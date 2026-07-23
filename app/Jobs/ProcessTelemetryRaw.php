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

        $inverterIds = DB::table('inverters')
            ->where('plant_id', $plantId)
            ->value('id');

        // Fetch current month/year tracking values once per batch run
        $plantInfo = DB::table('plant_infos')
            ->where('atun', $this->client?->username ?? '')
            ->select('month_power', 'year_power', 'current_month', 'current_year', 'last_tkwh')
            ->first();

        $monthPower = $plantInfo->month_power ?? 0;
        $yearPower = $plantInfo->year_power ?? 0;
        $currentMonth = $plantInfo->current_month ?? null;
        $currentYear = $plantInfo->current_year ?? null;
        $lastTkwh = $plantInfo->last_tkwh ?? null;

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
            $tkwhRecords = [];

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
                        'record_time'      => isset($payload['TIMESTAMP']) ? substr($payload['TIMESTAMP'], 11, 8) : null,
                        'record_datetime' => $payload['TIMESTAMP'] ?? null,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];

                    $tkwh = isset($payload['IS-1-0---TKWH']) ? round((float) $payload['IS-1-0---TKWH'], 4) : null;
                    $lkwh = isset($payload['IS-1-0---LKWH']) ? round((float) $payload['IS-1-0---LKWH'], 4) : null;

                    if (!empty($payload['TIMESTAMP']) && $tkwh !== null) {
                        $tkwhRecords[] = [
                            'plant_id'     => $plantId,
                            'collector_id' => $this->collectorId,
                            'record_date'  => substr($payload['TIMESTAMP'], 0, 10),
                            'user_id'      => $this->client?->id ?? 0,
                            'atun'         => $this->client?->username ?? '',
                            'atpd'         => $this->client?->password ?? '',
                            'tkwh'         => $tkwh,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                    }

                    // --- month/year reset & accumulation ---
                    $recordTimestamp = $payload['TIMESTAMP'] ?? null;
                    $recordMonth = $recordTimestamp ? date('Y-m', strtotime($recordTimestamp)) : null;
                    $recordYear  = $recordTimestamp ? date('Y',  strtotime($recordTimestamp)) : null;

                    if ($recordMonth && $currentMonth !== $recordMonth) {
                        $monthPower = 0;
                        $currentMonth = $recordMonth;
                    }
                    if ($recordYear && $currentYear !== $recordYear) {
                        $yearPower = 0;
                        $currentYear = $recordYear;
                    }

                    if ($tkwh !== null) {
                        Log::info('Telemetry accumulation debug', [
                            'collector_id' => $this->collectorId,
                            'tkwh' => $tkwh,
                            'last_tkwh' => $lastTkwh,
                            'record_month' => $recordMonth,
                            'record_year' => $recordYear,
                            'current_month' => $currentMonth,
                            'current_year' => $currentYear,
                            'month_power_before' => $monthPower,
                            'year_power_before' => $yearPower,
                        ]);

                        if ($lastTkwh !== null) {
                            $increment = $tkwh - $lastTkwh;
                            if ($increment < 0) {
                                // Meter reset or rollover: treat full current value as increment
                                $increment = $tkwh;
                            }
                            $monthPower += $increment;
                            $yearPower  += $increment;

                            Log::info('Telemetry increment applied', [
                                'increment' => $increment,
                                'month_power_after' => $monthPower,
                                'year_power_after' => $yearPower,
                            ]);
                        } else {
                            Log::info('Telemetry first baseline: last_tkwh is null, skipping accumulation');
                        }
                        $lastTkwh = $tkwh;
                    } else {
                        Log::info('Telemetry tkwh is null, skipping accumulation');
                    }
                    // ----------------------------------------

                    DB::table('plant_infos')->where('atun', $this->client?->username ?? '')->update([
                        'eday' => $tkwh,
                        'etot' => $lkwh,
                        'capacity' => $inverterTypeNumeric,
                        'acpower' => $powClean,
                        'month_power' => $monthPower,
                        'year_power'  => $yearPower,
                        'current_month' => $currentMonth,
                        'current_year'  => $currentYear,
                        'last_tkwh'     => $lastTkwh,
                    ]);

                    $a = DB::table('inverter_details')->updateOrInsert(
                        [
                            'inverterId' => $inverterIds
                        ],
                        [
                            'recordTime'        => $payload['TIMESTAMP'],
                            'recordDate' => isset($payload['TIMESTAMP'])? date('Y-m-d', strtotime($payload['TIMESTAMP'])): null,
                            'inverterState'     => 1,
                            'acVoltage'       => $payload['IS-1-0---RPHV'],
                            'acFrequency'     => $payload['IS-1-0---FREQ'],
                            'acMomentaryPower'     => $powClean,
                            'dayPowerLower'     => $tkwh,
                            'totalPowerLower'  => $lkwh,
                            'plantId' => $plantId,
                            'temperature'       => $payload['IS-1-0---TEMP'],
                            'user_id'       => $this->client?->id ?? 0,
                            'atun'       => $this->client?->username ?? '',
                            'atpd'       => $this->client?->password ?? '',
                            'server_flag'       => 1,
                            'updated_at'        => now(),
                            'created_at'        => now(), // Only used on insert
                        ]
                    );
                    Log::info('inverter_details', ['result' => $a]);

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

            if (!empty($tkwhRecords)) {
                DB::table('telemetry_daily_tkwh')->upsert(
                    $tkwhRecords,
                    ['collector_id', 'record_date'],
                    ['tkwh', 'updated_at']
                );
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
