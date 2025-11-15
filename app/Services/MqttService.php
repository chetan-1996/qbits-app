<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    private function connect()
    {
        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setKeepAliveInterval(config('mqtt.keep_alive'));

        $client = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id_prefix') . '_' . uniqid()
        );

        $client->connect($settings, true);

        return $client;
    }

    public function publish($topic, $data, $qos = null)
    {
        $client = $this->connect();

        $payload = is_string($data) ? $data : json_encode($data);
        $client->publish($topic, $payload, $qos ?? config('mqtt.qos'));

        $client->disconnect();
    }
    // protected $mqtt;

    // public function __construct()
    // {
    //     $host = env('MQTT_HOST', 'localhost');
    //     $port = env('MQTT_PORT', 1883);

    //     $clientId = env('MQTT_CLIENT_ID', 'laravel-client-' . rand(1000, 9999));

    //     $connectionSettings = (new ConnectionSettings)
    //         ->setUsername(env('MQTT_USERNAME'))
    //         ->setPassword(env('MQTT_PASSWORD'))
    //         ->setKeepAliveInterval(60)
    //         ->setUseTls(false);

    //     $this->mqtt = new MqttClient($host, $port, $clientId);
    //     $this->mqtt->connect($connectionSettings, true);
    // }

    // public function publish($topic, $message)
    // {
    //     $this->mqtt->publish($topic, $message, 0);
    //     $this->mqtt->disconnect();
    // }

    // public function subscribe($topic, callable $callback)
    // {
    //     $this->mqtt->subscribe($topic, $callback, 0);
    //     $this->mqtt->loop(true);
    // }
}
