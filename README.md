# FleetTrack — IoT GPS Fleet Tracking System

A Laravel 13 application for real-time GPS fleet tracking and delivery management.
ESP32 devices mounted on vehicles publish GPS fixes over MQTT; a long-running
Artisan daemon ingests them, derives alerts (overspeed, geofence, delay, offline)
and shipment status, and a role-scoped web dashboard plus a public client
tracking page expose the data.

The entire UI is **server-rendered Blade + Leaflet** — no SPA and no build step
(assets load via CDN + inline styles/scripts; there is no `npm`/Vite pipeline).

---

## Features

- **Live fleet map** — vehicle positions, headings and speeds polled every 5s,
  with per-vehicle trip history playback (speed-colored polylines). Overlapping
  vehicles collapse into count-bubble clusters, and the map has fullscreen,
  fit-all-vehicles, and street/satellite controls.
- **Role-based access** — `admin`, `manager`, `driver`. Drivers get *scoped
  data*, not just hidden UI: queries are filtered to their assigned vehicle
  server-side. Deactivated accounts are force-logged-out on their next request.
  Drivers get a mobile-first dashboard — bottom tab nav, large tap targets, and
  pull-to-refresh.
- **Shipment lifecycle** — `pending → in_transit → delivered` with `cancelled`
  and `returned` as terminal. `delayed` means *late and not yet started*: a
  pending shipment past its ETA flips to `delayed` (still startable); a started
  shipment that runs late keeps `in_transit` and shows an OVERDUE badge instead.
  Drivers start deliveries, and confirm them with a mandatory proof photo,
  validated server-side against a 200 m destination geofence.
- **Failed deliveries** — a driver on an active delivery can report a failed
  attempt (recipient absent, wrong address, refused, …) with an optional photo,
  from anywhere (no geofence). Each failure re-queues the parcel for another try
  and emails the customer; after a configurable number of attempts
  (`max_delivery_attempts`) the shipment is returned to sender. Every attempt
  and the return show up on the customer's tracking timeline.
- **Alerts** — overspeed, delay (packet-driven *and* a scheduled sweep),
  offline-vehicle detection, and a "left the delivery zone without confirming"
  geofence flag.
- **Client tracking portal** — public, no-auth page (`/track?code=…`) where
  customers follow their shipment: a delivery-history timeline (created →
  out for delivery → arriving → delivered, curated from the activity log with
  client-safe labels), live queue position, and a live map. Live location is
  only exposed while the shipment is actually moving (suppressed server-side
  otherwise); road ETA via OSRM with straight-line fallback. Light/dark themed,
  with client-side form validation on the shipment-request form.
- **Shipment requests (tickets)** — customers without an account request a
  shipment straight from the tracking portal (throttled public form). They get
  a request code to read to staff; admins/managers review the queue and either
  deny or approve — approval creates the real shipment from a prefilled form
  and emails the customer exactly once, with their new tracking code.
- **Address geocoding** — shipment destinations are searched via a self-hosted
  Nominatim instance with autocomplete, two-way synced with a map pin picker;
  plus a free-text delivery-instructions field surfaced to the driver.
- **Route ETA & manifest optimization** — self-hosted OSRM computes road
  distance/drive time (client ETA, per-stop figures), and its Trip service
  (a TSP solver) orders the driver's stops as an optimized tour: the started
  delivery stays first, the remaining stops are toured from its destination.
  The tour is drawn on the driver's map as a manifest line, and customers see
  their queue position ("2 deliveries before yours") on the tracking page —
  a privacy-safe count that reveals no vehicle location. Both OSRM and
  Nominatim are *soft dependencies* — when unreachable, the app degrades to
  nearest-first/straight-line ordering and manual pin entry instead of
  erroring.
- **Admin settings** — an admin-only page (`/fleet/settings`) to tune operational
  thresholds (overspeed, delay, GPS-staleness/offline, max active shipments, max
  delivery attempts, geofence radius) at runtime. Values persist to the database
  and override `config/fleet.php` at boot, so no redeploy is needed; daemon-side
  thresholds take effect after the MQTT subscriber restarts.
- **Activity log** — automatic audit trail of model changes (with sensitive-field
  redaction) plus system events (MQTT ingestion, alerts, delivery flow), with
  keyword search (plate / name / action) and vehicle/source/date filters.
