<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    protected $mqtt;

    public function __construct()
    {
        $host = env('MQTT_HOST', 'localhost');
        $port = env('MQTT_PORT', 1883);
        
        $clientId = env('MQTT_CLIENT_ID', 'laravel-client-' . rand(1000, 9999));

        $connectionSettings = (new ConnectionSettings)
            ->setUsername(env('MQTT_USERNAME'))
            ->setPassword(env('MQTT_PASSWORD'))
            ->setKeepAliveInterval(60)
            ->setUseTls(false);

        $this->mqtt = new MqttClient($host, $port, $clientId);
        $this->mqtt->connect($connectionSettings, true);
    }

    public function publish($topic, $message)
    {
        $this->mqtt->publish($topic, $message, 0);
        $this->mqtt->disconnect();
    }

    public function subscribe($topic, callable $callback)
    {
        $this->mqtt->subscribe($topic, $callback, 0);
        $this->mqtt->loop(true);
    }
}
