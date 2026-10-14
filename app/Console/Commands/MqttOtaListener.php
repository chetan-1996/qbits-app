<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOtaStatus;
use App\Services\OtaMqttService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Subscribe to rtsg-1/Ongridrooftop/+/ota/pub (MO-20) and hand every status
 * message to the queue. Must be subscribed before the first send; run under
 * supervisor next to the other mqtt:*-listen commands.
 */
class MqttOtaListener extends Command
{
    protected $signature = 'mqtt:ota-listen';
    protected $description = 'Listen for OTA status messages from dongles';

    public function handle()
    {
        $topic = config('ota.topic_prefix') . '/+/ota/pub';

        while (true) {
            try {
                $mqtt = app(OtaMqttService::class);
                // Unique client id per process: a duplicate id lets the broker
                // kick the supervised copy (they disconnect each other forever).
                $mqtt->connect(config('mqtt.client_id_prefix') . '-ota-sub-' . getmypid());

                $this->info("OTA status listener started on {$topic}");

                $mqtt->subscribe($topic, function ($t, $message) {
                    ProcessOtaStatus::dispatch($t, $message, now()->toDateTimeString())
                        ->onQueue('ota');
                });

                $mqtt->loop(true);
            } catch (\Throwable $e) {
                Log::error('OTA listener lost connection, retrying', ['error' => $e->getMessage()]);
                $this->error('Connection lost: ' . $e->getMessage());
                sleep(5);
            }
        }
    }
}
