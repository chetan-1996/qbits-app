<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class GetInverterStatus extends Command
{
    protected $signature = 'getInverterStatus:cron';
    protected $description = 'Ultra-fast inverter status via Guzzle Pool (Laravel-native)';

    public function handle()
    {
        $users = DB::table('clients')
            ->select('id', 'username', 'password')
            ->orderBy('id')
            ->get();

        $client = new Client([
            'verify'  => false,  // SSL fix
            'timeout' => 15,
        ]);

        $baseUrl = "https://www.aotaisolarcloud.com/solarweb/user/getPlantInfo";
        $date = now()->format('Y-m');

        $concurrency = $this->calculateConcurrency(count($users));

        $requests = function () use ($users, $baseUrl, $date) {
            foreach ($users as $user) {
                yield new Request('GET', $baseUrl . '?' . http_build_query([
                    'atun' => $user->username,
                    'atpd' => $user->password,
                    'date' => $date,
                ]));
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,

            'fulfilled' => function ($response, $index) use ($users) {
                $user = $users[$index];
                $json = json_decode($response->getBody(), true);

                if (!isset($json['data']) || empty($json['data'])) {
                    Log::warning("⚠️ Empty inverter data for user {$user->id}");
                    return;
                }

                $allPlant     = $json['data']['plantCount'] ?? 0;
                $normalPlant  = $json['data']['normalCount'] ?? 0;
                $alarmPlant   = ($json['data']['faultCount'] ?? 0) + ($json['data']['warnCount'] ?? 0);
                $offlinePlant = ($json['data']['offlineCount'] ?? 0) + ($json['data']['jiansheCount'] ?? 0);

                DB::table('inverter_status')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'data'          => json_encode($json),
                        'all_plant'     => $allPlant,
                        'normal_plant'  => $normalPlant,
                        'alarm_plant'   => $alarmPlant,
                        'offline_plant' => $offlinePlant,
                        'power'         => $json['data']['power'] ?? 0,
                        'capacity'      => $json['data']['capacity'] ?? 0,
                        'day_power'     => $json['data']['dayPower'] ?? 0,
                        'month_power'   => $json['data']['monthPower'] ?? 0,
                        'total_power'   => $json['data']['totalPower'] ?? 0,
                        'atun'          => $user->username,
                        'atpd'          => $user->password,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]
                );

                // DB::table('inverter_status')->updateOrInsert(
                //     ['user_id' => $user->id],
                //     [
                //         'data' => json_encode($json),
                //         'all_plant' => $user->username,
                //         'normal_plant' => $user->username,
                //         'alarm_plant' => $user->username,
                //         'offline_plant' => $user->username,
                //         'atun' => $user->username,
                //         'atpd' => $user->password,
                //         'created_at' => now(),
                //         'updated_at' => now(),
                //     ]
                // );

                Log::info("✔ Inverter updated for user {$user->id}");
            },

            'rejected' => function ($reason, $index) use ($users) {
                $user = $users[$index];
                Log::error("❌ Inverter fetch failed for user {$user->id}: " . $reason);
            },
        ]);

        // Execute concurrent batch
        $pool->promise()->wait();

        Log::info("🚀 Laravel-native parallel cron completed with {$concurrency} threads.");

        return Command::SUCCESS;
    }

    private function calculateConcurrency($count)
    {
        if ($count <= 500) return 20;
        if ($count <= 2000) return 50;
        if ($count <= 5000) return 100;
        return 150; // Laravel can easily support this in CLI
    }
}
