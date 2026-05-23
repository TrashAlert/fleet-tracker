# IoT-Based GPS Fleet Tracking System — Laravel Backend

## Project Structure

```
fleet-tracker/
├── app/
│   ├── Console/Commands/
│   │   └── MqttSubscriber.php       ← MQTT daemon (receives ESP32 GPS data)
│   ├── Http/Controllers/
│   │   ├── FleetController.php      ← Fleet manager dashboard & API
│   │   └── ClientTrackingController.php ← Client shipment tracking portal
│   ├── Models/
│   │   ├── Vehicle.php
│   │   ├── GpsTelemetry.php
│   │   ├── Shipment.php
│   │   └── Alert.php
│   └── Notifications/
│       └── DeliveryDelayedNotification.php
├── config/
│   └── fleet.php                    ← GPS thresholds, MQTT prefix
├── database/migrations/
│   ├── ..._create_vehicles_table.php
│   ├── ..._create_gps_telemetry_table.php
│   ├── ..._create_shipments_table.php
│   └── ..._create_alerts_table.php
├── routes/
│   └── web.php
├── .env.example
├── fleet-mqtt-subscriber.conf       ← Supervisor config
└── setup.sh                         ← One-shot server setup script
```

---

## Step-by-Step Local Setup (Development)

### 1. Install PHP & Composer
```bash
# Ubuntu / WSL
sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Create Laravel project and copy these files
```bash
composer create-project laravel/laravel fleet-tracker
cd fleet-tracker

# Copy all provided files into the matching directories
# Then install the MQTT client package:
composer require php-mqtt/laravel-client

# Publish the MQTT config file (creates config/mqtt-client.php)
php artisan vendor:publish --provider="PhpMqtt\Client\MqttClientServiceProvider" --tag="config"
```

### 3. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```
DB_DATABASE=fleet_tracker
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_pass

MQTT_HOST=127.0.0.1
MQTT_PORT=8883
MQTT_USERNAME=fleet_server
MQTT_PASSWORD=your_mqtt_password
```

### 4. Run database migrations
```bash
php artisan migrate
```

### 5. Start the development server
```bash
php artisan serve        # Web app at http://localhost:8000
```

### 6. Start MQTT subscriber (separate terminal)
```bash
php artisan mqtt:subscribe
```

---

## Production Deployment

Run the automated setup script on Ubuntu 22.04/24.04:
```bash
sudo bash setup.sh
```

Then configure Nginx to point to `/var/www/fleet-tracker/public`.

---

## Key Routes

| Route | Description |
|---|---|
| `GET /fleet` | Fleet manager dashboard (auth required) |
| `GET /fleet/api/live` | Live JSON positions for all vehicles |
| `GET /fleet/api/vehicle/{id}/history?date=YYYY-MM-DD` | Trip history |
| `POST /fleet/api/shipments` | Create new shipment |
| `GET /track?code=XXXXXXXXXX` | Client tracking portal (public) |
| `GET /api/track/{code}/status` | Live shipment status JSON |

---

## MQTT Topic Structure

```
fleet/{mqtt_client_id}/telemetry
```

**ESP32 JSON payload:**
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

The `mqtt_client_id` in the topic must match the `mqtt_client_id` column in the `vehicles` table.

---

## Supervisor (Production MQTT Daemon)

```bash
sudo cp fleet-mqtt-subscriber.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start fleet-mqtt-subscriber
sudo supervisorctl status  # check it's RUNNING
```
# fleet-tracker
# fleet-tracker
