# FleetTrack — IoT GPS Fleet Tracking System

A Laravel 13 application for real-time GPS fleet tracking and delivery management.
ESP32 devices mounted on vehicles publish GPS fixes over MQTT; a long-running
Artisan daemon ingests them, derives alerts (overspeed, geofence, delay, offline)
and shipment status, and a role-scoped web dashboard plus a public client
tracking page expose the data.

The entire UI is **server-rendered Blade + Leaflet** (no SPA — the Vite/Vue
tooling in `package.json` is vestigial and not wired up).

---

## Features

- **Live fleet map** — vehicle positions, headings and speeds polled every 5s,
  with per-vehicle trip history playback (speed-colored polylines).
- **Role-based access** — `admin`, `manager`, `driver`. Drivers get *scoped
  data*, not just hidden UI: queries are filtered to their assigned vehicle
  server-side. Deactivated accounts are force-logged-out on their next request.
- **Shipment lifecycle** — `pending → in_transit → delivered` with `delayed` as
  a side-status and `cancelled` as terminal. Drivers start deliveries, and
  confirm them with a mandatory proof photo, validated server-side against a
  200 m destination geofence.
- **Alerts** — overspeed, delay (packet-driven *and* a scheduled sweep),
  offline-vehicle detection, and a "left the delivery zone without confirming"
  geofence flag.
- **Client tracking portal** — public, no-auth page (`/track?code=…`) where
  customers follow their shipment. Live location is only exposed while the
  shipment is actually moving (suppressed server-side otherwise); road ETA via
  OSRM with straight-line fallback.
- **Address geocoding** — shipment destinations are searched via a self-hosted
  Nominatim instance with autocomplete, two-way synced with a map pin picker;
  plus a free-text delivery-instructions field surfaced to the driver.
- **Route ETA** — self-hosted OSRM computes road distance/drive time (driver
  dashboard ordering, client ETA). Both OSRM and Nominatim are *soft
  dependencies* — when unreachable, the app degrades to straight-line distance
  and manual pin entry instead of erroring.
- **Activity log** — automatic audit trail of model changes (with sensitive-field
  redaction) plus system events (MQTT ingestion, alerts, delivery flow).
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
│   ├── ClientTrackingController.php ← public tracking portal + status JSON
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
│   │                                  User, OriginLocation, ActivityLog
├── Services/
│   ├── OsrmService.php              ← road distance/duration (Table service)
│   ├── NominatimService.php         ← geocode search/reverse (cached)
│   ├── ShipmentDelayService.php     ← single source of truth for delay handling
│   └── ActivityLogger.php           ← audit-trail writer
├── Notifications/                   ← ShipmentCreated / DeliveryDelayed / DeliveryConfirmed
└── Traits/Loggable.php              ← auto audit-logging on model events

config/fleet.php                     ← all fleet tunables (env-overridable)
resources/views/{fleet,client,auth,layouts}/  ← the actual UI (Blade + Leaflet)
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
| `offline_alert_threshold_seconds` | `GPS_OFFLINE_ALERT_SECONDS` | 180 | GPS silence before an offline **alert** |
| `max_active_shipments` | `FLEET_MAX_ACTIVE_SHIPMENTS` | 20 | Per-vehicle active-shipment cap at creation |
| `mqtt_topic_prefix` | `MQTT_TOPIC_PREFIX` | `fleet/` | Telemetry topic prefix |
| `osrm_url` | `OSRM_URL` | `http://localhost:5001` | Self-hosted OSRM (road ETA) |
| `nominatim_url` | `NOMINATIM_URL` | `http://localhost:8082` | Self-hosted Nominatim (geocoding) |
| `nominatim_country_codes` | `NOMINATIM_COUNTRY_CODES` | `my` | Geocode result country filter |

Note the two distinct staleness settings: the short one only drives the
dashboard's online/offline pill; the long one raises the actual offline alert
(so brief tunnel drops don't page anyone).

---

## Roles

| Capability | admin | manager | driver |
|---|---|---|---|
| Live map / dashboard | all vehicles | all vehicles | own vehicle only |
| Shipments | create, override status | create, override status | view own, start, confirm |
| Vehicles / origins CRUD | ✔ | ✔ | — |
| Activity log / performance | ✔ | ✔ | — |
| User management | ✔ | — | — |

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
| `GET /track?code=XXXXXXXXXX` | Client tracking portal (public) |
| `GET /api/track/{code}/status` | Shipment status JSON (public, polled) |

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
the delay sweep, and the geocoding proxy. `Tests\Concerns\CreatesFleetData` is
the shared, deliberately factory-free builder trait — use its helpers
(`makeVehicle()`, `makeDriver()`, `makeShipment()`, `packet()`, …) in new tests.

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

Point nginx at `/var/www/fleet-tracker/public` and ensure the web user can read
`storage/app/public` (delivery-proof photos are served from there via
`storage:link`).

---

## Known issues / open items

1. **`bootstrap/providers.php` references providers that don't exist**
   (`AppServiceProvider`, `FortifyServiceProvider`) — a fresh clone or
   `php artisan optimize:clear` fatals with *Class not found*. Fix: create an
   empty real `App\Providers\AppServiceProvider` and remove the Fortify line
   (auth here is hand-rolled; Fortify was never a dependency).
2. Public tracking routes (`/track`, `/api/track/{code}/status`) are
   **unthrottled** — add `throttle:` middleware before an internet-facing deploy.
3. MQTT payloads are **not bounds-checked** (lat/lng/speed ranges) before
   persisting.
4. Frontend build tooling (`package.json`, `vite.config.ts`, `components.json`)
   is configured for Inertia/Vue but not wired up — the real UI is Blade. Don't
   assume `npm run dev` works.
5. `pusher/pusher-php-server` is an unused dependency.
