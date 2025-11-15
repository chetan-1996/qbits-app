<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

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

            Log::info("MQTT Message Received RE", [
                'topic' => $topic,
                'payload' => $message,
                'received_at' => now()->toISOString()
            ]);

            Redis::rpush(config('mqtt.redis_queue_list'), $item);

        }, config('mqtt.qos'));

        $client->loop(true);
    }
}

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use PhpMqtt\Client\MqttClient;
// use PhpMqtt\Client\ConnectionSettings;
// use Illuminate\Support\Facades\Log;

// class MqttSubscribe extends Command
// {
//     protected $signature = 'mqtt:subscribe';
//     protected $description = 'Subscribe and process inverter data once (no Redis)';

//     public function handle()
//     {
//         $client = new MqttClient(
//             config('mqtt.host'),
//             config('mqtt.port'),
//             config('mqtt.client_id_prefix') . '_sub_once'
//         );

//         $settings = (new ConnectionSettings)
//             ->setUsername(config('mqtt.username'))
//             ->setPassword(config('mqtt.password'))
//             ->setKeepAliveInterval(config('mqtt.keep_alive'));

//         $client->connect($settings, true);

//         // Subscribe to all inverter topics once
//         $client->subscribe('inverters/+/data', function ($topic, $message) {

//             // 👉 Handle message here
//             Log::info("MQTT Message Received", [
//                 'topic' => $topic,
//                 'payload' => $message,
//                 'received_at' => now()->toISOString()
//             ]);

//             // TODO: Save directly to DB if needed
//             // DB::table('inverters_data')->insert([
//             //     'topic' => $topic,
//             //     'payload' => $message,
//             //     'created_at' => now(),
//             // ]);

//         }, config('mqtt.qos'));

//         // 👉 Listen for messages for 2 seconds then exit
//         $client->loop(true, 2);

//         // Disconnect cleanly
//         $client->disconnect();

//         $this->info("MQTT subscribed once and processed message.");

//         return Command::SUCCESS;
//     }
// }
