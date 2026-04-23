<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use Illuminate\Support\Facades\DB;

class MqttHeartbeatListener extends Command
{
    protected $signature = 'mqtt:heartbeat-listen';
    protected $description = 'Listen heartbeat from dongles';

    public function handle()
    {
        DB::disableQueryLog();

        $mqtt = new MqttService();
        $mqtt->connect(config('mqtt.client_id_prefix') . '-heartbeat-sub');

        $mqtt->subscribe('rtsg-1/Ongridrooftop/+/heartbeat/pub', function ($topic, $message) {

            try {
                $payload = json_decode($message, true);

                DB::table('telemetry_heartbeat')->insert([
                    'collector_id' => $payload['IMEI'] ?? null,
                    'inverter_id'  => $payload['IMEI'] ?? null,
                    'payload'      => $message,
                    'created_at'   => now(),
                ]);

                // Optional: lightweight logging (avoid heavy logs)
                // $this->info("HEARTBEAT: $topic");

            } catch (\Throwable $e) {
                \Log::error('Heartbeat Insert Error', [
                    'error' => $e->getMessage(),
                    'topic' => $topic,
                    'message' => $message
                ]);
            }
        });

        // IMPORTANT: keep loop running
        $mqtt->loop(true);
    }
}
