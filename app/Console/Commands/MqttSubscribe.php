<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Redis;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'Subscribe to all inverter data and queue in Redis';

    public function handle()
    {
        $client = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id_prefix') . '_sub'
        );

        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setKeepAliveInterval(config('mqtt.keep_alive'));

        $client->connect($settings, true);

        // Wildcard subscription
        $client->subscribe('inverters/+/data', function ($topic, $message) {

            $item = json_encode([
                'topic' => $topic,
                'payload' => $message,
                'received_at' => now()->toISOString(),
            ]);

            Redis::rpush(config('mqtt.redis_queue_list'), $item);

        }, config('mqtt.qos'));

        $client->loop(true);
    }
}
