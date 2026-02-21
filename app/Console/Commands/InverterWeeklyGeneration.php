<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InverterWeeklyGeneration extends Command
{
    protected $signature = 'inverterWeeklyGeneration:cron';
    protected $description = 'Weekly Generation WhatsApp Report (Ultra Optimized)';

    public function handle()
    {
        DB::disableQueryLog();

        register_shutdown_function(function () {
            Log::info('Weekly Cron Peak Memory', [
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ]);
        });

        /* ---------------- WEEK RANGE ---------------- */

        $weekStart = Carbon::now()->subWeek()->startOfWeek()->format('d-m-Y');
        $weekEnd   = Carbon::now()->subWeek()->endOfWeek()->format('d-m-Y');

        /* ---------------- HTTP CLIENT ---------------- */

        $waClient = $this->createHttpClient();
        $counter  = 0;

        /* ---------------- STREAM USERS + GENERATION ---------------- */

        DB::table('clients as c')
            ->join(
                'qbits_daily_generations as g',
                'g.username',
                '=',
                'c.username'
            )
            ->where('c.weekly_generation_report_flag', 1)
            ->whereNotNull('c.phone')
            // ->whereIn('c.id', [12, 20])
            ->whereNotNull('g.total_daily_generation')
            ->where('g.total_daily_generation', '>', 0)
            ->select(
                'c.id',
                'c.username',
                'c.phone',
                'g.total_daily_generation'
            )
            ->orderBy('c.id')
            ->lazyById(100, 'c.id')
            ->each(function ($user) use (
                &$waClient,
                &$counter,
                $weekStart,
                $weekEnd
            ) {

                $counter++;

                if ($this->sendWhatsApp(
                    $waClient,
                    $user,
                    $user->total_daily_generation,
                    $weekStart,
                    $weekEnd
                )) {
                    DB::table('qbits_daily_generations')
                        ->where('username', $user->username)
                        ->limit(1)
                        ->delete();
                }

                if ($counter % 200 === 0) {
                    Log::info('Weekly Cron Running', [
                        'processed_users' => $counter,
                        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                    ]);
                }

                if ($counter % 500 === 0) {
                    $waClient = $this->createHttpClient();
                }

                if (memory_get_usage(true) > 200 * 1024 * 1024) {
                    Log::warning('Memory threshold reached. Graceful stop.');
                    return false; // stops lazy collection safely
                }
            });

        return Command::SUCCESS;
    }

    /* ===================================================== */

    private function createHttpClient()
    {
        return Http::withOptions([
            'verify'  => false,
            'timeout' => 8,
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ]);
    }

    /* ===================================================== */

    private function sendWhatsApp(
        $waClient,
        $user,
        $generation,
        $startDisplay,
        $endDisplay
    ): bool {
        try {
            $response = $waClient->post(
                'https://app.11za.in/apis/template/sendTemplate',
                [
                    'authToken'     => "U2FsdGVkX19F1SxG2t/SCM6FZsYxNRogvfHM9vr7dDjh8drxCK+CiQyv/Y/fSiJ/VKsIOqARcT7mnU0xN3jHlQa/1OFlrCw0gntC4xsUlo3ljR6rqW7bncim8YbGunV6PykJr6/qnpgi53swkm54cdDqXWvsUAea/eKtSgQpUpqL5nybHLepdP3rPyNRWXMA",
                    'name'          => $user->username,
                    'sendto'        => $user->phone,
                    'originWebsite' => 'https://qbitsenergy.com/',
                    'templateName'  => 'qbits_weekly_generation_reports',
                    'language'      => 'en',
                    'data' => [
                        $startDisplay,
                        $endDisplay,
                        $generation
                    ]
                ]
            );

            if (!$response->ok()) {
                return false;
            }

            $data = $response->json();

            if (
                empty($data['IsSuccess']) ||
                empty($data['Data']['messageId'])
            ) {
                Log::warning("WA Failed User {$user->id}", $data);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error("WeeklyGeneration User {$user->id}: {$e->getMessage()}");
            return false;
        }
    }
}
