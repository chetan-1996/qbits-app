<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InverterDailyGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inverterDailyGeneration:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily generation report to WhatsApp with minimal CPU usage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // Lazy load users one by one to reduce memory and CPU
        DB::table('clients')
            ->whereNull('company_code')
            ->where('daily_generation_report_flag', 1)
            ->select('id', 'username', 'password', 'phone','weekly_generation_report_flag')
            ->orderBy('id')
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
            $baseUrl = "https://www.aotaisolarcloud.com/solarweb/plant/getRankByPageWithSize";

            $response = Http::timeout(10)->get($baseUrl, [
                'page' => 0,
                'pageSize' => 100,
                'atun' => $user->username,
                'atpd' => $user->password,
                'date' => now()->format('Y-m'),
                'param' => 'daypower',
                'flag' => 'false',
            ]);

            if (!$response->successful() || empty($response['list'])) {
                return;
            }

            // Calculate total daily generation
            $totalDailyGeneration = 0;
            foreach ($response['list'] as $item) {
                $totalDailyGeneration += $item['plantInfo']['eday'] ?? 0;
            }

            if ($totalDailyGeneration <= 0) return;

            // Prepare WhatsApp message
            $todayDate = now()->format('d M Y');
//            $message = "🌞✨ નમસ્તે {$user->username},\n
            $message ="
Your Qbits Solar Inverter Daily Generation Report ☀️⚡\n
📅 Date: {$todayDate}
🔋 Today’s Generation: *{$totalDailyGeneration} kWh*\n
📌 Our Special Recommendations:
1. Clean the solar panels on time to maintain optimal generation.
2. Check inverter data at least once a month.
3. Keep the inverter in an open area with good air circulation and sunlight exposure.
4. Avoid direct sunlight falling on the inverter.\n
Submit a Ticket | Qbits: \nhttps://support.qbitsenergy.com";

            $whatsAppContent = [
                'Name' => $user->username,
                'Number' => $user->phone,
                'Message' => $message,
            ];
            // Send report to Wabb API
            Http::timeout(5)->get('https://api.wabb.in/api/v1/webhooks-automation/catch/287/CSZS8YqZZrM9/', $whatsAppContent);
            if($user->weekly_generation_report_flag==1){
                DB::table('qbits_daily_generations')->updateOrInsert(
                    ['username' => $user->username],
                    ['total_daily_generation' => DB::raw("COALESCE(total_daily_generation, 0) + {$totalDailyGeneration}")]
                );
            }
            // Cleanup
            unset($whatsAppContent, $response);
            usleep(random_int(1000000, 3000000)); // microseconds (1s – 3s);
        } catch (\Throwable $e) {
            \Log::error("DailyGenerationReport error for user {$user->id}: {$e->getMessage()}");
        }
    }
}
