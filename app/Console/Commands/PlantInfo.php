<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class PlantInfo extends Command
{
    protected $signature = 'plantInfo:cron';
    protected $description = 'Fetch plant information and process it safely.';

    public function handle()
    {
        $this->info("=== Plant Info Command Started ===");

        $curTime = date('Y-m');  // faster than now()->format()
        $now = date('Y-m-d H:i:s'); // use once

        $http = Http::timeout(15)     // create once (saves CPU)
            ->retry(2, 150)
            ->withOptions(['verify' => false]);

        DB::table('clients')
            ->select('id', 'username', 'password') // reduce memory
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($curTime, $http, $now) {

                foreach ($users as $user) {

                    try {
                        $response = $http->get(
                            'https://www.aotaisolarcloud.com/solarweb/plant/getRankByPageWithSize',
                            [
                                'page'     => 0,
                                'pageSize' => 200,
                                'atun'     => $user->username,
                                'atpd'     => $user->password,
                                'date'     => $curTime,
                                'param'    => 'power',
                                'flag'     => true,
                            ]
                        );

                        if ($response->failed()) {
                            Log::warning("PlantInfo API Failed: User {$user->id}");
                            continue;
                        }

                        $data = $response->json();

                        // skip if empty list
                        if (empty($data['list'])) {
                            continue;
                        }

                        foreach ($data['list'] as $item) {

                            $p = $item['plantInfo'];

                            // pre-encode once
                            $json = json_encode($item, JSON_UNESCAPED_UNICODE);

                            // avoid updating unchanged heavy fields
                            $updateData = [
                                'date'      => $item['date'],
                                'atun'      => $user->username,
                                'atpd'      => $user->password,

                                'plant_name'    => $p['plantName'],
                                'is_room'       => (int)$p['isRoom'],
                                'is_enviroment' => (int)$p['isEnviroment'],
                                'is_blackflow'  => (int)$p['isBlackflow'],

                                'elec_subsidy_price'    => $p['elecSubsidyPrice'],
                                'internet_power_price'  => $p['internetPowerPrice'],
                                'own_power_price'       => $p['ownPowerPrice'],

                                'internet_power_occupy' => $p['internetPowerOccupy'],
                                'own_power_occupy'      => $p['ownPowerOccupy'],

                                'remark1' => $p['remark1'],
                                'remark2' => $p['remark2'],
                                'remark3' => $p['remark3'],

                                'plant_user' => $p['plantUser'],
                                'acpower'    => $p['acpower'] ?? 0,
                                'eday'       => $p['eday'] ?? 0,
                                'etot'       => $p['etot'] ?? 0,

                                'plantstate' => $p['plantstate'],
                                'planttype'  => $p['planttype'],

                                'record_time' => $p['recordTime'],

                                'capacity'        => $p['capacity'],
                                'capacitybattery' => $p['capacitybattery'],

                                'country'  => $p['country'],
                                'province' => $p['province'],
                                'city'     => $p['city'],
                                'district' => $p['district'],

                                // summary
                                'month_power' => $item['monthPower'],
                                'year_power'  => $item['yearPower'],
                                'power_rate'  => $item['powerRate'],
                                'kpi'         => $item['kpi'],
                                'watch'       => (int)$item['watch'],
                                'time'        => ($item['time'] !== "*") ? $item['time'] : null,

                                'full_response' => $json,
                                'updated_at'    => $now,
                            ];

                            // run DB write (cannot batch because updateOrInsert is row-specific)
                            DB::table('plant_infos')->updateOrInsert(
                                [
                                    'user_id'  => $user->id,
                                    'plant_no' => $p['plantNo'],
                                ],
                                $updateData
                            );
                        }

                        Log::info("Processed user {$user->id}, plants: " . count($data['list']));

                    } catch (\Throwable $e) {
                        Log::error("PlantInfo Error User {$user->id}: " . $e->getMessage());
                        continue;
                    }
                }
            });

        $this->info("=== Plant Info Command Completed ===");

        return Command::SUCCESS;
    }
}
