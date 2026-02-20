<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use Illuminate\Support\Facades\DB;

class MqttTelemetryListener extends Command
{
    protected $signature = 'mqtt:telemetry-listen';
    protected $description = 'Listen telemetry from dongles';

    public function handle()
    {
        DB::disableQueryLog();
        $mqtt = new MqttService();
        $mqtt->connect(config('mqtt.client_id_prefix') .'-telemetry-sub');

        $mqtt->subscribe('heaven/devices/+/telemetry', function ($topic, $message) {

            $payload = json_decode($message, true);

            DB::table('telemetry_raw')->insert([
                'collector_id' => $payload['cid'] ?? null,
                'inverter_id'  => $payload['inv_id'] ?? null,
                'payload'      => $message,
                'created_at'   => now(),
            ]);

            $this->info("TELEMETRY: $topic");
        });
    }
}
