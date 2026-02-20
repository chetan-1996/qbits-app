<?php

namespace App\Http\Controllers\API\V1;

use App\Services\MqttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Inverter;
use App\Models\InverterDetail;
use App\Models\Client;

class InverterController extends BaseController
{
    public function sendCommand(Request $request)
    {
        $mqtt = new MqttService();

        $clientId = config('mqtt.client_id_prefix') . '-publisher';
        $mqtt->connect($clientId);

        $topic = "inverter/{$request->inverter_id}/command";

        $payload = json_encode([
            'action' => $request->action,
            'value'  => $request->value,
        ]);

        $mqtt->publish($topic, $payload);

        return response()->json([
            'status' => 'Command sent',
            'topic'  => $topic,
        ]);
    }

    /*public function sendCommand(Request $request, $id)
    {
        $command = $request->all();
        $topic = "inverters/{$id}/command";

        app(MqttService::class)->publish($topic, $command, 1); // QoS 1

        return response()->json(['status' => 'sent', 'inverter' => $id]);
    }*/

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
        $rows = Inverter::
        // with('latestDetail')
             with([
                'latestDetail',
                'plant:id,plant_name,plant_no,country,city,plantstate,capacity,acpower,eday,etot,kpi,month_power,year_power'
            ])
            ->where('plant_id', $request->plantId)
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function inverter_data_details_list(Request $request)
    {
        $rows = Inverter::
        // with(['latestDetail', 'plant'])
            with([
                'latestDetail',
                'plant:id,plant_name,plant_no,country,city,plantstate,capacity,acpower,eday,etot,kpi,month_power,year_power'
            ])
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function frontendIndex(Request $request)
    {
        $rows = DB::table('inverters')
            ->where('plant_id', $request->plantId)
            ->orderBy('id')
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function frontend_inverter_data(Request $request)
    {
        $rows = DB::table('inverter_details')
            ->where('inverterId', $request->inverterId)
            ->orderBy('inverterId')
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function frontend_inverter_data_details(Request $request)
    {
        $rows = Inverter::
        // with('latestDetail')
            with([
                'latestDetail',
                'plant:id,plant_name,plant_no,country,city,plantstate,capacity,acpower,eday,etot,kpi,month_power,year_power'
            ])
            ->where('plant_id', $request->plantId)
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function frontend_inverter_data_details_list(Request $request)
    {
        $user = Auth::user();

        $companyId=[$user->id];
        if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
            $companyId = Client::where('qbits_company_code', $user->qbits_company_code)
                    // ->where('user_flag', 0)
                    ->pluck('id')
                    ->all();
        }

        $rows = Inverter::
        // with(['latestDetail', 'plant'])
            with([
                'latestDetail',
                'plant:id,plant_name,plant_no,country,city,plantstate,capacity,acpower,eday,etot,kpi,month_power,year_power'
            ])
            ->whereIn('user_id', $companyId)
            ->get();

        return $this->sendResponse([
            'inverters' => $rows,
        ], 'Inverter list fetched');
    }

    public function teleHistory(Request $request)
    {
        $collectorId = $request->collector_id;

        $data = DB::table('telemetry_raw')
            ->select(['id', 'collector_id', 'payload', 'created_at'])
            ->when($collectorId, function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId);
            })
            ->orderByDesc('id')
            ->simplePaginate(50);

        foreach ($data->items() as $row) {
            $row->payload = json_decode($row->payload, true);
        }

        return $this->sendResponse([
            'telemetry' => $data,
        ], 'telemetry list fetched');
    }

    public function ackHistory(Request $request)
    {
        $collectorId = $request->collector_id;

        $data = DB::table('device_ack')
            ->select(['id', 'collector_id', 'payload', 'created_at'])
            ->when($collectorId, function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId);
            })
            ->orderByDesc('id')
            ->simplePaginate(50);

        foreach ($data->items() as $row) {
            $row->payload = json_decode($row->payload, true);
        }

        return $this->sendResponse([
            'ack' => $data,
        ], 'ack list fetched');
    }
}
