<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TelemetryController extends BaseController
{
    /**
     * Display telemetry history in view
     */
    public function index(Request $request)
    {
        $data = $this->getTelemetryData('telemetry_raw', $request);
        return view('telemetry.history', ['telemetry' => $data]);
    }

    /**
     * Display telemetry heartbeat in view
     */
    public function heartbeatView(Request $request)
    {
        $data = $this->getTelemetryData('telemetry_heartbeat', $request);
        return view('telemetry.heartbeat', ['telemetry' => $data]);
    }

    /**
     * Get telemetry history with generic table handling
     */
    private function getTelemetryData(string $tableName, Request $request)
    {
        $collectorId = $request->collector_id;

        $data = DB::table($tableName)
            ->select(['id', 'collector_id', 'payload', 'created_at'])
            ->when($collectorId, function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId);
            })
            ->orderByDesc('created_at')
            ->simplePaginate(50);

        foreach ($data->items() as $row) {
            $row->payload = json_decode($row->payload, true);
            $row->created_at = Carbon::parse($row->created_at);
        }

        return $data;
    }

    /**
     * Get telemetry_raw history (API)
     */
    public function teleHistory(Request $request)
    {
        $data = $this->getTelemetryData('telemetry_raw', $request);

        return $this->sendResponse([
            'telemetry' => $data,
        ], 'telemetry list fetched');
    }

    /**
     * Get telemetry_heartbeat history (API)
     */
    public function teleHeartbeatHistory(Request $request)
    {
        $data = $this->getTelemetryData('telemetry_heartbeat', $request);

        return $this->sendResponse([
            'telemetry' => $data,
        ], 'telemetry list fetched');
    }

    /**
     * Get acknowledgment history (API)
     */
    public function ackHistory(Request $request)
    {
        $collectorId = $request->collector_id;

        $data = DB::table('device_ack')
            ->select('*')
            ->when($collectorId, function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId);
            })
            ->orderByDesc('id')
            ->simplePaginate(50);

        return $this->sendResponse([
            'ack' => $data,
        ], 'ack list fetched');
    }
}

