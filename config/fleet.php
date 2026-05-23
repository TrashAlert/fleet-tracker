<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GPS / Fleet Settings
    |--------------------------------------------------------------------------
    */

    // Speed (km/h) above which an overspeed alert is triggered
    'overspeed_threshold_kmh' => (int) env('GPS_OVERSPEED_THRESHOLD', 110),

    // Minutes past expected_delivery_at before a shipment is flagged as delayed
    'delay_threshold_minutes' => (int) env('GPS_DELAY_THRESHOLD_MINUTES', 15),

    // Seconds without a GPS update before a vehicle is considered offline
    'gps_stale_timeout_seconds' => (int) env('GPS_STALE_TIMEOUT_SECONDS', 60),

    // MQTT topic prefix — must match firmware topic structure
    'mqtt_topic_prefix' => env('MQTT_TOPIC_PREFIX', 'fleet/'),
];
