<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InverterFault extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inverterFault:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $faultSolutions = $this->getFaultSolutions();

        // Process users in batches to reduce memory footprint
        DB::table('clients')
            ->where('phone', '!=', '')
            ->where('inverter_fault_flag', 1)
            // ->whereNull('company_code')
            ->orderBy('id')
            ->chunk(50, function ($users) use ($faultSolutions) {
                foreach ($users as $user) {
                    // (Generate MD5, call login API, etc.)
                    [$contentMd5, $timestamp] = $this->generateCustomString(new \DateTime());
                    $headers = [
                        'Content-MD5'   => $contentMd5,
                        'timestamp'     => $timestamp,
                        'Content-Type'  => 'application/x-www-form-urlencoded',
                    ];
                    $loginData = ['atun' => $user->username, 'atpd' => $user->password];
                    $response_login = Http::withOptions(['verify' => false])->withHeaders($headers)
                        ->asForm()
                        ->post('https://www.aotaisolarcloud.com/solarweb/api/login', $loginData);

                    if (!empty($response_login['data'])) {
                        $token     = $response_login['data']['token']['token'];
                        $appSecret = $response_login['data']['token']['appSecret'];
                        $timestamp = (string)round(microtime(true) * 1000);
                        $contentMd5 = $this->generateTokenHash($token, $appSecret, $timestamp);

                        $headers = [
                            'content-length' => '0',
                            'content-md5'    => $contentMd5,
                            'timestamp'      => $timestamp,
                            'token'          => $token,
                        ];
                        $cur_date = now()->format('Y-m-d');     // 2025-09-30
                        $cur_time = "00:00";
                        // $cur_time = now()->format('H:i');
                        $url = "http://www.aotaisolarcloud.com/solarweb/inverterWarn/getWarnByDateTime?"
                            . "iid=0&date={$cur_date}&time={$cur_time}&page=0&pageSize=500&atun={$user->username}&atpd={$user->password}";

                        $response = Http::withOptions(['verify' => false])->withHeaders($headers)->get($url);

                        // For demonstration, assume $response is parsed into array $data.
                        $data = $response['list'] ?? [];

                        if (!empty($data)) {
                            $messages = [];
                            foreach ($data as $idx => $item) {
                                foreach ($item['messageen'] ?? [] as $fault) {
                                    if (isset($faultSolutions[$fault])) {
                                        $details = $faultSolutions[$fault];
                                        $messages[] = "*" . (++$idx) . ". {$details['code']} {$fault}*";
                                        $messages[] = "💡 Solution:\n" . $details['solution'] . "\n";
                                    }
                                }
                            }

                            if (!empty($messages) && $user->inverter_fault_flag == 1) {
                                $plantName = $item['plant']['plantName'] ?? 'Unknown Plant';
                                $whatsAppContent = [
                                    'Name'    => $user->username,
                                    'Number'  => $user->phone,
                                    'Message' => "🌱 Plant: {$plantName}\n\n⚠ Detected Faults:\n"
                                        . implode("\n", $messages)
                                        . "\nIf problem not resolved, contact installer or raise a complaint:\n"
                                        . "*Helpdesk*: https://support.qbitsenergy.com\n"
                                        . "*Email*: support@qbitsenergy.com"
                                ];
                                $wabbWebhookUrl = config('services.webhook.url');
                                Http::withOptions(['verify' => false])->get($wabbWebhookUrl, $whatsAppContent);
                                sleep(random_int(5, 30));
                            }
                        }

                        // Free memory of $data and $messages
                        unset($response, $data, $messages);
                    }
                }
            });
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

    /**
     * Mapping of inverter faults to codes and solutions
     */
    private function getFaultSolutions(): array
    {
        return [
            "Grid voltage is too high" => [
                "code" => "F3:15",
                "solution" => "1.Check whether the grid is being lost.\n2.Check whether the AC terminal is burned out.\n3.Check whether the air switch of the distribution box works properly"
            ],
            "High leakage current" => [
                "code" => "F3:13",
                "solution" => "Check that the ground impedance of each DC input is greater than 1MΩ"
            ],
            "Fast leakage current change" => [
                "code" => "F3:12",
                "solution" => "Check that the ground impedance of each DC input is greater than 1MΩ"
            ],
            "PV2 current is too high" => [
                "code" => "F3:09",
                "solution" => "Check whether the DC input is short-circuited"
            ],
            "Voltage fluctuation" => [
                "code" => "F3:08",
                "solution" => "Check the three-phase AC phase voltage and line voltage. If normal, try to restart the inverter, if the fault is still, please contact our after-sales"
            ],
            "Midpoint imbalance" => [
                "code" => "F3:07",
                "solution" => "The AC IGBT or drive maybe be damaged, please contact our after-sales"
            ],
            "Low bus voltage" => [
                "code" => "F3:06",
                "solution" => "Check whether the power grid is cut off or the power distribution box is tripping"
            ],
            "High DC voltage" => [
                "code" => "F3:05",
                "solution" => "Check that the DC input voltage is not greater than the maximum operating voltage"
            ],
            "Low DC voltage" => [
                "code" => "F3:04",
                "solution" => "Check that the DC operating voltage is not less than minimum running voltage"
            ],
            "PV2 voltage is too high" => [
                "code" => "F3:03",
                "solution" => "Check that the DC input voltage is not greater than the maximum operating voltage"
            ],
            "PV2 voltage is too low" => [
                "code" => "F3:02",
                "solution" => "Check that the DC operating voltage is not less than minimum running voltage"
            ],
            "PV3 current is too high" => [
                "code" => "F3:00",
                "solution" => "Check whether the DC input is short-circuited"
            ],
            "PV4 current is too high" => [
                "code" => "F0:15",
                "solution" => "Check whether the DC input is short-circuited"
            ],
            "System external interrupt" => [
                "code" => "F0:13",
                "solution" => "Check the AC terminal and restart the inverter, if the fault is still, please contact our after-sales"
            ],
            "Wrong AC phase sequence" => [
                "code" => "F0:12",
                "solution" => "Check the three-phase AC phase voltage and line voltage. If normal, try to restart the inverter, if the fault is still, please contact our after-sales"
            ],
            "Failed to lock phase" => [
                "code" => "F0:11",
                "solution" => "Check the three-phase AC phase voltage and line voltage. If normal, try to restart the inverter, if the fault is still, please contact our after-sales"
            ],
            "Three-phase current imbalance" => [
                "code" => "F0:10",
                "solution" => "Check the AC terminal and restart the inverter, if the fault is still, please contact our after-sales"
            ],
            "SCI Communication fault" => [
                "code" => "F0:09",
                "solution" => "Inverter internal communication failure, try to restart the inverter, if the fault is still, contact our after-sales"
            ],
            "SPI Communication fault" => [
                "code" => "F0:08",
                "solution" => "Inverter internal communication failure, try to restart the inverter, if the fault is still, contact our after-sales"
            ],
            "Lock phase fault" => [
                "code" => "F0:07",
                "solution" => "Check the three-phase AC phase voltage and line voltage. If normal, try to restart the inverter, if the fault is still, please contact our after-sales"
            ],
            "High temperature" => [
                "code" => "F0:06",
                "solution" => "Check whether the inverter fan works normally, and ensure that the installation position of the inverter is ventilated and cooled and the surrounding spacing is more than 30cm"
            ],
            "LVRT timeout fault" => [
                "code" => "F0:05",
                "solution" => "Try to restart the inverter, if the fault is still, contact our after-sales"
            ],
            "Wrong DC polarity" => [
                "code" => "F0:02",
                "solution" => "Check whether the positive and negative electrodes of DC input are connected correctly with the male and female terminal"
            ],
            "Low output power" => [
                "code" => "F0:01",
                "solution" => "It is a normal alarm in the morning, evening, or rainy days. In clear weather, try to restart the inverter. If the fault is still, please contact our after-sales"
            ],
            "Leakage Current Sampling Channel Fault" => [
                "code" => "F0:00",
                "solution" => "The inverter leakage current sampling circuit is faulty, please contact our after-sales"
            ],
            "DC Circuit Breaker CB10 disconnected" => [
                "code" => "F1:15",
                "solution" => "Check the status of the CB10 circuit breaker in the inverter"
            ],
            "DC Circuit Breaker CB11 disconnected" => [
                "code" => "F1:14",
                "solution" => "Check the status of the CB11 circuit breaker in the inverter"
            ],
            "IGBT saturation fault" => [
                "code" => "F1:13",
                "solution" => "Check whether the inverter output power is limited"
            ],
            "AC auxiliary contactor failure" => [
                "code" => "F1:12",
                "solution" => "Check the working status of the AC auxiliary contactor"
            ],
            "PV3 voltage is too high" => [
                "code" => "F1:11",
                "solution" => "Check that the DC input voltage is not greater than maximum operating voltage"
            ],
            "Inverter thermal relay overheating" => [
                "code" => "F1:10",
                "solution" => "Check the ventilation environment of the inverter and try to restart the inverter. If the fault is still, please contact our after-sales"
            ],
            "AC contactor fault" => [
                "code" => "F1:09",
                "solution" => "The inverter internal relay is not closed, please contact our after-sales"
            ],
            "Emergency shutdown" => [
                "code" => "F1:08",
                "solution" => "Check the inverter emergency stop push button"
            ],
            "Grounding fault" => [
                "code" => "F1:07",
                "solution" => "The grounding fault of the DC group string, please check the DC input and solve the grounding"
            ],
            "AC Circuit Breaker CB20 disconnected" => [
                "code" => "F1:06",
                "solution" => "Check the Air switch CB20 inside the inverter"
            ],
            "Reactor thermal relay overheating" => [
                "code" => "F1:05",
                "solution" => "Check whether the inverter ventilation is blocked"
            ],
            "PEBB1 fault" => [
                "code" => "F1:04",
                "solution" => "Check the inverter inverter unit 1 module"
            ],
            "PEBB2 fault" => [
                "code" => "F1:03",
                "solution" => "Check the inverter inverter unit 2 module"
            ],
            "PEBB3 fault" => [
                "code" => "F1:02",
                "solution" => "Check the inverter inverter unit 3 module"
            ],
            "PEBB4 fault" => [
                "code" => "F1:01",
                "solution" => "Check the inverter inverter unit 4 module"
            ],
            "PEBB5 fault" => [
                "code" => "F1:00",
                "solution" => "Check the inverter inverter unit 5 module"
            ],
            "PV3 voltage is too low" => [
                "code" => "F2:15",
                "solution" => "Check that the DC operating voltage is not less than minimum running voltage"
            ],
            "Phase A voltage is too high" => [
                "code" => "F2:14",
                "solution" => "1.Check the AC phase voltage is not greater than Safety Voltage.\n2.Check whether the AC terminal is burned out"
            ],
            "Phase B voltage is too high" => [
                "code" => "F2:13",
                "solution" => "1.Check the AC phase voltage is not greater than Safety Voltage.\n2.Check whether the AC terminal is burned out"
            ],
            "Phase C voltage is too high" => [
                "code" => "F2:12",
                "solution" => "1.Check the AC phase voltage is not greater than Safety Voltage.\n2.Check whether the AC terminal is burned out"
            ],
            "Phase A voltage is too low" => [
                "code" => "F2:11",
                "solution" => "1.Check whether the grid is being lost.\n2.Check whether the AC terminal is burned out.\n3.Check whether the air switch of the distribution box works properly"
            ],
            "Phase B voltage is too low" => [
                "code" => "F2:10",
                "solution" => "1.Check whether the grid is being lost.\n2.Check whether the AC terminal is burned out.\n3.Check whether the air switch of the distribution box works properly"
            ],
            "Phase C voltage is too low" => [
                "code" => "F2:09",
                "solution" => "1.Check whether the grid is being lost.\n2.Check whether the AC terminal is burned out.\n3.Check whether the air switch of the distribution box works properly"
            ],
            "Phase A current is too high" => [
                "code" => "F2:08",
                "solution" => "If the internal filter inductance of the inverter is damaged or the AC output oscillates, please contact our after-sales"
            ],
            "Phase B current is too high" => [
                "code" => "F2:07",
                "solution" => "If the internal filter inductance of the inverter is damaged or the AC output oscillates, please contact our after-sales"
            ],
            "Phase C current is too high" => [
                "code" => "F2:06",
                "solution" => "If the internal filter inductance of the inverter is damaged or the AC output oscillates, please contact our after-sales"
            ],
            "Low frequency" => [
                "code" => "F2:05",
                "solution" => "1.Check whether the power grid is lost.\n2.Check whether the AC terminal is burned out.\n3.Check whether the air switch of the distribution box works properly"
            ],
            "High frequency" => [
                "code" => "F2:04",
                "solution" => "If the internal filter inductance of the inverter is damaged or the AC output oscillates, please contact our after-sales"
            ],
            "High bus voltage" => [
                "code" => "F2:03",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "Low DC voltage (short-circuit)" => [
                "code" => "F2:02",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
            "PV4 voltage is too high" => [
                "code" => "F2:01",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "PV4 voltage is too low" => [
                "code" => "F2:00",
                "solution" => "Check that the DC operating voltage is not less than minimum running voltage"
            ],
            "PV7 voltage is too high" => [
                "code" => "F4:13",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "PV7 current is too high" => [
                "code" => "F4:12",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
            "PV8 current is too high" => [
                "code" => "F4:10",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
            "PV9 voltage is too high" => [
                "code" => "F4:09",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "PV9 current is too high" => [
                "code" => "F4:08",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
            "PV10 voltage is too high" => [
                "code" => "F4:07",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "PV10 current is too high" => [
                "code" => "F4:06",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
            "PV11 voltage is too high" => [
                "code" => "F4:05",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "PV12 current is too high" => [
                "code" => "F4:04",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
            "PV12 voltage is too high" => [
                "code" => "F4:03",
                "solution" => "Check that the DC input cannot be greater than the maximum operating voltage"
            ],
            "PV12 current is too high (again)" => [
                "code" => "F4:02",
                "solution" => "Check whether the DC input is in the short-circuit state"
            ],
        ];
    }
}
