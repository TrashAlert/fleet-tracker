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

            'use_clean_session' => true,
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

                'last_will' => [
                    'topic'              => null,
                    'message'            => null,
                    'quality_of_service' => 0,
                    'retain'             => false,
                ],

                'connect_timeout'      => env('MQTT_CONNECT_TIMEOUT', 60),
                'socket_timeout'       => env('MQTT_SOCKET_TIMEOUT', 5),
                'resend_timeout'       => env('MQTT_RESEND_TIMEOUT', 10),
                'keep_alive_interval'  => env('MQTT_KEEP_ALIVE_INTERVAL', 60),

                'auto_reconnect' => [
                    'enabled'                            => false,
                    'max_reconnect_attempts'             => 3,
                    'delay_between_reconnect_attempts'   => 5,
                ],
            ],
        ],
    ],
];