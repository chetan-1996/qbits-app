<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Redis;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'MQTT subscriber with auto reconnect';

    public function handle()
    {
        $host = config('mqtt.host');
        $port = config('mqtt.port');
        $clientId = config('mqtt.client_id_prefix') . '_sub';

        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setKeepAliveInterval(20)                // keep-alive every 20 sec
            ->setReconnectAutomatically(true)         // auto reconnect
            ->setDelayBetweenReconnectAttempts(3)     // retry every 3 sec
            ->setMaxReconnectAttempts(0);             // infinite retries

        $client = new MqttClient($host, $port, $clientId);

        $client->connect($settings, true);

        // Subscribe to all inverters
        $client->subscribe('inverters/+/data', function ($topic, $message) {

            $data = json_encode([
                'topic' => $topic,
                'payload' => $message,
                'received_at' => now()->toISOString(),
            ]);

            Redis::rpush(config('mqtt.redis_queue_list'), $data);

        }, config('mqtt.qos'));

        // Main loop (handles reconnects)
        while (true) {
            try {
                $client->loop(true, 1);   // 1 second loop cycle
            } catch (\Throwable $e) {
                echo "Subscriber crashed: " . $e->getMessage() . "\n";
                sleep(3); // wait before reconnect
            }
        }
    }
}
