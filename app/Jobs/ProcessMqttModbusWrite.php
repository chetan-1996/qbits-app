<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMqttModbusWrite implements ShouldQueue
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

        if (!is_array($payload) || empty($payload['IMEI'])) {
            Log::warning('Invalid modbus write payload skipped', [
                'topic' => $this->topic,
                'json_error' => json_last_error_msg(),
            ]);
            return;
        }

        DB::table('modbus_write_logs')->insert([
            'collector_id' => $payload['IMEI'],
            'topic'        => $this->topic,
            'payload'      => $this->message,
            'created_at'   => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessMqttModbusWrite Job Failed', [
            'error'   => $e->getMessage(),
            'topic'   => $this->topic,
            'message' => $this->message,
        ]);
    }
}
