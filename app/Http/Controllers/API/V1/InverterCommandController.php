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

        $payload = [
            'p_type' => 'cmd',
            'collector' => $collector,
            'inverter_id' => $request->inverter_id,
            'cmd_id' => $request->cmd_id,
            'cmd' => array_filter([
                'set_act_pow_lim' => $request->set_act_pow_lim,
                'set_react_pow'   => $request->set_react_pow,
                'set_on_off'      => $request->set_on_off,
                'set_safe_v'      => $request->set_safe_v,
            ], fn ($v) => $v !== null),
        ];

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

        $payload = [
            'cid'      => $collector,
            'ota_id'   => $request->ota_id,
            'ver'      => $request->ver,
            'url'      => $request->url,
            'checksum' => $request->checksum,
            'force'    => $request->force ?? false,
        ];

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