- **Email notifications** — shipment created, delivery delayed, delivery
  confirmed (to the client).
- **Performance page** — per-vehicle analytics (uses MySQL window functions) and
  PDF export (dompdf).

---

## Architecture

```
ESP32 (GPS + SIM/WiFi)
   │  MQTT publish: fleet/{mqtt_client_id}/telemetry
   ▼
MQTT broker (TLS-capable, e.g. Mosquitto)
   │
   ▼
php artisan mqtt:subscribe          ← long-running daemon (Supervisor in prod)
   ├─ persists GpsTelemetry row
   ├─ overspeed check → Alert
   ├─ per-shipment 200m geofence → arrival / left-zone flag
   ├─ delay detection → status flip + client email  (shared ShipmentDelayService)
   └─ activity log entries
   ▼
MySQL ── FleetController / ClientTrackingController ── Blade + Leaflet UI
              │
              ├─ OSRM (self-hosted, soft dep)      → road ETA / distance
              └─ Nominatim (self-hosted, soft dep) → address search / reverse geocode

Scheduler (every minute):
   fleet:check-offline   → offline alerts for silent vehicles on active deliveries
   fleet:check-delays    → packet-independent delay sweep (vehicle silent ≠ never delayed)
```

### Project structure (key files)

```
app/
├── Console/Commands/
│   ├── MqttSubscriber.php           ← MQTT ingestion daemon (the core pipeline)
│   ├── CheckOfflineVehicles.php     ← fleet:check-offline sweep
│   └── CheckDelayedShipments.php    ← fleet:check-delays sweep
├── Http/Controllers/
│   ├── FleetController.php          ← dashboard, live API, shipments, lifecycle
│   ├── ClientTrackingController.php ← public tracking portal + status JSON + timeline
│   ├── ShipmentTicketController.php ← client shipment requests (submit / review / deny)
│   ├── GeocodingController.php      ← Nominatim proxy (search / reverse)
│   ├── OriginLocationController.php ← warehouse/depot presets CRUD
│   ├── ActivityLogController.php    ← audit trail UI + API
│   ├── PerformanceController.php    ← analytics + PDF export
│   ├── UserController.php           ← user management (admin)
│   └── Auth/LoginController.php     ← hand-rolled auth (no starter kit)
├── Http/Middleware/
│   ├── RoleMiddleware.php           ← role:admin,manager route gate
│   └── EnsureUserIsActive.php       ← force-logout for deactivated accounts
├── Models/                          ← Vehicle, GpsTelemetry, Shipment, Alert,
│   │                                  ShipmentTicket, User, OriginLocation, ActivityLog
├── Services/
│   ├── OsrmService.php              ← OSRM client (Table / Route / Trip-TSP)
│   ├── ManifestService.php          ← single source of truth for stop visit order
│   ├── NominatimService.php         ← geocode search/reverse (cached)
│   ├── ShipmentDelayService.php     ← single source of truth for delay handling
│   └── ActivityLogger.php           ← audit-trail writer
├── Notifications/                   ← ShipmentCreated / DeliveryDelayed /
│                                      DeliveryConfirmed / ShipmentTicketApproved
└── Traits/Loggable.php              ← auto audit-logging on model events

config/fleet.php                     ← all fleet tunables (env-overridable)
resources/views/{fleet,client,auth,layouts,pdf}/  ← the actual UI (Blade + Leaflet)
routes/web.php                       ← all routes;  routes/console.php ← scheduler
fleet-mqtt-subscriber.conf           ← Supervisor config for the daemon
setup.sh                             ← one-shot Ubuntu server setup
tests/                               ← PHPUnit feature suites (in-memory SQLite)
```

---

## Local Setup

### Requirements
- PHP 8.3+ (developed on 8.4) with `mbstring`, `xml`, `curl`, `zip`, `gd`, `pdo_mysql`
- Composer, MySQL 8, and an MQTT broker (e.g. Mosquitto) for live ingestion

### Steps

```bash
git clone <repo> fleet-tracker && cd fleet-tracker
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` — the essentials:

