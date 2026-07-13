<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Jobs\ProcessMqttModbusWrite;

class MqttModbusWriteListener extends Command
{
    protected $signature = 'mqtt:modbus-write-listen {--queue=default}';
    protected $description = 'Listen modbus write responses from dongles and dispatch to queue';

    public function handle()
    {
        $mqtt = new MqttService();
        $mqtt->connect(config('mqtt.client_id_prefix') . '-modbus-write-sub');

        $this->info('MQTT Modbus Write Listener started - dispatching to queue');

        $mqtt->subscribe('rtsg-1/Ongridrooftop/+/modbus_write/pub', function ($topic, $message) {
            ProcessMqttModbusWrite::dispatch($topic, $message)->onQueue('modbuswrite');
        });

        $mqtt->loop(true);
    }
}
