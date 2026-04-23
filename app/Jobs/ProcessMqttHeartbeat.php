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
        $payload = json_decode($this->message, true);

        DB::table('telemetry_heartbeat')->insert([
            'collector_id' => $payload['IMEI'] ?? null,
            'inverter_id'  => $payload['IMEI'] ?? null,
            'payload'      => $this->message,
            'created_at'   => now(),
        ]);
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
