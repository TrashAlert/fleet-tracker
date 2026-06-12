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
    'max_active_shipments' => env('FLEET_MAX_ACTIVE_SHIPMENTS', 10),
];
