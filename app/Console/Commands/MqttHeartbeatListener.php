<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Jobs\ProcessMqttHeartbeat;

class MqttHeartbeatListener extends Command
{
    protected $signature = 'mqtt:heartbeat-listen {--queue=default}';
    protected $description = 'Listen heartbeat from dongles and dispatch to queue';

    public function handle()
    {
        $mqtt = new MqttService();
        $mqtt->connect(config('mqtt.client_id_prefix') . '-heartbeat-sub');

        $this->info('MQTT Heartbeat Listener started - dispatching to queue');

        $mqtt->subscribe('rtsg-1/Ongridrooftop/+/heartbeat/pub', function ($topic, $message) {
            // Fast dispatch - minimal processing in listener
            ProcessMqttHeartbeat::dispatch($message)->onQueue('heartbeat');
        });

        // Keep loop running
        $mqtt->loop(true);
    }
}