```dotenv
DB_DATABASE=fleet_tracker
DB_USERNAME=...
DB_PASSWORD=...

# MQTT broker (php-mqtt/laravel-client)
MQTT_HOST=127.0.0.1
MQTT_PORT=1883            # 8883 + MQTT_TLS_* for TLS
MQTT_AUTH_USERNAME=fleet_server
MQTT_AUTH_PASSWORD=...
MQTT_TOPIC_PREFIX=fleet/

# Soft dependencies (optional — app degrades gracefully without them)
OSRM_URL=http://localhost:5001
NOMINATIM_URL=http://localhost:8082
NOMINATIM_COUNTRY_CODES=my

# Client emails (delay / created / confirmed notifications)
MAIL_MAILER=smtp
MAIL_HOST=...
```

Then:

```bash
php artisan migrate
php artisan db:seed --class=AdminSeeder
```

This seeds the default admin — **change the password after first login**:

> `admin@fleettrack.local` / `Admin@1234`

For a populated demo (vehicles, drivers, shipments in every status, live GPS,
alerts, and client timelines) there's a **re-runnable** seeder that resets the
fleet data while keeping the admin:

```bash
php artisan db:seed --class=PresentationSeeder
```

Two region-themed variants exist with the same shape and safety — Putrajaya
(`F` plates, Putrajaya/Cyberjaya landmarks) and Melaka (`M` plates, Melaka
landmarks):

```bash
php artisan db:seed --class=PutrajayaSeeder
php artisan db:seed --class=MelakaSeeder
```

All three are re-runnable wipe-and-seed and reset the same fleet tables, so
running one **replaces** the others' data. All seeded customer emails use
`@example.com` (non-deliverable), so it's safe to run even with real mail
configured. Seeded drivers log in with `Password@123`.

There is **no self-registration**: admins create manager/driver accounts from
the Users page. A driver is linked to a vehicle via `users.vehicle_id`.

### Running (three processes)

```bash
php artisan serve            # web app → http://localhost:8000
php artisan mqtt:subscribe   # MQTT ingestion daemon (required for GPS data)
php artisan schedule:work    # runs the offline + delay sweeps every minute
```

---

## Configuration (`config/fleet.php`)

All tunables are env-overridable:

| Key | Env | Default | Purpose |
|---|---|---|---|
| `overspeed_threshold_kmh` | `GPS_OVERSPEED_THRESHOLD` | 110 | Speed that raises an overspeed alert |
| `delay_threshold_minutes` | `GPS_DELAY_THRESHOLD_MINUTES` | 15 | Minutes past ETA before a shipment is *delayed* |
| `gps_stale_timeout_seconds` | `GPS_STALE_TIMEOUT_SECONDS` | 60 | Dashboard online/offline pill (cosmetic) |
| `geofence_radius_metres` | `FLEET_GEOFENCE_RADIUS_METRES` | 200 | Destination arrival/confirm zone radius |
| `offline_alert_threshold_seconds` | `GPS_OFFLINE_ALERT_SECONDS` | 180 | GPS silence before an offline **alert** |
| `max_active_shipments` | `FLEET_MAX_ACTIVE_SHIPMENTS` | 20 | Per-vehicle active-shipment cap at creation |
| `max_delivery_attempts` | `FLEET_MAX_DELIVERY_ATTEMPTS` | 3 | Failed attempts before return-to-sender |
| `mqtt_topic_prefix` | `MQTT_TOPIC_PREFIX` | `fleet/` | Telemetry topic prefix |
| `osrm_url` | `OSRM_URL` | `http://localhost:5001` | Self-hosted OSRM (road ETA) |
| `nominatim_url` | `NOMINATIM_URL` | `http://localhost:8082` | Self-hosted Nominatim (geocoding) |
| `nominatim_country_codes` | `NOMINATIM_COUNTRY_CODES` | `my` | Geocode result country filter |
| `delivery_tiers` | — | standard 5d, express 2d | Service tiers; expected date = now + tier days (admins also get a custom-date option) |

