<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class ProcessInverterBatch implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $batchSize = 500;

    public function handle()
    {
        $list = config('mqtt.redis_queue_list');
        $records = [];

        for ($i = 0; $i < $this->batchSize; $i++) {
            $raw = Redis::lpop($list);
            if (!$raw) break;

            $data = json_decode($raw, true);
            $parts = explode('/', $data['topic']);

            $records[] = [
                'inverter_id' => $parts[1] ?? 'unknown',
                'payload' => $data['payload'],
                'received_at' => $data['received_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($records)) {
            DB::table('inverter_logs')->insert($records);

            foreach ($records as $r) {
                DB::table('inverter_latest')->updateOrInsert(
                    ['inverter_id' => $r['inverter_id']],
                    ['latest_payload' => $r['payload'], 'updated_at' => now()]
                );
            }
        }

        if (Redis::llen($list) > 0) {
            dispatch(new self());
        }
    }
}
