<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InverterMonthlyGeneration extends Command
{
    protected $signature = 'inverterMonthlyGeneration:cron';
    protected $description = 'Send monthly generation report to WhatsApp';

    public function handle()
    {
        DB::disableQueryLog();
        $now = now();
        $month = $now->copy()->subMonth()->format('M Y');
        $dateParam = $now->copy()->subMonth()->format('Y-m');

        DB::table('clients')
            ->where('phone', '!=', '')
            // ->whereIn('id', [12, 20])
            ->where('monthly_generation_report_flag', 1)
            ->select('id', 'username', 'password', 'phone')
            ->orderBy('id')
            ->lazyById(50)
            ->each(function ($user) use ($month, $dateParam) {
                $this->process($user, $month, $dateParam);
            });

        return Command::SUCCESS;
    }

    private function process($user, $month, $dateParam, $total=0)
    {
        try {
            // Login
            [$contentMd5, $timestamp] = $this->sign();
            
            $login = Http::withOptions(['verify' => false, 'timeout' => 15])
                ->withHeaders([
                    'Content-MD5' => $contentMd5,
                    'timestamp' => $timestamp,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()
                ->post('https://www.aotaisolarcloud.com/solarweb/api/login', [
                    'atun' => $user->username,
                    'atpd' => $user->password,
                ]);

            if (!$login->successful() || empty($login['data'])) return;

            // Fetch data
            $token = $login['data']['token']['token'];
            $secret = $login['data']['token']['appSecret'];
            $ts = (string) round(microtime(true) * 1000);
            
            $md5 = $this->hash($token, $secret, $ts);
            
            $resp = Http::withOptions(['verify' => false, 'timeout' => 15])
                ->withHeaders([
                    'content-length' => '0',
                    'content-md5' => $md5,
                    'timestamp' => $ts,
                    'token' => $token,
                ])
                ->get("https://www.aotaisolarcloud.com/solarweb/api/user/getMonthBar?date={$dateParam}");

            if (!$resp->successful() || empty($resp['data'])) return;

            // Calculate total
            // $total = 0;
            // foreach ($resp['data'] as $v) $total += $v;
            $total = array_sum($resp['data']);
            if ($total <= 0) return;

            // Send WhatsApp
            Http::withOptions(['verify' => false, 'timeout' => 8])
                ->post('https://app.11za.in/apis/template/sendTemplate', [
                    'authToken' => 'U2FsdGVkX19F1SxG2t/SCM6FZsYxNRogvfHM9vr7dDjh8drxCK+CiQyv/Y/fSiJ/VKsIOqARcT7mnU0xN3jHlQa/1OFlrCw0gntC4xsUlo3ljR6rqW7bncim8YbGunV6PykJr6/qnpgi53swkm54cdDqXWvsUAea/eKtSgQpUpqL5nybHLepdP3rPyNRWXMA',
                    'name' => $user->username,
                    'sendto' => $user->phone,
                    'originWebsite' => 'https://qbitsenergy.com/',
                    'templateName' => 'qbits_monthly_generation_report',
                    'language' => 'en',
                    'data' => [$month, number_format($total, 2, '.', '')],
                ]);
        } catch (\Throwable $e) {
            \Log::error("MonthlyReport: User {$user->id} - {$e->getMessage()}");
        }
    }

    private function sign()
    {
        $t = (string) (time() * 1000);
        $r = strrev($t);
        $m = base64_encode(md5("{$t}&-api-&{$r}"));
        return [$m . $t, $t];
    }

    private function hash($token, $secret, $ts)
    {
        return base64_encode(md5("{$token}&{$secret}&{$ts}"));
    }
}
