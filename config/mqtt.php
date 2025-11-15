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
];
