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
        $data = $this->getTelemetryData1('telemetry_raw', $request);
        return view('telemetry.history', ['telemetry' => $data]);
    }

    private function getTelemetryData1(string $tableName, Request $request)
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
     * Display filtered telemetry history with date range in view
     */
    public function filteredIndex(Request $request)
    {
        $data = $this->getTelemetryData('telemetry_raw', $request);
        return view('telemetry.history-filtered', ['telemetry' => $data]);
    }

    /**
     * Export filtered telemetry history as CSV
     */
    public function exportFiltered(Request $request)
    {
        $data = $this->getExportData('telemetry_raw', $request);

        $filename = 'telemetry-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = [
            'ID','Created At', 'IMEI', 'VD', 'DATE', 'LOAD', 'CMKEY', 'INDEX', 'MSGID', 'PMKEY',
            'ASN_31', 'FW_VER', 'MAXINDEX', 'TIMESTAMP', 'STINTERVAL',
            'IS-1-0---I', 'IS-1-0---PF', 'IS-1-0---VN',
            'IS-1-0---FT1', 'IS-1-0---FT2', 'IS-1-0---FT3', 'IS-1-0---FT4', 'IS-1-0---FT5',
            'IS-1-0---IST', 'IS-1-0---LON', 'IS-1-0---POW', 'IS-1-0---TON',
            'IS-1-0---APOW', 'IS-1-0---BPHI', 'IS-1-0---BPHV',
            'IS-1-0---DCI1', 'IS-1-0---DCV1', 'IS-1-0---FREQ', 'IS-1-0---LKWH',
            'IS-1-0---POWB', 'IS-1-0---POWR', 'IS-1-0---POWY',
            'IS-1-0---RPHI', 'IS-1-0---RPHV', 'IS-1-0---RPOW',
            'IS-1-0---TEMP', 'IS-1-0---TKWH',
            'IS-1-0---YPHI', 'IS-1-0---YPHV', 'IS-1-0---DCKW1',
            
        ];

        $callback = function () use ($data, $columns) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $columns);

            foreach ($data as $row) {
                $p = $row->payload ?? [];
                fputcsv($handle, [
                    $row->id,
                    $row->created_at,
                    $row->collector_id,
                    $p['VD'] ?? '',
                    $p['DATE'] ?? '',
                    $p['LOAD'] ?? '',
                    $p['CMKEY'] ?? '',
                    $p['INDEX'] ?? '',
                    $p['MSGID'] ?? '',
                    $p['PMKEY'] ?? '',
                    $p['ASN_31'] ?? '',
                    $p['FW_VER'] ?? '',
                    $p['MAXINDEX'] ?? '',
                    $p['TIMESTAMP'] ?? '',
                    $p['STINTERVAL'] ?? '',
                    $p['IS-1-0---I'] ?? '',
                    $p['IS-1-0---PF'] ?? '',
                    $p['IS-1-0---VN'] ?? '',
                    $p['IS-1-0---FT1'] ?? '',
                    $p['IS-1-0---FT2'] ?? '',
                    $p['IS-1-0---FT3'] ?? '',
                    $p['IS-1-0---FT4'] ?? '',
                    $p['IS-1-0---FT5'] ?? '',
                    $p['IS-1-0---IST'] ?? '',
                    $p['IS-1-0---LON'] ?? '',
                    $p['IS-1-0---POW'] ?? '',
                    $p['IS-1-0---TON'] ?? '',
                    $p['IS-1-0---APOW'] ?? '',
                    $p['IS-1-0---BPHI'] ?? '',
                    $p['IS-1-0---BPHV'] ?? '',
                    $p['IS-1-0---DCI1'] ?? '',
                    $p['IS-1-0---DCV1'] ?? '',
                    $p['IS-1-0---FREQ'] ?? '',
                    $p['IS-1-0---LKWH'] ?? '',
                    $p['IS-1-0---POWB'] ?? '',
                    $p['IS-1-0---POWR'] ?? '',
                    $p['IS-1-0---POWY'] ?? '',
                    $p['IS-1-0---RPHI'] ?? '',
                    $p['IS-1-0---RPHV'] ?? '',
                    $p['IS-1-0---RPOW'] ?? '',
                    $p['IS-1-0---TEMP'] ?? '',
                    $p['IS-1-0---TKWH'] ?? '',
                    $p['IS-1-0---YPHI'] ?? '',
                    $p['IS-1-0---YPHV'] ?? '',
                    $p['IS-1-0---DCKW1'] ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get all (non-paginated) filtered telemetry for export
     */
    private function getExportData(string $tableName, Request $request)
    {
        $collectorId = $request->collector_id;
        $dateFrom    = $request->date_from;
        $dateTo      = $request->date_to;

        $data = DB::table($tableName)
            ->select(['id', 'collector_id', 'payload', 'created_at'])
            ->when($collectorId, function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId);
            })
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->whereDate('created_at', '<=', $dateTo);
            })
            ->orderByDesc('created_at')
            ->get();

        foreach ($data as $row) {
            $row->payload = json_decode($row->payload, true);
        }

        return $data;
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
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $data = DB::table($tableName)
            ->select(['id', 'collector_id', 'payload', 'created_at'])
            ->when($collectorId, function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId);
            })
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->whereDate('created_at', '<=', $dateTo);
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

