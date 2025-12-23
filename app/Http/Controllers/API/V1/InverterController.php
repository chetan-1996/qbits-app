<?php

namespace App\Http\Controllers\API\V1;

use App\Services\MqttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Inverter;
use App\Models\InverterDetail;

class InverterController extends BaseController
{
    public function sendCommand(Request $request, $id)
    {
        $command = $request->all();
        $topic = "inverters/{$id}/command";

        app(MqttService::class)->publish($topic, $command, 1); // QoS 1

        return response()->json(['status' => 'sent', 'inverter' => $id]);
    }

    public function index(Request $request)
    {
        $rows = DB::table('inverters')
            ->where('plant_id', $request->plantId)
            ->orderBy('id')
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function inverter_data(Request $request)
    {
        $rows = DB::table('inverter_details')
            ->where('inverterId', $request->inverterId)
            ->orderBy('inverterId')
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function inverter_data_details(Request $request)
    {
        $rows = Inverter::with('latestDetail')
            ->where('plant_id', $request->plantId)
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

}
