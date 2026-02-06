<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MqttService;

class InverterCommandController extends BaseController
{
    public function sendCmd(Request $request)
    {
        $collector = $request->collector;

        // $payload = json_encode([
        //     'p_type' => $request->p_type,
        //     'collector' => $collector,
        //     'inverter_id' => $request->inverter_id,
        //     'cmd_id' => $request->cmd_id,
        //     'cmd' => array_filter([
        //         'set_act_pow_lim' => $request->set_act_pow_lim,
        //         'set_react_pow'   => $request->set_react_pow,
        //         'set_on_off'      => $request->set_on_off,
        //         'set_safe_v'      => $request->set_safe_v,
        //     ], fn ($v) => $v !== null),
        // ]);

        $cmdInput = $request->input('cmd', []);

        $cmd = array_filter([
            'set_act_pow_lim' => $cmdInput['set_act_pow_lim'] ?? null,
            'set_react_pow'   => $cmdInput['set_react_pow'] ?? null,
            'set_on_off'      => $cmdInput['set_on_off'] ?? null,
            'set_safe_v'      => $cmdInput['set_safe_v'] ?? null,
        ], fn ($v) => $v !== null);

        $payload = json_encode([
            'p_type' => $request->p_type,
            'collector' => $collector,
            'inverter_id' => $request->inverter_id,
            'cmd_id' => $request->cmd_id,
            'cmd' => empty($cmd) ? (object)[] : $cmd,
        ]);

        $mqtt = new MqttService();
        $mqtt->connect(
            config('mqtt.client_id_prefix') . '-cmd-' . $collector . '-' . uniqid()
        );

        $mqtt->publish(
            "heaven/devices/{$collector}/cmd",
            $payload
        );

        return response()->json(['status' => 'CMD_SENT']);
    }

    public function sendOta(Request $request)
    {
        $collector = $request->collector;

        $payload = json_encode([
            'cid'      => $collector,
            'ota_id'   => $request->ota_id,
            'ver'      => $request->ver,
            'url'      => $request->url,
            'checksum' => $request->checksum,
            'force'    => $request->force ?? false,
        ]);

        $mqtt = new MqttService();
        $mqtt->connect(
            config('mqtt.client_id_prefix') . '-ota-' . $collector . '-' . uniqid()
        );

        $mqtt->publish(
            "heaven/devices/{$collector}/ota",
            $payload
        );

        return response()->json(['status' => 'OTA_SENT']);
    }
}
