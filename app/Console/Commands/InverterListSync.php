<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InverterListSync extends Command
{
    protected $signature = 'inverterListSync:cron';
    protected $description = 'Sync inverter list from Aotai Solar and store into DB';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        DB::table('clients')
            ->select('id', 'username', 'password')
            ->orderBy('id')
            ->chunkById(50, function ($clients) {

                foreach ($clients as $client) {

                    /** LOGIN ONCE FOR CLIENT **/
                    [$contentMd5, $timestamp] = $this->generateCustomString(new \DateTime());

                    $loginHeaders = [
                        'Content-MD5'  => $contentMd5,
                        'timestamp'    => $timestamp,
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ];

                    $loginResponse = Http::withOptions(['verify' => false])
                        ->withHeaders($loginHeaders)
                        ->asForm()
                        ->post('https://www.aotaisolarcloud.com/solarweb/api/login', [
                            'atun' => $client->username,
                            'atpd' => $client->password,
                        ]);

                    if (empty($loginResponse['data']['token'])) {
                        Log::warning('Login failed', ['client_id' => $client->id]);
                        continue;
                    }

                    $token     = $loginResponse['data']['token']['token'];
                    $appSecret = $loginResponse['data']['token']['appSecret'];

                    /** GET PLANTS FOR THIS CLIENT **/
                    $plantNos = DB::table('plant_infos')
                        ->where('user_id', $client->id)
                        ->pluck('plant_no');

                    foreach ($plantNos as $plantNo) {

                        $timestamp  = (string) round(microtime(true) * 1000);
                        $contentMd5 = $this->generateTokenHash($token, $appSecret, $timestamp);

                        $headers = [
                            'content-length' => '0',
                            'content-md5'    => $contentMd5,
                            'timestamp'      => $timestamp,
                            'token'          => $token,
                        ];

                        $url = 'https://www.aotaisolarcloud.com/solarweb/api/inverterInfo/getByPlantId';

                        $response = Http::withOptions(['verify' => false])
                            ->withHeaders($headers)
                            ->get($url, [
                                'plantid' => $plantNo
                            ]);

                        $data = $response['list'] ?? [];

                        if (empty($data)) {
                            continue;
                        }

                        /** BUILD BATCH UPSERT DATA **/
                        $batch = [];
                        foreach ($data as $inv) {
                            $batch[] = [
                                'id'                => $inv['id'] ?? null,
                                'inverter_no'       => $inv['inverterNo'] ?? null,
                                'inverter_address'  => $inv['inverterAddress'] ?? null,
                                'collector_address' => $inv['collectorAddress'] ?? null,
                                'model'             => $inv['model'] ?? null,
                                'state'             => $inv['state'] ?? null,
                                'control'           => $inv['control'] ?? null,
                                'register0a'        => $inv['register0a'] ?? null,
                                'register31'        => $inv['register31'] ?? null,
                                'register29'        => $inv['register29'] ?? null,
                                'register2a'        => $inv['register2a'] ?? null,
                                'remark1'           => $inv['remark1'] ?? null,
                                'remark2'           => $inv['remark2'] ?? null,
                                'remark3'           => $inv['remark3'] ?? null,
                                'remark4'           => $inv['remark4'] ?? null,
                                'room_id'           => $inv['roomId'] ?? null,
                                'plant_id'          => $inv['plantId'] ?? null,
                                'timezone'          => $inv['timezone'] ?? null,
                                'record_time'       => $inv['recordTime'] ?? null,
                                'inverter_type'     => $inv['invertertype'] ?? null,
                                'load'              => $inv['load'] ?? null,
                                'panel'             => $inv['panel'] ?? null,
                                'panel_num'         => $inv['panelnum'] ?? null,
                                'user_id'           => $client->id,
                                'atun'              => $client->username,
                                'atpd'              => $client->password,
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ];
                        }

                        /** UPSERT — NO DUPLICATES **/
                        DB::table('inverters')->upsert(
                            $batch,
                            ['id'], // UNIQUE KEY (no duplicates)
                            [       // FIELDS TO UPDATE IF EXISTS
                                'inverter_no','inverter_address','collector_address','model','state',
                                'control','register0a','register31','register29','register2a',
                                'remark1','remark2','remark3','remark4','room_id','plant_id','timezone',
                                'record_time','inverter_type','load','panel','panel_num','updated_at'
                            ]
                        );


                        $url = 'https://www.aotaisolarcloud.com/solarweb/api/inverterDatas/getByPlantId';

                        $response_data = Http::withOptions(['verify' => false])
                            ->withHeaders($headers)
                            ->get($url, [
                                'plantid' => $plantNo
                            ]);

                        $inverter_data = $response_data['list'] ?? [];

                        if (empty($inverter_data)) {
                            continue;
                        }

                        $detailsBatch = [];

                        foreach ($inverter_data as $row) {
                            $detailsBatch[] = [
                                'inverterId'         => $row['inverterId'] ?? null,
                                'recordTime'         => $row['recordTime'] ?? null,
                                'recordDate'         => date('Y-m-d'),
                                'inverterState'      => $row['inverterState'] ?? null,
                                'onlineHours'        => $row['onlineHours'] ?? null,
                                'onlineMinutes'      => $row['onlineMinutes'] ?? null,
                                'onlineSeconds'      => $row['onlineSeconds'] ?? null,

                                'alarmInfor1'        => $row['alarmInfor1'] ?? null,
                                'alarmInfor2'        => $row['alarmInfor2'] ?? null,
                                'alarmInfor3'        => $row['alarmInfor3'] ?? null,
                                'alarmInfor4'        => $row['alarmInfor4'] ?? null,
                                'alarmInfor5'        => $row['alarmInfor5'] ?? null,
                                'alarmInfor6'        => $row['alarmInfor6'] ?? null,
                                'alarmInfor7'        => $row['alarmInfor7'] ?? null,
                                'alarmInfory1'       => $row['alarmInfory1'] ?? null,
                                'alarmInfory2'       => $row['alarmInfory2'] ?? null,
                                'alarmInfory3'       => $row['alarmInfory3'] ?? null,
                                'alarmInfory4'       => $row['alarmInfory4'] ?? null,
                                'alarmInfory5'       => $row['alarmInfory5'] ?? null,
                                'alarmInfory6'       => $row['alarmInfory6'] ?? null,
                                'alarmInfory7'       => $row['alarmInfory7'] ?? null,

                                'dayMpp'             => $row['dayMpp'] ?? null,
                                'mpptVoltage'        => $row['mpptVoltage'] ?? null,
                                'acVoltage'          => $row['acVoltage'] ?? null,
                                'acBvoltage'         => $row['acBvoltage'] ?? null,
                                'acCvoltage'         => $row['acCvoltage'] ?? null,
                                'acCurrent'          => $row['acCurrent'] ?? null,
                                'acBcurrent'         => $row['acBcurrent'] ?? null,
                                'acCcurrent'         => $row['acCcurrent'] ?? null,
                                'acFrequency'        => $row['acFrequency'] ?? null,
                                'acMomentaryPower'   => $row['acMomentaryPower'] ?? null,
                                'reactivePower'      => $row['reactivePower'] ?? null,

                                'dcMomentaryPower'   => $row['dcMomentaryPower'] ?? null,
                                'dcMomentaryPower2'  => $row['dcMomentaryPower2'] ?? null,
                                'dcMomentaryPower3'  => $row['dcMomentaryPower3'] ?? null,
                                'dcMomentaryPower4'  => $row['dcMomentaryPower4'] ?? null,
                                'dcMomentaryPower5'  => $row['dcMomentaryPower5'] ?? null,
                                'dcMomentaryPower6'  => $row['dcMomentaryPower6'] ?? null,

                                'dcCurrent'          => $row['dcCurrent'] ?? null,
                                'dcVoltage'          => $row['dcVoltage'] ?? null,
                                'dcCurrent2'         => $row['dcCurrent2'] ?? null,
                                'dcVoltage2'         => $row['dcVoltage2'] ?? null,
                                'dcCurrent3'         => $row['dcCurrent3'] ?? null,
                                'dcVoltage3'         => $row['dcVoltage3'] ?? null,
                                'dcCurrent4'         => $row['dcCurrent4'] ?? null,
                                'dcVoltage4'         => $row['dcVoltage4'] ?? null,
                                'dcCurrent5'         => $row['dcCurrent5'] ?? null,
                                'dcVoltage5'         => $row['dcVoltage5'] ?? null,
                                'dcCurrent6'         => $row['dcCurrent6'] ?? null,
                                'dcVoltage6'         => $row['dcVoltage6'] ?? null,
                                'dcVoltageMuxian'    => $row['dcVoltageMuxian'] ?? null,
                                'dcVoltageMuxian2'   => $row['dcVoltageMuxian2'] ?? null,

                                'dayPowerHigh'       => $row['dayPowerHigh'] ?? null,
                                'dayPowerLower'      => $row['dayPowerLower'] ?? null,
                                'totalPowerHigh'     => $row['totalPowerHigh'] ?? null,
                                'totalPowerLower'    => $row['totalPowerLower'] ?? null,

                                'temperature'        => $row['temperature'] ?? null,
                                'temperaturedc'      => $row['temperaturedc'] ?? null,
                                'powerFactor'        => $row['powerFactor'] ?? null,
                                'co2'                => $row['co2'] ?? null,
                                'iv'                 => $row['iv'] ?? null,
                                'angui'              => $row['angui'] ?? null,

                                'plantId'            => $row['plantId'] ?? null,
                                'keepLive'           => $row['keepLive'] ?? null,
                                'signal'             => $row['signal'] ?? null,

                                'powerSet'           => $row['powerSet'] ?? null,
                                'repowerSet'         => $row['repowerSet'] ?? null,
                                'protocolV'          => $row['protocolV'] ?? null,
                                'deviceType'         => $row['deviceType'] ?? null,
                                'currentSwitch'      => $row['currentSwitch'] ?? null,

                                'current1'           => $row['current1'] ?? null,
                                'current2'           => $row['current2'] ?? null,
                                'current3'           => $row['current3'] ?? null,
                                'current4'           => $row['current4'] ?? null,
                                'current5'           => $row['current5'] ?? null,
                                'current6'           => $row['current6'] ?? null,
                                'current7'           => $row['current7'] ?? null,
                                'current8'           => $row['current8'] ?? null,
                                'current9'           => $row['current9'] ?? null,
                                'current10'          => $row['current10'] ?? null,
                                'current11'          => $row['current11'] ?? null,
                                'current12'          => $row['current12'] ?? null,
                                'current13'          => $row['current13'] ?? null,
                                'current14'          => $row['current14'] ?? null,
                                'current15'          => $row['current15'] ?? null,
                                'current16'          => $row['current16'] ?? null,

                                'inverterSn'         => $row['inverterSn'] ?? null,
                                'inverterNo'         => $row['inverterNo'] ?? null,
                                'outputStatus'       => $row['outputStatus'] ?? null,

                                'meterPower'         => $row['meterPower'] ?? null,
                                'meterTotal'         => $row['meterTotal'] ?? null,
                                'meterRetotal'       => $row['meterRetotal'] ?? null,

                                'user_id'            => $client->id,
                                'atun'               => $client->username,
                                'atpd'               => $client->password,

                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ];
                        }
                            DB::table('inverter_details')->upsert(
                                $detailsBatch,
                                ['inverterId', 'created_at'], // primary key, prevents duplicates
                                array_keys($detailsBatch[0]) // update all keys on conflict
                            );

                        // Log::info("Synced " . json_encode($inverter_data) . " inverters plant {$plantNo}");
                    }
                }
            });

        Log::info("Inverter Sync: Completed Successfully");
        return 0;
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
