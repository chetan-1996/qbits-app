<?php

namespace App\Http\Controllers\API\V1;

use App\Services\MqttService;
use Illuminate\Http\Request;

class InverterController extends BaseController
{
    public function sendCommand(Request $request, $id)
    {
        $command = $request->all();
        $topic = "inverters/{$id}/command";

        app(MqttService::class)->publish($topic, $command, 1); // QoS 1

        return response()->json(['status' => 'sent', 'inverter' => $id]);
    }
}
