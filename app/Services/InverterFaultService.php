<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InverterFaultService
{
    protected $url = "https://www.aotaisolarcloud.com/solarweb/inverterWarn/getPlantByPage";

    public function sync()
    {
        try {
            DB::table('plant_infos')
                ->select('plant_no', 'atun', 'atpd','user_id')
                ->orderBy('id')
                ->chunk(50, function ($plants) {

                    foreach ($plants as $plant) {

                        $response = Http::withOptions([
                            'verify' => false
                        ])->get($this->url, [
                            'page' => 0,
                            'pid'  => $plant->plant_no,
                            'type' => -1,
                            'atun' => $plant->atun,
                            'atpd' => $plant->atpd,
                        ]);

                        if (!$response->successful()) {
                            Log::warning("Fault API failed", [
                                'plant_no' => $plant->plant_no,
                                'body' => $response->body()
                            ]);
                            unset($response);
                            gc_collect_cycles();
                            continue;
                        }

                        // Decode only once → much lighter than collect()
                        $data = json_decode($response->body(), true);
                        unset($response);

                        if (!isset($data['list']) || empty($data['list'])) {
                            unset($data);
                            gc_collect_cycles();
                            continue;
                        }

                        foreach ($data['list'] as $row) {

                            // Duplicate check
                            $exists = DB::table('inverter_faults')
                                ->where('inverter_id', $row['inverterId'])
                                ->where('stime', $row['stime'])
                                ->exists();

                            if ($exists) {
                                continue;
                            }

                            DB::table('inverter_faults')->insert([
                                'inverter_id' => $row['inverterId'],
                                'plant_id'    => $row['plantId'],
                                'status'      => $row['status'],
                                'inverter_sn' => $row['inverterSn'] ?? null,
                                'stime'       => $row['stime'],
                                'etime'       => $row['etime'] ?? null,
                                'meta'        => json_encode($row['meta'] ?? []),
                                'message_cn'  => json_encode($row['messagecn'] ?? []),
                                'message_en'  => json_encode($row['messageen'] ?? []),
                                'atun'        => $plant->atun,
                                'atpd'        => $plant->atpd,
                                'user_id'     => $plant->user_id,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]);

                            // Free memory inside loop
                            unset($exists);
                        }

                        // Free API data memory
                        unset($data);
                        gc_collect_cycles();
                    }

                    // Free chunk memory
                    unset($plants);
                    gc_collect_cycles();
                });

            return true;

        } catch (\Throwable $e) {
            Log::error("Fault Sync ERROR: " . $e->getMessage());
            return false;
        }
    }
}