Note the two distinct staleness settings: the short one only drives the
dashboard's online/offline pill; the long one raises the actual offline alert
(so brief tunnel drops don't page anyone).

**Session idle timeout** is a Laravel setting, not a `fleet.php` key:
`SESSION_LIFETIME` (minutes, default `60`) controls how long an idle session lasts.
A client-side warning + auto-logout (in the layout) warns before expiry and pings
an authed `keep-alive` route to stay signed in.

---

## Roles

| Capability | admin | manager | driver |
|---|---|---|---|
| Live map / dashboard | all vehicles | all vehicles | own vehicle only |
| Shipments | create, override status | create, override status | view own, start, confirm, report failed |
| Shipment request tickets | review, approve, deny | review, approve, deny | — |
| Vehicles / origins CRUD | ✔ | ✔ | — |
| Activity log / performance | ✔ | ✔ | — |
| User management | ✔ | — | — |
| Settings (operational thresholds) | ✔ | — | — |

---

## Key Routes

| Route | Description |
|---|---|
| `GET /fleet` | Dashboard (role-scoped; drivers get their own view) |
| `GET /fleet/api/live` | Live vehicle positions JSON (polled by the map) |
| `GET /fleet/api/vehicle/{id}/history?date=YYYY-MM-DD` | Trip history (drivers: own vehicle only) |
| `POST /fleet/api/shipments` | Create shipment (admin/manager) |
| `GET /fleet/api/geocode?q=…` / `…/reverse?lat=&lng=` | Nominatim proxy (admin/manager, throttled) |
| `POST /fleet/api/shipments/{id}/start-delivery` | Driver starts a delivery (one active per vehicle) |
| `POST /fleet/api/shipments/{id}/confirm-delivery` | Driver confirms — photo required, 200 m re-check |
| `POST /fleet/api/shipments/{id}/fail-delivery` | Driver reports a failed attempt (reason, optional photo; no geofence) |
| `GET /track?code=XXXXXXXXXX` | Client tracking portal (public, throttled) |
| `GET /api/track/{code}/status` | Shipment status JSON (public, polled, throttled) |
| `POST /api/track/request` | Client shipment request → ticket (public, throttled 5/min) |

---

## MQTT

Topic: `{MQTT_TOPIC_PREFIX}{mqtt_client_id}/telemetry` (default `fleet/…`).
The `mqtt_client_id` in the topic must match the `vehicles.mqtt_client_id` column.

ESP32 JSON payload:

```json
{
  "lat": 3.1234567,
  "lng": 101.1234567,
  "speed": 65.3,
  "heading": 182.5,
  "satellites": 8,
  "hdop": 1.2,
  "ts": 1712345678
}
```

Timestamps before 2020 (an ESP32 sending `millis()` before its first GPS time
fix) are rejected and replaced with the server time.

---

## Testing

PHPUnit (plain, not Pest). Every test runs against **in-memory SQLite** — never
the real database.

```bash
vendor/bin/phpunit                          # full suite
vendor/bin/phpunit tests/Feature/RoleAccessTest.php
vendor/bin/phpunit --filter test_name
vendor/bin/pint                             # code style (Laravel preset)
```

Suites cover the role/access matrix, the shipment lifecycle state machine, the
real MQTT ingestion pipeline (synthetic packets through `processTelemetry`),
the delay sweep, the driver manifest ordering (OSRM faked), the client portal's
privacy contract (location suppression, rate limits, timeline, queue position),
the ticket flow, and the geocoding proxy. `Tests\Concerns\CreatesFleetData` is
the shared, deliberately factory-free builder trait — use its helpers
(`makeVehicle()`, `makeDriver()`, `makeShipment()`, `makeTicket()`, `packet()`, …)
in new tests.

Note: the Performance page's raw MySQL window-function queries are not covered
by the SQLite-based suite.

---

## Production

```bash
sudo bash setup.sh          # one-shot Ubuntu 22.04/24.04 provisioning
```

**MQTT daemon under Supervisor:**

```bash
sudo cp fleet-mqtt-subscriber.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start fleet-mqtt-subscriber
```

> ⚠ **After deploying changes to `MqttSubscriber.php` or anything it uses
> (models, services), restart the daemon** — a running process keeps executing
> the old code with no error, just wrong behavior.

**Scheduler** — one cron entry drives both sweeps:

```
* * * * * cd /var/www/fleet-tracker && php artisan schedule:run >> /dev/null 2>&1
```

Point nginx at `/var/www/fleet-tracker/public`. **Delivery-proof photos are
private** — stored on `storage/app/private/delivery-proofs/` and served only
through the authenticated, role-scoped route `GET /fleet/api/shipments/{id}/photo`
(never a public URL). On deploy, run the one-time move of any existing files from
`storage/app/public/delivery-proofs/` to `storage/app/private/delivery-proofs/`.

---

## Known issues / open items

1. `pusher/pusher-php-server` and `laravel/sanctum` are unused dependencies.
