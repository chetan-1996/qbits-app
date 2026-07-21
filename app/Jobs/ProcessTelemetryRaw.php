<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTelemetryRaw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 5;

    public function __construct(
        public string $collectorId,
        public ?object $client = null,
        public int $batchSize = 500
    ) {}

    public function handle(): void
    {
        DB::disableQueryLog();

        $processed = 0;
        $failed = 0;
        $logRecords = [];

        $plantId = DB::table('plant_infos')
            ->where('atun', $this->client?->username ?? '')
            ->value('id');

        do {
            $records = DB::table('telemetry_raw')
                ->where('collector_id', $this->collectorId)
                ->whereNull('processed_at')
                ->orderBy('id')
                ->limit($this->batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            $processedIds = [];
            $powRecords = [];

            foreach ($records as $record) {
                try {
                    $payload = json_decode($record->payload, true);
                    $inverterId = $record->inverter_id ?? $this->collectorId;

                    $inverterType = $this->client?->inverter_type ?? '';
                    $inverterTypeNumeric = null;
                    if (preg_match('/(\d+(?:\.\d+)?)/', $inverterType, $matches)) {
                        $inverterTypeNumeric = $matches[1];
                    }

                    $logRecords[] = [
                        'company_name' => $this->client?->company_name ?? $this->client?->plant_name ?? 'N/A',
                        'username' => $this->client?->username ?? 'N/A',
                        'password' => $this->client?->password ?? 'N/A',
                        'inverter_id' => $inverterId,
                        'inverter_type_numeric' => $inverterTypeNumeric,
                        'tkwh' => $payload['IS-1-0---TKWH'] ?? null,
                        // 'payload'     => $record->payload,
                        'received_at' => $record->created_at,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
 
                    $processedIds[] = $record->id;

                    $powRaw = $payload['IS-1-0---POW'] ?? null;
                    $powClean = $powRaw !== null ? round((float) $powRaw, 4) : null;

                    $powRecords[] = [
                        'plant_id'        => $plantId,
                        'collector_id'    => $this->collectorId,
                        'user_id'         => $this->client?->id ?? 0,
                        'atun'            => $this->client?->username ?? '',
                        'atpd'            => $this->client?->password ?? '',
                        'pow'             => $powClean,
                        'record_datetime' => $payload['TIMESTAMP'] ?? null,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];

                    $tkwh = isset($payload['IS-1-0---TKWH']) ? round((float) $payload['IS-1-0---TKWH'], 4) : null;
                    $lkwh = isset($payload['IS-1-0---LKWH']) ? round((float) $payload['IS-1-0---LKWH'], 4) : null;

                    DB::table('plant_infos')->where('atun', $this->client->username)->update([
                        'eday' => $tkwh,
                        'etot' => $lkwh,
                        'capacity' => $inverterTypeNumeric,
                        'acpower' => $powClean,
                    ]);

                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('Telemetry raw parse failed', [
                        'id'      => $record->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($powRecords)) {
                DB::table('telemetry_pow')->insert($powRecords);
            }

            // if (!empty($logRecords)) {
            //     DB::table('inverter_logs')->insert($logRecords);

            //     foreach ($logRecords as $r) {
            //         DB::table('inverter_latest')->updateOrInsert(
            //             ['inverter_id' => $r['inverter_id']],
            //             ['latest_payload' => $r['payload'], 'updated_at' => now()]
            //         );
            //     }
            // }

            if (!empty($processedIds)) {
                DB::table('telemetry_raw')
                    ->whereIn('id', $processedIds)
                    ->update(['processed_at' => now()]);
            }

            $processed += count($processedIds);

        } while ($records->count() === $this->batchSize);

        Log::info('Telemetry raw processed', [
            'collector_id' => $this->collectorId,
            'client'       => $this->client?->company_name ?? $this->client?->plant_name ?? 'N/A',
            'processed'    => $processed,
            "data" => $logRecords,
            'failed'       => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessTelemetryRaw Job Failed', [
            'error'        => $e->getMessage(),
            'collector_id' => $this->collectorId,
        ]);
    }
}
