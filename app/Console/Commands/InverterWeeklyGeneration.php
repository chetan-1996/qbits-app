<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InverterWeeklyGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inverterWeeklyGeneration:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send weekly generation report to WhatsApp with minimal CPU usage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // Lazy load users one by one to reduce memory and CPU
        DB::table('clients as u')
            ->join('qbits_daily_generations as g', 'u.username', '=', 'g.username')
            ->where('u.phone', '!=', '')
            // ->whereNull('u.company_code')
            ->where('u.weekly_generation_report_flag', 1)
            ->select('g.id', 'g.username', 'g.total_daily_generation','u.phone')
            ->orderBy('u.id')
            ->cursor()
            ->each(function ($user) {

                $this->processUser($user);

                // Free memory per iteration
                unset($user);
                gc_collect_cycles();

                // Tiny pause to prevent CPU spikes
                usleep(5000); // 5 milliseconds
            });

        return 0;
    }

    /**
     * Process a single user: fetch inverter data and send WhatsApp report
     */
    private function processUser($user,$totalDailyGeneration = 0)
    {
        try {
            $startDate = now()->subWeek()->startOfWeek(); // last Monday 00:00
            $endDate   = now()->subWeek()->endOfWeek();   // last Sunday 23:59
            $startDateFormatted = $startDate->format('d-m-Y');
            $endDateFormatted   = $endDate->format('d-m-Y');
            $totalDailyGeneration = $user->total_daily_generation;
            // Prepare WhatsApp message
            $todayDate = now()->format('d M Y');
//            $message = "🌞✨ નમસ્તે {$user->username},\n
            $message ="
Your Qbits Solar Inverter Weekly Generation Report ☀️⚡\n
📅 Date: {$startDateFormatted} to {$endDateFormatted}
🔋 Weekly’s Generation: *{$user->total_daily_generation} kWh*\n
📌 Our Special Recommendations:
1. Clean the solar panels on time to maintain optimal generation.
2. Check inverter data at least once a month.
3. Keep the inverter in an open area with good air circulation and sunlight exposure.
4. Avoid direct sunlight falling on the inverter.\n
Submit a Ticket | Qbits \nhttps://support.qbitsenergy.com";

            $whatsAppContent = [
                'Name' => $user->username,
                'Number' => $user->phone,
                'Message' => $message,
            ];
            // Send report to Wabb API
            $wabbWebhookUrl = config('services.webhook.url');
            Http::withOptions(['verify' => false])->timeout(5)->get($wabbWebhookUrl, $whatsAppContent);

            DB::table('qbits_daily_generations')
                ->where('username', $user->username)
                ->delete();

            // Cleanup
            unset($whatsAppContent);
            sleep(random_int(5, 30));
            // usleep(random_int(1000000, 3000000)); // microseconds (1s – 3s)
        } catch (\Throwable $e) {
            \Log::error("DailyGenerationReport error for user {$user->id}: {$e->getMessage()}");
        }
    }
}
