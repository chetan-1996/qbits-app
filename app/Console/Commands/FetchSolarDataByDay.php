<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\SolarPowerLog;

class FetchSolarDataByDay extends Command
{
    protected $signature   = 'solar:fetch-byday {date?}';
    protected $description = 'Fetch solar power data and store without duplicates';

    public function handle(): void
    {
        // Configure your plants here (or load from DB/config)
        // $plants = [
        //     [
        //         'plantId' => 12345,
        //         'atun'    => 'your_atun',
        //         'atpd'    => 'your_atpd',
        //     ],
        // ];
        $today = $this->argument('date')
        ? \Carbon\Carbon::parse($this->argument('date'))->toDateString()
        : now()->toDateString();
//  $today = now()->toDateString(); // e.g. "2025-04-09"
//  $today =  "2025-04-08"; // e.g. "2025-04-09"
       \DB::table('inverter_details')
    ->select('plantId', 'user_id', 'atun', 'atpd')
    ->whereNotNull('plantId')
    ->chunkById(50, function ($plants) use ($today) {

        foreach ($plants as $plant) {
            $this->fetchAndStore((array) $plant, $today);
        }

    }, 'plantId'); // 👈 important


        // foreach ($plants as $plant) {
        //     $this->fetchAndStore($plant, $today);
        // }
    }

    private function fetchAndStore(array $plant, string $date): void
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(20)
                ->get(
                    'https://www.aotaisolarcloud.com/ATSolarInfo/appcanPlantStatisticsByDay.action',
                    [
                        'startTime' => $date,
                        'plantId'   => $plant['plantId'],
                        'atun'      => $plant['atun'],
                        'atpd'      => $plant['atpd'],
                    ]
                );

            if (!$response->successful()) {
                $this->error("API failed for plant {$plant['plantId']}");
                return;
            }

            $json    = $response->json();
            // $byday   = $json['data']['byday'] ?? [];
            $records = $json['catisticsDataByDayList'] ?? [];
            $eday    = $json['eday'] ?? null;

            // Deduplicate: keep only first occurrence of each HH:MM (strip seconds)
            $seen = [];
            $upsertRows = [];

            // foreach ($records as $record) {
            //     // Normalize to HH:MM:00 to group near-duplicate pairs
            //     $slotTime = substr($record['recordTime'], 0, 5) . ':00';

            //     if (isset($seen[$slotTime])) {
            //         continue; // skip duplicate
            //     }

            //     $seen[$slotTime] = true;

            //     $upsertRows[] = [
            //         'plant_id'           => $plant['plantId'],
            //         'record_date'        => $date,
            //         'record_time'        => $slotTime,
            //         'ac_momentary_power' => (float) $record['acMomentaryPower'],
            //         'irradiation'        => (int) $record['irradiation'],
            //         'eday'               => $eday,
            //         'created_at'         => now(),
            //         'updated_at'         => now(),
            //     ];
            // }

            // SolarPowerLog::upsert(
            //     $upsertRows,
            //     ['plant_id', 'record_date', 'record_time'],  // unique keys
            //     ['ac_momentary_power', 'irradiation', 'eday', 'updated_at']  // update if exists
            // );

                $upsertRows[] = [
                    'plant_id'           => $plant['plantId'],
                    'atun'               => $plant['atun'],
                    'atpd'               => $plant['atpd'],
                    'user_id'            => $plant['user_id'],
                    'record_date'        => $date,
                    'record_time'        => now()->format('H:i:s'),
                    'ac_momentary_power' => (float) 0.00,
                    'irradiation'        => (int) 0,
                    'eday'               => $eday,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];

            // Upsert: insert new, update existing (no duplicates ever)
            SolarPowerLog::upsert(
                $upsertRows,
                ['plant_id', 'record_date'],  // unique keys
                ['ac_momentary_power', 'irradiation', 'eday', 'updated_at','user_id','atun','atpd']  // update if exists
            );

            $this->info("Plant {$plant['plantId']}: stored " . count($upsertRows) . ' records');

        } catch (\Throwable $e) {
            $this->error("Plant {$plant['plantId']} error: {$e->getMessage()}");
        }
    }
}
