<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InverterMonthlyGeneration extends Command
{
    protected $signature = 'inverterMonthlyGeneration:cron';
    protected $description = 'Send monthly generation report to WhatsApp with minimal CPU & memory usage';

    public function handle()
    {
        $now   = now();
        $month = $now->copy()->subMonth()->format('M Y');
        $dateParam = $now->copy()->subMonth()->format('Y-m');

        $counter = 0;

        // Lazy load users one by one
        DB::table('clients')
            ->where('phone', '!=', '')
            // ->whereNull('company_code')
            ->where('monthly_generation_report_flag', 1)
            ->select('id', 'username', 'password', 'phone')
            ->orderBy('id')
            ->cursor()
            ->each(function ($user) use ($month, $dateParam, &$counter) {
                $this->processUser($user, $month, $dateParam);

                unset($user);
                gc_collect_cycles();

                $counter++;
                if ($counter % 10 === 0) {
                    usleep(5000); // tiny pause after every 10 users
                }
            });

        return 0;
    }

    /**
     * Process each user: login, fetch data, send WhatsApp
     */
    private function processUser($user, $month, $dateParam,$total=0)
    {
        try {
            // Step 1: Login
            [$contentMd5, $timestamp] = $this->generateCustomString(new \DateTime());

            $loginResponse = Http::withHeaders([
                'Content-MD5'  => $contentMd5,
                'timestamp'    => $timestamp,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()
                ->timeout(10)
                ->post('https://www.aotaisolarcloud.com/solarweb/api/login', [
                    'atun' => $user->username,
                    'atpd' => $user->password,
                ]);

            if (!$loginResponse->successful() || empty($loginResponse['data'])) {
                \Log::warning("MonthlyReport: Login failed for user {$user->id}");
                return;
            }

            // Step 2: Prepare token headers
            $token     = $loginResponse['data']['token']['token'];
            $appSecret = $loginResponse['data']['token']['appSecret'];
            $ts        = (string) round(microtime(true) * 1000);

            $contentMd5 = $this->generateTokenHash($token, $appSecret, $ts);

            $headers = [
                'content-length' => '0',
                'content-md5'    => $contentMd5,
                'timestamp'      => $ts,
                'token'          => $token,
            ];

            // Step 3: Fetch monthly data
            $url      = "https://www.aotaisolarcloud.com/solarweb/api/user/getMonthBar?date={$dateParam}";
            $response = Http::withHeaders($headers)->timeout(10)->get($url);

            if (!$response->successful() || empty($response['data'])) {
                \Log::warning("MonthlyReport: Data fetch failed for user {$user->id}");
                return;
            }

            $total = array_sum($response['data']);
            if ($total <= 0) return;

            // Step 4: Prepare WhatsApp message
//            $msg = "🌞✨ નમસ્તે {$user->username},\n
            $msg = "
Your Qbits Solar Inverter Monthly Generation Report ☀️⚡\n
📅 Month: {$month}
⚡ This Month's Generation: *{$total} kWh*\n
📌 Our Special Recommendations:
1. Clean the solar panels on time to maintain optimal generation.
2. Check inverter data at least once a month.
3. Keep the inverter in an open area with good air circulation and sunlight exposure.
4. Avoid direct sunlight falling on the inverter.\n
Submit a Ticket | Qbits: \nhttps://support.qbitsenergy.com";

            $payload = [
                'Name'   => $user->username,
                'Number' => $user->phone,
                'Message'=> $msg,
            ];

            // Step 5: Send WhatsApp via Wabb webhook
            $wabbWebhookUrl = config('services.webhook.url');
            Http::timeout(5)->get(
                $wabbWebhookUrl,
                $payload
            );
            sleep(random_int(5, 30));
        } catch (\Throwable $e) {
            \Log::error("MonthlyReport error for user {$user->id}: {$e->getMessage()}");
        }
    }

    private function calculateMd5($input)
    {
        return base64_encode(md5($input));
    }

    private function generateCustomString(\DateTime $dateTime)
    {
        $timestamp = (string) ($dateTime->getTimestamp() * 1000);
        $rev       = strrev($timestamp);
        $str       = $timestamp . '&-api-&' . $rev;
        $md5       = $this->calculateMd5($str);

        return [$md5 . $timestamp, $timestamp];
    }

    private function generateTokenHash($token, $appSecret, $timestamp = null)
    {
        $ts     = $timestamp ?? now()->toString();
        $rawStr = "{$token}&{$appSecret}&{$ts}";

        return $this->calculateMd5($rawStr);
    }
}
