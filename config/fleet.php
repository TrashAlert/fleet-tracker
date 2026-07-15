<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fleet Tracking Thresholds
    |--------------------------------------------------------------------------
    */

    // Speed above which an overspeed alert is created (km/h)
    'overspeed_threshold_kmh' => env('GPS_OVERSPEED_THRESHOLD', 110),

    // Minutes past expected delivery before a shipment is marked delayed
    'delay_threshold_minutes' => env('GPS_DELAY_THRESHOLD_MINUTES', 15),

    // Seconds without GPS data before a vehicle is considered offline
    'gps_stale_timeout_seconds' => env('GPS_STALE_TIMEOUT_SECONDS', 60),

    // MQTT topic prefix
    'mqtt_topic_prefix' => env('MQTT_TOPIC_PREFIX', 'fleet/'),

    // Maximum active (pending/in_transit/delayed) shipments per vehicle
    'max_active_shipments' => env('FLEET_MAX_ACTIVE_SHIPMENTS', 20),

    // Seconds of GPS silence before an offline ALERT is raised
    // (separate from gps_stale_timeout_seconds which only affects the
    //  dashboard online/offline pill — brief tunnel drops shouldn't alert)
    'offline_alert_threshold_seconds' => env('GPS_OFFLINE_ALERT_SECONDS', 180),

    // Where is OSRM located
    'osrm_url' => env('OSRM_URL', 'http://localhost:5001'),

    // Nominatin config
    'nominatim_url' => env('NOMINATIM_URL', 'http://localhost:8082'),
    'nominatim_country_codes' => env('NOMINATIM_COUNTRY_CODES', 'my'),

    // Delivery service tiers offered to customers (and to admins alongside a
    // "custom date" escape hatch). expected_delivery_at = now() + days.
    // Add/adjust tiers here — forms and validation render from this list.
    'delivery_tiers' => [
        'standard' => ['label' => 'Standard Delivery', 'days' => 5],
        'express' => ['label' => 'Express Delivery',  'days' => 2],
    ],
];
