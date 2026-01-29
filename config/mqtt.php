<?php

return [
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => env('MQTT_PORT', 1883),

    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    'client_id_prefix' => env('MQTT_CLIENT_PREFIX', 'laravel_mqtt'),
    'keep_alive' => env('MQTT_KEEPALIVE', 60),
    'qos' => env('MQTT_QOS', 0),

    // Redis list where raw MQTT messages are pushed
    'redis_queue_list' => env('MQTT_REDIS_LIST', 'mqtt:inverter_queue'),

    // ✅ TLS CA file
    'ca_file' => env('MQTT_CA_FILE', '/etc/mosquitto/certs/ca.crt'),

    // ✅ SSL verify settings (same like mosquitto_pub/sub cafile)
    'tls' => [
        'enabled' => env('MQTT_TLS', true),
        'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
    ],
];
