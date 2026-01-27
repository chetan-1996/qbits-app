<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    private MqttClient $client;

    public function connect(string $clientId): void
    {
        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setUseTls(true)
            ->setTlsCertificateAuthorityFile(config('mqtt.ca_file'))
            ->setKeepAliveInterval(60)
            ->setCleanSession(false); // persistent

        $this->client = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            $clientId
        );

        $this->client->connect($settings, true);
    }

    public function publish(string $topic, string $payload, int $qos = 1): void
    {
        $this->client->publish($topic, $payload, $qos);
        $this->client->disconnect();
    }

    public function subscribe(string $topic, callable $callback, int $qos = 1): void
    {
        $this->client->subscribe($topic, $callback, $qos);
        $this->client->loop(true);
    }
}
/*class MqttService
{
    private function connect()
    {
        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setKeepAliveInterval(config('mqtt.keep_alive'));

            // ✅ TLS enable for port 8883
        if ((int) config('mqtt.port') === 8883) {

            $settings->setUseTls(true);

            // cafile set (like --cafile /etc/mosquitto/certs/ca.crt)
            if (!empty(config('mqtt.ca_file'))) {
                $settings->setTlsCaFile(config('mqtt.ca_file'));
            }

            // verify settings (default = true)
            $settings->setTlsVerifyPeer(config('mqtt.tls.verify_peer', true));
            $settings->setTlsVerifyPeerName(config('mqtt.tls.verify_peer_name', true));
            $settings->setTlsSelfSignedAllowed(config('mqtt.tls.allow_self_signed', false));
        }

        $client = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id_prefix') . '_' . uniqid()
        );

        $client->connect($settings, true);

        return $client;
    }

    public function publish(string $topic, $data, ?int $qos = null, bool $retain = false): void
    {
        $clientId = 'test-publisher-1'; // same as your mosquitto command
        $client = $this->connect($clientId, true);

        $payload = is_string($data) ? $data : json_encode($data);
        $client->publish($topic, $payload, $qos ?? config('mqtt.qos'));

        $client->disconnect();
    }


    public function subscribe(string $topic, callable $callback, ?int $qos = null): void
    {
        $clientId = 'test-subscriber-1'; // same as your mosquitto command

        $cleanSession = false; // ✅ mosquitto_sub -c

        $client = $this->connect($clientId, $cleanSession);

        $client->subscribe($topic, function (string $t, string $message, bool $retained) use ($callback) {
            $callback($t, $message, $retained);
        }, $qos ?? config('mqtt.qos', 1));

        // ✅ keep running
        while (true) {
            $client->loop(true);
        }
    }

    // public function publish($topic, $data, $qos = null)
    // {
    //     $client = $this->connect();

    //     $payload = is_string($data) ? $data : json_encode($data);
    //     $client->publish($topic, $payload, $qos ?? config('mqtt.qos'));

    //     $client->disconnect();
    // }
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
}*/
