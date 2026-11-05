<?php

namespace App\Console\Commands;

use App\Services\ShineMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpLinkDailyGeneration extends Command
{
    protected $signature = 'tpLinkDailyGeneration:cron';
    protected $description = 'TP-Link (ShineMonitor) Daily Generation WhatsApp Report';

    public function handle()
    {
        DB::disableQueryLog();

        $today        = date('Y-m-d');
        $todayDisplay = date('d M Y');
        $updatedIds   = [];

        /* ---------------- HTTP CLIENT ---------------- */

        $waClient = Http::withOptions([
            'verify' => false,
            'timeout' => 8,
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ]);

        $shine = app(ShineMonitorService::class);

        /* ---------------- STREAM USERS ---------------- */

        $query = DB::table('clients')
            ->where('server_flag', 2)
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
            ->limit(20);

        $totalUsers = (clone $query)->count();
        $this->info("TpLinkDailyGeneration: {$totalUsers} eligible users for {$today}");
        Log::info("TpLinkDailyGeneration started", ['date' => $today, 'eligible_users' => $totalUsers]);

        $query
            ->lazyById(50)
            ->each(function ($user) use (
                $shine,
                $waClient,
                $today,
                $todayDisplay,
                &$updatedIds
            ) {

                if ($this->processUser(
                    $user,
                    $shine,
                    $waClient,
                    $today,
                    $todayDisplay
                )) {
                    $updatedIds[] = $user->id;
                }

                // HARD GUARD
                if (memory_get_usage(true) > 150 * 1024 * 1024) {
                    Log::warning('TpLinkDailyGeneration: Memory threshold hit');
                    return false;
                }
            });

        /* ---------------- BATCH UPDATE ---------------- */

        if ($updatedIds) {
            DB::table('clients')
                ->whereIn('id', $updatedIds)
                ->update(['daily_report_sent_at' => $today]);
        }

        $this->info('TpLinkDailyGeneration done. Sent: ' . count($updatedIds) . " / {$totalUsers}");
        Log::info('TpLinkDailyGeneration finished', ['sent' => count($updatedIds)]);

        return Command::SUCCESS;
    }

    /* ===================================================== */

    private function processUser(
        $user,
        $shine,
        $waClient,
        $today,
        $todayDisplay
    ) {
        try {

            /* ---------- AUTH ---------- */

            $auth = $shine->auth($user->username, $user->password);

            if (($auth['err'] ?? -1) !== 0) {
                Log::warning("TpLinkDailyGeneration auth failed User {$user->id}", [
                    'err'  => $auth['err'] ?? null,
                    'desc' => $auth['desc'] ?? 'unknown',
                ]);
                $this->warn("User {$user->id} ({$user->username}): auth failed - " . ($auth['desc'] ?? 'unknown'));
                return false;
            }

            $token  = $auth['dat']['token'] ?? null;
            $secret = $auth['dat']['secret'] ?? null;

            if (!$token || !$secret) {
                Log::warning("TpLinkDailyGeneration missing token/secret User {$user->id}", $auth);
                $this->warn("User {$user->id}: auth ok but token/secret missing");
                return false;
            }

            /* ---------- FETCH DAY ENERGY ---------- */

            $res = $shine->call('queryPlantsEnergyDay', ['date' => $today], $token, $secret);

            if (($res['err'] ?? -1) !== 0) {
                if (($res['err'] ?? 0) == 12) {
                    // ERR_NO_RECORD — aaje generation nathi, normal skip
                    $this->line("User {$user->id} ({$user->username}): no generation data today");
                } else {
                    Log::warning("TpLinkDailyGeneration query failed User {$user->id}", [
                        'err'  => $res['err'] ?? null,
                        'desc' => $res['desc'] ?? 'unknown',
                        'response' => $res,
                    ]);
                    $this->warn("User {$user->id} ({$user->username}): query failed - " . ($res['desc'] ?? 'unknown'));
                }
                return false;
            }

            $total = (float) ($res['dat']['energy'] ?? 0);

            if ($total <= 0) {
                $this->line("User {$user->id} ({$user->username}): energy = 0");
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
                    'templateName'   => 'qbits_daily_generation_reports',
                    'language'       => 'en',
                    "buttonValue"    => "",
                    "headerdata"     => "",
                    'data'           => [$todayDisplay, round($total, 2)],
                    "tags"           => ""
                ]
            );

            if (!$waResponse->ok()) {
                Log::warning("TpLinkDailyGeneration WA HTTP failed User {$user->id}", [
                    'status' => $waResponse->status(),
                    'body'   => $waResponse->body(),
                ]);
                $this->warn("User {$user->id}: WhatsApp HTTP {$waResponse->status()}");
                return false;
            }

            $waData = $waResponse->json();

            if (
                empty($waData['IsSuccess']) ||
                empty($waData['Data']['messageId'])
            ) {
                Log::warning("TpLinkDailyGeneration WA Failed User {$user->id}", $waData);
                $this->warn("User {$user->id}: WhatsApp API failed");
                return false;
            }

            Log::build([
                'driver' => 'single',
                'path'   => storage_path('logs/daily_generation.log'),
            ])->info('TpLinkDailyGeneration WA sent', [
                'id'         => $user->id,
                'username'   => $user->username,
                'phone'      => $user->phone,
                'eday'       => $total,
                'message_id' => $waData['Data']['messageId'],
            ]);

            /* ---------- WEEKLY SUM ---------- */

            if ($user->weekly_generation_report_flag) {
                DB::table('qbits_daily_generations')
                    ->updateOrInsert(
                        ['username' => $user->username],
                        ['total_daily_generation' => DB::raw("total_daily_generation + {$total}")]
                    );
            }

            $this->info("User {$user->id} ({$user->username}): sent {$total} kWh");
            return true;

        } catch (\Throwable $e) {

            Log::error(
                "TpLinkDailyGeneration User {$user->id}: {$e->getMessage()}"
            );
            $this->error("User {$user->id}: {$e->getMessage()}");
            return false;
        }
    }
}
