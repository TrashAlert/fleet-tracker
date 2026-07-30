<?php

declare(strict_types=1);

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

return [

    'default_connection' => 'fleet',

    'connections' => [

        'fleet' => [
            'host'      => env('MQTT_HOST', '127.0.0.1'),
            'port'      => (int) env('MQTT_PORT', 8883),
            'protocol'  => MqttClient::MQTT_3_1,
            'client_id' => env('MQTT_CLIENT_ID', 'fleet-laravel'),

            // Persistent session (NOT clean). Required for auto_reconnect below —
            // php-mqtt forbids clean_session + auto_reconnect together — and it lets
            // the broker queue telemetry (QoS 1) while the daemon is briefly down,
            // delivering the backlog on reconnect instead of losing those fixes.
            'use_clean_session' => env('MQTT_CLEAN_SESSION', false),
            'enable_logging'    => true,
            'log_channel'       => null,
            'repository'        => MemoryRepository::class,

            'connection_settings' => [

                'tls' => [
                    // Enable TLS — must match port 8883
                    'enabled'                          => env('MQTT_TLS_ENABLED', false),

                    // Required for self-signed certificates
                    'allow_self_signed_certificate'    => env('MQTT_TLS_ALLOW_SELF_SIGNED_CERT', true),

                    // Verify broker cert is signed by our CA
                    'verify_peer'                      => env('MQTT_TLS_VERIFY_PEER', true),

                    // Disable peer name check — CN=localhost vs IP=127.0.0.1 mismatch
                    'verify_peer_name'                 => env('MQTT_TLS_VERIFY_PEER_NAME', false),

                    // CA cert path — used to verify the broker's certificate
                    'ca_file'                          => env('MQTT_TLS_CA_FILE'),
                    'ca_path'                          => null,

                    // No client cert needed — broker uses username/password auth
                    'client_certificate_file'          => null,
                    'client_certificate_key_file'      => null,
                    'client_certificate_key_passphrase'=> null,
                    'alpn'                             => null,
                ],

                'auth' => [
                    'username' => env('MQTT_AUTH_USERNAME'),
                    'password' => env('MQTT_AUTH_PASSWORD'),
                ],

                // Presence beacon: if this daemon drops UNgracefully, the broker
                // auto-publishes "offline" (retained) to the status topic. The
                // mqtt:subscribe command publishes "online" on connect and after
                // every reconnect, so a monitor subscribing to this topic always
                // sees whether GPS ingestion is live.
                'last_will' => [
                    'topic'              => env('MQTT_STATUS_TOPIC', 'fleet/system/subscriber/status'),
                    'message'            => 'offline',
                    'quality_of_service' => 1,
                    'retain'             => true,
                ],

                'connect_timeout'      => env('MQTT_CONNECT_TIMEOUT', 60),
                'socket_timeout'       => env('MQTT_SOCKET_TIMEOUT', 5),
                'resend_timeout'       => env('MQTT_RESEND_TIMEOUT', 10),
                'keep_alive_interval'  => env('MQTT_KEEP_ALIVE_INTERVAL', 60),

                // Reconnect in-process on a dropped broker connection rather than
                // relying solely on a Supervisor restart. Needs the persistent
                // session above. If attempts are exhausted the loop throws, the
                // process exits, and Supervisor autorestart takes over (backstop).
                'auto_reconnect' => [
                    'enabled'                            => env('MQTT_AUTO_RECONNECT', true),
                    'max_reconnect_attempts'             => (int) env('MQTT_MAX_RECONNECT_ATTEMPTS', 5),
                    'delay_between_reconnect_attempts'   => (int) env('MQTT_RECONNECT_DELAY', 5),
                ],
            ],
        ],
    ],
];