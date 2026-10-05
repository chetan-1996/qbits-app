<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

/**
 * MQTT client for the OTA subsystem. Holds one persistent connection so the
 * dispatcher can publish paced commands without reconnecting per unit (MO-14).
 * Kept separate from MqttService, whose publish() disconnects every call.
 *
 * All OTA traffic is QoS 0, retain off — the broker gives no delivery
 * confirmation and a retained command would re-fire on every reconnect (MO-14).
 */
class OtaMqttService
{
    private ?MqttClient $client = null;
    private string $clientId = '';

    public function connect(string $clientId): void
    {
        $this->clientId = $clientId;

        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setKeepAliveInterval(config('mqtt.keep_alive', 60));

        $this->client = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            $clientId
        );

        $this->client->connect($settings, true);
    }

    public function reconnect(): void
    {
        try {
            $this->client?->disconnect();
        } catch (\Throwable) {
            // already gone
        }
        $this->connect($this->clientId);
    }

    /**
     * Publish an OTA command: QoS 0, retain false (MO-14).
     */
    public function publishCommand(string $topic, string $payload): void
    {
        $this->client->publish($topic, $payload, MqttClient::QOS_AT_MOST_ONCE, false);
    }

    /**
     * Subscribe at QoS 0. The callback receives ($topic, $message).
     */
    public function subscribe(string $topic, callable $callback): void
    {
        $this->client->subscribe($topic, function (string $t, string $message) use ($callback) {
            $callback($t, $message);
        }, MqttClient::QOS_AT_MOST_ONCE);
    }

    public function loop(bool $blocking = true): void
    {
        $this->client->loop($blocking);
    }

    public function disconnect(): void
    {
        try {
            $this->client?->disconnect();
        } catch (\Throwable $e) {
            Log::debug('OTA MQTT disconnect failed', ['error' => $e->getMessage()]);
        }
    }
}
