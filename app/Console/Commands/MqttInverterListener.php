<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MqttInverterListener extends Command
{
    protected $signature = 'mqtt:inverter-listen';
    protected $description = 'Listen inverter MQTT data';

    public function handle()
    {
        DB::disableQueryLog();
        $mqtt = new MqttService();
        $mqtt->connect(config('mqtt.client_id_prefix') . '-subscriber');

        $mqtt->subscribe('inverter/+/data', function ($topic, $message) {

            $data = json_decode($message, true);

            DB::table('inverters_logs')->insert([
                'topic' => $topic,
                'data' => $message,
                'created_at' => now()
            ]);

            $this->info("DATA [$topic] $message");
        });
    }
}
