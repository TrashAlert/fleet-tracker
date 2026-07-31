#!/bin/bash
# ============================================================
# Fleet Tracker — Laravel Server Setup Script
# Run as root on Ubuntu 22.04 / 24.04
# ============================================================

set -e

APP_DIR="/var/www/fleet-tracker"
DB_NAME="fleet_tracker"
DB_USER="fleet_user"
DB_PASS="change_me_in_production"

echo "=== [1/7] Updating system packages ==="
apt-get update -qq
apt-get install -y -qq \
    nginx mysql-server php8.3-fpm php8.3-cli \
    php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath \
    mosquitto mosquitto-clients \
    supervisor curl unzip git openssl

echo "=== [2/7] Installing Composer ==="
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

echo "=== [3/7] Setting up MySQL database ==="
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "=== [4/7] Generating TLS certificates for Mosquitto ==="
CERT_DIR="/etc/mosquitto/certs"
mkdir -p $CERT_DIR

# Certificate Authority
openssl req -new -x509 -days 3650 -nodes \
    -out $CERT_DIR/ca.crt \
    -keyout $CERT_DIR/ca.key \
    -subj "/CN=FleetTrackerCA"

# Server certificate
openssl req -new -nodes \
    -out $CERT_DIR/server.csr \
    -keyout $CERT_DIR/server.key \
    -subj "/CN=localhost"

openssl x509 -req -days 3650 \
    -in $CERT_DIR/server.csr \
    -CA $CERT_DIR/ca.crt \
    -CAkey $CERT_DIR/ca.key \
    -CAcreateserial \
    -out $CERT_DIR/server.crt

chown -R mosquitto:mosquitto $CERT_DIR
chmod 640 $CERT_DIR/*.key

echo "=== [5/7] Configuring Mosquitto MQTT broker ==="
cat > /etc/mosquitto/conf.d/fleet.conf <<MQTT
# Auth applies to BOTH listeners below (global by default in mosquitto 2.x).
allow_anonymous false
password_file /etc/mosquitto/passwd
acl_file /etc/mosquitto/aclfile

# Listener 1 — the Laravel server, over localhost, TLS (unchanged).
listener 8883
cafile /etc/mosquitto/certs/ca.crt
certfile /etc/mosquitto/certs/server.crt
keyfile /etc/mosquitto/certs/server.key
require_certificate false

# Listener 2 — ESP32 devices, plain MQTT inside the Husarnet VPN (Husarnet
# encrypts the link, so no TLS here).
# SECURITY: bind this to the host's Husarnet IPv6 (e.g. "listener 1883 <husarnet-ipv6>")
# or firewall port 1883 to the Husarnet interface so it is never exposed publicly.
listener 1883
MQTT

# ACL — least privilege: the server reads/writes the whole fleet namespace (it
# also publishes its own liveness beacon), while each device may publish ONLY its
# own vehicle topic. Add a "user ... / topic write fleet/<CLIENT_ID>/#" pair per device.
cat > /etc/mosquitto/aclfile <<ACL
user fleet_server
topic readwrite fleet/#

user esp32_client
topic write fleet/ESP32_VEHICLE_01/#
ACL
chown mosquitto:mosquitto /etc/mosquitto/aclfile
chmod 640 /etc/mosquitto/aclfile

# MQTT users. Passwords are dev placeholders — change them in production.
mosquitto_passwd -c -b /etc/mosquitto/passwd fleet_server changeme_mqtt
# Device login — MUST match MQTT_USER / MQTT_PASS in the ESP32 firmware.
mosquitto_passwd -b /etc/mosquitto/passwd esp32_client abc123

systemctl enable mosquitto
systemctl restart mosquitto

echo "=== [6/7] Setting up Laravel application ==="
if [ ! -d "$APP_DIR" ]; then
    git clone . $APP_DIR    # replace with your actual repo URL
fi

cd $APP_DIR
cp .env.example .env

# Update .env with DB credentials
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/MQTT_AUTH_PASSWORD=.*/MQTT_AUTH_PASSWORD=changeme_mqtt/" .env

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache

chown -R www-data:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache

echo "=== [7/7] Setting up Supervisor for MQTT subscriber ==="
cp $APP_DIR/fleet-mqtt-subscriber.conf /etc/supervisor/conf.d/
supervisorctl reread
supervisorctl update
supervisorctl start fleet-mqtt-subscriber

echo ""
echo "✅ Setup complete!"
echo "   Dashboard:       http://your-server/fleet"
echo "   Client tracking: http://your-server/track"
echo "   MQTT broker:     localhost:8883 (TLS, Laravel)  |  1883 (plain, ESP32 over Husarnet)"
echo ""
echo "⚠️  Remember to:"
echo "   1. Update MQTT passwords in /etc/mosquitto/passwd (match them in the ESP32 firmware)"
echo "   2. Configure Nginx virtual host for your domain"
echo "   3. Join THIS host to your Husarnet network; bind/firewall port 1883 to the Husarnet interface"
echo "   4. In the dashboard, add a Vehicle with MQTT Client ID = ESP32_VEHICLE_01 (exact case)"
