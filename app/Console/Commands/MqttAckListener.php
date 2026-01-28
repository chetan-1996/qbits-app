<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use Illuminate\Support\Facades\DB;

class MqttAckListener extends Command
{
    protected $signature = 'mqtt:ack-listen';
    protected $description = 'Listen ACKs from dongles';

    public function handle()
    {
        $this->info("ACK:");
        $mqtt = new MqttService();
        $mqtt->connect(config('mqtt.client_id_prefix') .'-ack-sub');

        $mqtt->subscribe('heaven/devices/+/ack', function ($topic, $message) {

            $payload = json_decode($message, true);

            DB::table('device_ack')->insert([
                'collector_id' => $payload['cid'] ?? null,
                'inverter_id'  => $payload['inv_id'] ?? null,
                'p_type'         => $payload['p_type'],
                'ref_id'       => $payload['ref_id'],
                'status'       => $payload['status'],
                'f_reason'       => $payload['f_reason'],
                'created_at'   => now(),
            ]);

            $this->info("ACK: $topic");
        });
    }
}
