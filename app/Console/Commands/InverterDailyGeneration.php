<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InverterDailyGeneration extends Command
{
    protected $signature = 'inverterDailyGeneration:cron';
    protected $description = 'Daily Generation WhatsApp Report (Ultra Optimized)';

    public function handle()
    {
        DB::disableQueryLog();

        $today        = date('Y-m-d');
        $todayMonth   = date('Y-m');
        $todayDisplay = date('d M Y');
        $updatedIds   = [];

        /* ---------------- HTTP CLIENTS ---------------- */

        $solarClient = Http::withOptions([
            'verify' => false,
            'timeout' => 12,
        ])->withHeaders([
            'Connection' => 'keep-alive',
        ]);

        $waClient = Http::withOptions([
            'verify' => false,
            'timeout' => 8,
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ]);

        /* ---------------- STREAM USERS ---------------- */

        DB::table('clients')
            // ->whereIn('id', [12, 20])
            ->where('daily_generation_report_flag', 1)
            ->whereNotNull('phone')
            ->where(function ($q) use ($today) {
                $q->whereNull('daily_report_sent_at')
                  ->orWhereDate('daily_report_sent_at', '!=', $today);
            })
            ->select([
                'id',
                'username',
                'password',
                'phone',
                'weekly_generation_report_flag'
            ])
            ->orderBy('id')
            ->limit(20)
            ->lazyById(50)
            ->each(function ($user) use (
                $solarClient,
                $waClient,
                $today,
                $todayMonth,
                $todayDisplay,
                &$updatedIds
            ) {

                if ($this->processUser(
                    $user,
                    $solarClient,
                    $waClient,
                    $todayMonth,
                    $todayDisplay
                )) {
                    $updatedIds[] = $user->id;
                }

                // HARD GUARD
                if (memory_get_usage(true) > 150 * 1024 * 1024) {
                    Log::warning('DailyGeneration: Memory threshold hit');
                    return false;
                }
            });

        /* ---------------- BATCH UPDATE ---------------- */

        if ($updatedIds) {
            DB::table('clients')
                ->whereIn('id', $updatedIds)
                ->update(['daily_report_sent_at' => $today]);
        }

        return Command::SUCCESS;
    }

    /* ===================================================== */

    private function processUser(
        $user,
        $solarClient,
        $waClient,
        $todayMonth,
        $todayDisplay
    ) {
        try {

            /* ---------- FETCH DATA ---------- */

            $response = $solarClient->get(
                'https://www.aotaisolarcloud.com/solarweb/plant/getRankByPageWithSize',
                [
                    'page'     => 0,
                    'pageSize' => 100,
                    'atun'     => $user->username,
                    'atpd'     => $user->password,
                    'date'     => $todayMonth,
                    'param'    => 'daypower',
                    'flag'     => 'false',
                ]
            );

            if (!$response->ok()) {
                return false;
            }

            $list = $response->json('list');
            if (empty($list)) {
                return false;
            }

            /* ---------- CALCULATE ---------- */

            $total = 0.0;
            foreach ($list as $row) {
                $total += $row['plantInfo']['eday'] ?? 0;
            }

            if ($total <= 0) {
                return false;
            }

            /* ---------- WHATSAPP ---------- */

            $waResponse = $waClient->post(
                'https://app.11za.in/apis/template/sendTemplate',
                [
                    'authToken'      => "U2FsdGVkX19F1SxG2t/SCM6FZsYxNRogvfHM9vr7dDjh8drxCK+CiQyv/Y/fSiJ/VKsIOqARcT7mnU0xN3jHlQa/1OFlrCw0gntC4xsUlo3ljR6rqW7bncim8YbGunV6PykJr6/qnpgi53swkm54cdDqXWvsUAea/eKtSgQpUpqL5nybHLepdP3rPyNRWXMA",
                    'name'           => $user->username,
                    'sendto'         => $user->phone,
                    'originWebsite'  => 'https://qbitsenergy.com/',
                    'templateName'   => 'qbits_daily_generation_report',
                    'language'       => 'en',
                    "buttonValue"    => "",
                    "headerdata"     => "",
                    'data'           => [$todayDisplay, $total],
                    "tags"           => ""
                ]
            );

            if (!$waResponse->ok()) {
                return false;
            }

            $waData = $waResponse->json();

            if (
                empty($waData['IsSuccess']) ||
                empty($waData['Data']['messageId'])
            ) {
                Log::warning("WA Failed User {$user->id}", $waData);
                return false;
            }

            /* ---------- WEEKLY SUM ---------- */

            if ($user->weekly_generation_report_flag) {
                DB::table('qbits_daily_generations')
                    ->updateOrInsert(
                        ['username' => $user->username],
                        ['total_daily_generation' => DB::raw("total_daily_generation + {$total}")]
                    );
            }

            return true;

        } catch (\Throwable $e) {

            Log::error(
                "DailyGeneration User {$user->id}: {$e->getMessage()}"
            );
            return false;
        }
    }
}
