<?php
// app/Jobs/ProcessHeartbeat.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMqttHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 5;

    public function __construct(
        public string $topic,
        public string $message
    ) {}

    public function handle(): void
    {
        Log::info('[HB] job start', ['topic' => $this->topic, 'len' => strlen($this->message)]);

        $payload = json_decode($this->message, true);
        $payload = is_array($payload) ? $payload : [];

        // New heartbeat format: collector id comes from the topic
        // (rtsg-1/Ongridrooftop/{collector_id}/heartbeat/pub),
        // inverter id stays the payload IMEI.
        $topicParts  = explode('/', (string) $this->topic);
        $collectorId = $topicParts[2] ?? ($payload['IMEI'] ?? null);

        try {
            $id = DB::table('telemetry_heartbeat')->insertGetId([
                'collector_id' => $collectorId,
                'inverter_id'  => $payload['IMEI'] ?? null,
                'payload'      => $this->message,
                'created_at'   => now(),
            ]);
            Log::info('[HB] inserted', ['id' => $id, 'collector_id' => $collectorId]);
        } catch (\Throwable $e) {
            Log::error('[HB] insert failed', [
                'topic'        => $this->topic,
                'collector_id' => $collectorId,
                'error'        => $e->getMessage(),
            ]);
            throw $e;
        }

        // OTA support: keep per-unit last-seen and running firmware version.
        try {
            $imei = $payload['IMEI'] ?? null;
            if ($imei) {
                \App\Models\DongleState::recordSeen((string) $imei, \App\Support\Fwver::extract(is_array($payload) ? $payload : []));
            }
        } catch (\Throwable $e) {
            // never break heartbeat ingest on OTA bookkeeping
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessHeartbeat Job Failed', [
            'error'   => $e->getMessage(),
            'topic'   => $this->topic,
            'message' => $this->message,
        ]);
    }
}
