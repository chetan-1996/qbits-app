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
    $users = DB::table('clients')
        ->where('phone', '!=', '')
        ->where('daily_generation_report_flag', 1)
        ->where(function ($q) {
            $q->whereNull('daily_report_sent_at')
              ->orWhere('daily_report_sent_at', '!=', now()->toDateString());
        })
        ->select(
            'id',
            'username',
            'password',
            'phone',
            'weekly_generation_report_flag'
        )
        // ->orderBy('id')
        ->inRandomOrder()
        ->limit(20) // ✅ HARD LIMIT
        ->get();

    foreach ($users as $user) {

        $sent = $this->processUser($user);

        // ✅ Mark sent ONLY after success (recommended)
       // if ($sent !== false) {
            DB::table('clients')
                ->where('id', $user->id)
                ->update([
                    'daily_report_sent_at' => now()->toDateString()
                ]);
        //}
 //unset($user);
                //gc_collect_cycles();
        // WhatsApp safe gap
        usleep(200000); // 0.2 sec
    }

    return 0;
}
    // public function handle()
    // {

    //     // Lazy load users one by one to reduce memory and CPU
    //     DB::table('clients')
    //         ->where('phone', '!=', '')
    //         // ->whereNull('company_code')
    //         ->where('daily_generation_report_flag', 1)
    //         ->where(function ($q) {
    //         $q->whereNull('daily_report_sent_at')
    //           ->orWhere('daily_report_sent_at', '!=', now()->toDateString());
    //     })
    //         ->select('id', 'username', 'password', 'phone','weekly_generation_report_flag')
    //         ->orderBy('id')
    //         ->cursor()
    //         ->each(function ($user) {

    //             $this->processUser($user);

    //             DB::table('clients')
    //         ->where('id', $user->id)
    //         ->update(['daily_report_sent_at' => now()->toDateString()]);

    //             // Free memory per iteration
    //             unset($user);
    //             gc_collect_cycles();

    //             // Tiny pause to prevent CPU spikes
    //             // usleep(5000); // 5 milliseconds
    //             usleep(200000);
    //         });

    //     return 0;
    // }

    /**
     * Process a single user: fetch inverter data and send WhatsApp report
     */
    private function processUser($user,$totalDailyGeneration = 0)
    {
        try {
            $baseUrl = "https://www.aotaisolarcloud.com/solarweb/plant/getRankByPageWithSize";

            $response = Http::withOptions(['verify' => false])->timeout(15)->get($baseUrl, [
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
//             $message ="
// Your Qbits Solar Inverter Daily Generation Report ☀️⚡\n
// 📅 Date: {$todayDate}
// 🔋 Today’s Generation: *{$totalDailyGeneration} kWh*\n
// 📌 Our Special Recommendations:
// 1. Clean the solar panels on time to maintain optimal generation.
// 2. Check inverter data at least once a month.
// 3. Keep the inverter in an open area with good air circulation and sunlight exposure.
// 4. Avoid direct sunlight falling on the inverter.\n
// Submit a Ticket | Qbits: \nhttps://support.qbitsenergy.com";

$templates = ["
Your Qbits Solar Inverter Daily Generation Report ☀️⚡

📅 Date: {$todayDate}
🔋 Today’s Generation: *{$totalDailyGeneration} kWh*

📌 Tips to Maintain Better Generation:
1. Clean solar panels regularly.
2. Check inverter readings every month.
3. Ensure proper airflow around inverter.
4. Avoid installing inverter in direct sunlight.

Submit a Ticket | Qbits:
https://support.qbitsenergy.com
",
"
Qbits Daily Solar Power Summary ☀️⚡

📅 Report Date: {$todayDate}
🔋 Energy Produced Today: *{$totalDailyGeneration} kWh*

⚡ Maintenance Suggestions:
1. Clean panels on time to avoid dust loss.
2. Inspect inverter once a month.
3. Keep inverter in a ventilated area.
4. Prevent heat exposure and direct sunlight.

Need Support?
https://support.qbitsenergy.com
",
"
Qbits Energy — Daily Power Report 🌞

📅 Today's Date: {$todayDate}
🔋 Your Solar Output Today: *{$totalDailyGeneration} kWh*

📝 Best Practice Guide:
1. Clean panels periodically.
2. Review inverter performance monthly.
3. Maintain airflow around inverter.
4. Keep inverter away from heat & sunlight.

Raise a Service Ticket:
https://support.qbitsenergy.com
",
"
Qbits Solar – Daily Generation Update ⚡

📅 Date: {$todayDate}
🔋 Power Generated Today: *{$totalDailyGeneration} kWh*

💡 Efficiency Recommendations:
1. Timely cleaning improves generation.
2. Monitor inverter health monthly.
3. Ensure proper cooling & ventilation.
4. Avoid placing inverter in harsh sunlight.

Contact Support:
https://support.qbitsenergy.com
"];

$message = $templates[array_rand($templates)];
            $whatsAppContent = [
                'Name' => $user->username,
                'Number' => $user->phone,
                'Message' => $message,
            ];
            $wabbWebhookUrl = config('services.webhook.url');
            // Send report to Wabb API
            Http::withOptions(['verify' => false])->timeout(15)->get($wabbWebhookUrl, $whatsAppContent);
            if($user->weekly_generation_report_flag==1){
                DB::table('qbits_daily_generations')->updateOrInsert(
                    ['username' => $user->username],
                    ['total_daily_generation' => DB::raw("COALESCE(total_daily_generation, 0) + {$totalDailyGeneration}")]
                );
            }
            // Cleanup
            unset($whatsAppContent, $response);
            sleep(random_int(20, 60));
            // usleep(random_int(1000000, 3000000)); // microseconds (1s – 3s);
        } catch (\Throwable $e) {
            \Log::error("DailyGenerationReport error for user {$user->id}: {$e->getMessage()}");
        }
    }
}
