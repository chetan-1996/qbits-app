<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class GetInverterStatus extends Command
{
    protected $signature = 'getInverterStatus:cron';
    protected $description = 'Ultra-fast inverter status (Chunk + Pool + Retry)';

    public function handle()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        DB::disableQueryLog();

        $client = new Client([
            'verify'          => false,
            'timeout'         => 10,
            'connect_timeout' => 5,
        ]);

        $baseUrl = "https://www.aotaisolarcloud.com/solarweb/user/getPlantInfo";
        $date = now()->format('Y-m');

        $totalProcessed = 0;

        DB::table('clients')
            ->select('id', 'username', 'password')
            ->orderBy('id')
            ->chunk(200, function ($users) use ($client, $baseUrl, $date, &$totalProcessed) {

                $users = collect($users)->values(); // 🔥 FIX INDEX ISSUE

                $count = count($users);
                $concurrency = $this->calculateConcurrency($count);

                Log::info("⚡ Processing chunk of {$count} users with concurrency {$concurrency}");

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

                    'fulfilled' => function ($response, $index) use ($users, &$totalProcessed) {

                        $user = $users[$index] ?? null;

                        if (!$user) {
                            Log::error("❌ User index mismatch: {$index}");
                            return;
                        }

                        $json = json_decode($response->getBody(), true);

                        if (!isset($json['data'])) {
                            Log::warning("⚠️ Empty data user {$user->id}");
                            return;
                        }

                        $data = $json['data'];

                        DB::table('inverter_status')->updateOrInsert(
                            ['user_id' => $user->id],
                            [
                                'data'          => json_encode($json),
                                'all_plant'     => $data['plantCount'] ?? 0,
                                'normal_plant'  => $data['normalCount'] ?? 0,
                                'alarm_plant'   => ($data['faultCount'] ?? 0) + ($data['warnCount'] ?? 0),
                                'offline_plant' => ($data['offlineCount'] ?? 0) + ($data['jiansheCount'] ?? 0),
                                'power'         => $data['power'] ?? 0,
                                'capacity'      => $data['capacity'] ?? 0,
                                'day_power'     => $data['dayPower'] ?? 0,
                                'month_power'   => $data['monthPower'] ?? 0,
                                'total_power'   => $data['totalPower'] ?? 0,
                                'atun'          => $user->username,
                                'atpd'          => $user->password,
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ]
                        );

                        $totalProcessed++;
                    },

                    'rejected' => function ($reason, $index) use ($users, $baseUrl, $date, &$totalProcessed) {

                        $user = $users[$index] ?? null;

                        if (!$user) {
                            Log::error("❌ Reject index mismatch: {$index}");
                            return;
                        }

                        Log::error("❌ Failed user {$user->id}, retrying...");

                        // 🔁 RETRY ONCE
                        try {
                            $retryClient = new Client([
                                'verify'          => false,
                                'timeout'         => 8,
                                'connect_timeout' => 4,
                            ]);

                            $response = $retryClient->get($baseUrl, [
                                'query' => [
                                    'atun' => $user->username,
                                    'atpd' => $user->password,
                                    'date' => $date,
                                ]
                            ]);

                            $json = json_decode($response->getBody(), true);

                            if (!isset($json['data'])) {
                                return;
                            }

                            $data = $json['data'];

                            DB::table('inverter_status')->updateOrInsert(
                                ['user_id' => $user->id],
                                [
                                    'data'          => json_encode($json),
                                    'all_plant'     => $data['plantCount'] ?? 0,
                                    'normal_plant'  => $data['normalCount'] ?? 0,
                                    'alarm_plant'   => ($data['faultCount'] ?? 0) + ($data['warnCount'] ?? 0),
                                    'offline_plant' => ($data['offlineCount'] ?? 0) + ($data['jiansheCount'] ?? 0),
                                    'power'         => $data['power'] ?? 0,
                                    'capacity'      => $data['capacity'] ?? 0,
                                    'day_power'     => $data['dayPower'] ?? 0,
                                    'month_power'   => $data['monthPower'] ?? 0,
                                    'total_power'   => $data['totalPower'] ?? 0,
                                    'atun'          => $user->username,
                                    'atpd'          => $user->password,
                                    'created_at'    => now(),
                                    'updated_at'    => now(),
                                ]
                            );

                            $totalProcessed++;

                        } catch (RequestException $e) {
                            Log::error("❌ Retry failed user {$user->id}");
                        }
                    },
                ]);

                $pool->promise()->wait();

                Log::info("✅ Chunk done");
            });

        Log::info("🚀 CRON COMPLETED. Total processed: {$totalProcessed}");

        return Command::SUCCESS;
    }

    private function calculateConcurrency($count)
    {
        if ($count <= 200) return 10;
        if ($count <= 500) return 20;
        if ($count <= 1000) return 30;
        return 40;
    }
}
