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
     * Display modbus write history in view
     */
    public function modbusWriteView(Request $request)
    {
        $data = $this->getTelemetryData('modbus_write_logs', $request);
        return view('telemetry.modbus-write', ['telemetry' => $data]);
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
     * Display telemetry chart page
     */
    public function chart(Request $request)
    {
        $collectorId = $request->collector_id;
        $dateFrom    = $request->date_from ?? now()->subDays(7)->format('Y-m-d');
        $dateTo      = $request->date_to   ?? now()->format('Y-m-d');

        return view('telemetry.chart', compact('collectorId', 'dateFrom', 'dateTo'));
    }

    /**
     * Get telemetry chart data (API)
     */
    public function chartData(Request $request)
    {
        $request->validate([
            'collector_id' => 'required|string',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date',
            'parameters'   => 'required|array|min:1',
            'parameters.*' => 'string',
            'normalize'    => 'boolean',
            'x_axis'       => 'nullable|string|in:created_at,TIMESTAMP',
        ]);

        $collectorId = $request->collector_id;
        $dateFrom    = $request->date_from;
        $dateTo      = $request->date_to;
        $parameters  = $request->parameters;
        $normalize   = $request->boolean('normalize', false);
        $xAxis       = $request->input('x_axis', 'created_at');

        $data = DB::table('telemetry_raw')
            ->select(['id', 'collector_id', 'payload', 'created_at'])
            ->where('collector_id', $collectorId)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->orderBy('created_at')
            ->get();

        $payloadKeyMap = [
            'I' => 'IS-1-0---I', 'PF' => 'IS-1-0---PF', 'VN' => 'IS-1-0---VN',
            'FT1' => 'IS-1-0---FT1', 'FT2' => 'IS-1-0---FT2', 'FT3' => 'IS-1-0---FT3',
            'FT4' => 'IS-1-0---FT4', 'FT5' => 'IS-1-0---FT5',
            'IST' => 'IS-1-0---IST', 'LON' => 'IS-1-0---LON', 'POW' => 'IS-1-0---POW',
            'TON' => 'IS-1-0---TON', 'APOW' => 'IS-1-0---APOW',
            'BPHI' => 'IS-1-0---BPHI', 'BPHV' => 'IS-1-0---BPHV',
            'DCI1' => 'IS-1-0---DCI1', 'DCV1' => 'IS-1-0---DCV1',
            'FREQ' => 'IS-1-0---FREQ', 'LKWH' => 'IS-1-0---LKWH',
            'POWB' => 'IS-1-0---POWB', 'POWR' => 'IS-1-0---POWR', 'POWY' => 'IS-1-0---POWY',
            'RPHI' => 'IS-1-0---RPHI', 'RPHV' => 'IS-1-0---RPHV', 'RPOW' => 'IS-1-0---RPOW',
            'TEMP' => 'IS-1-0---TEMP', 'TKWH' => 'IS-1-0---TKWH',
            'YPHI' => 'IS-1-0---YPHI', 'YPHV' => 'IS-1-0---YPHV', 'DCKW1' => 'IS-1-0---DCKW1',
        ];

        $rows = [];

        foreach ($data as $row) {
            $payload = json_decode($row->payload, true);
            $label = $xAxis === 'TIMESTAMP'
                ? ($payload['TIMESTAMP'] ?? Carbon::parse($row->created_at)->format('Y-m-d H:i:s'))
                : Carbon::parse($row->created_at)->format('Y-m-d H:i:s');

            $paramValues = [];
            foreach ($parameters as $param) {
                $payloadKey = $payloadKeyMap[$param] ?? $param;
                $value = $payload[$payloadKey] ?? null;
                $paramValues[$param] = is_numeric($value) ? (float) $value : null;
            }

            $rows[] = [
                'label'      => $label,
                'values'     => $paramValues,
                'sort_key'   => $xAxis === 'TIMESTAMP'
                    ? (is_numeric($payload['TIMESTAMP'] ?? null) ? (float) $payload['TIMESTAMP'] : $row->created_at)
                    : $row->created_at,
            ];
        }

        // Sort by the chosen X-axis
        usort($rows, function ($a, $b) {
            if (is_numeric($a['sort_key']) && is_numeric($b['sort_key'])) {
                return $a['sort_key'] <=> $b['sort_key'];
            }
            return strtotime($a['sort_key']) <=> strtotime($b['sort_key']);
        });

        $labels = [];
        $rawData = [];
        foreach ($parameters as $param) {
            $rawData[$param] = [];
        }

        foreach ($rows as $row) {
            $labels[] = $row['label'];
            foreach ($parameters as $param) {
                $rawData[$param][] = $row['values'][$param];
            }
        }

        $chartDatasets = [];
        $colors = [
            '#0d6efd', '#dc3545', '#198754', '#ffc107', '#0dcaf0',
            '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6f42c1',
            '#17a2b8', '#28a745', '#dc3545', '#007bff', '#6c757d',
            '#f8f9fa', '#343a40', '#e9ecef', '#adb5bd', '#212529',
            '#495057', '#ced4da', '#868e96', '#d63384', '#6f42c1',
            '#0d6efd', '#6610f2', '#20c997', '#0dcaf0', '#ffc107',
            '#198754', '#dc3545', '#fd7e14', '#e83e8c', '#6f42c1',
            '#17a2b8', '#28a745', '#007bff', '#6c757d', '#495057',
        ];

        $idx = 0;
        foreach ($rawData as $param => $values) {
            $plotValues = $values;

            // Normalize each parameter to its own 0-100% scale
            if ($normalize) {
                $numericValues = array_filter($values, fn($v) => $v !== null);
                if (count($numericValues) > 0) {
                    $min = min($numericValues);
                    $max = max($numericValues);
                    $range = $max - $min;
                    $plotValues = array_map(function ($v) use ($min, $range) {
                        if ($v === null) return null;
                        if ($range == 0) return 50.0;
                        return round((($v - $min) / $range) * 100, 2);
                    }, $values);
                }
            }

            $chartDatasets[] = [
                'label'           => $param,
                'data'            => $plotValues,
                'borderColor'     => $colors[$idx % count($colors)],
                'backgroundColor'   => $colors[$idx % count($colors)] . '20',
                'borderWidth'     => 2,
                'pointRadius'     => 2,
                'pointHoverRadius'=> 5,
                'tension'         => 0.1,
                'fill'            => false,
            ];
            $idx++;
        }

        return $this->sendResponse([
            'labels'     => $labels,
            'datasets'   => $chartDatasets,
            'count'      => count($labels),
            'normalized' => $normalize,
        ], 'Chart data fetched successfully');
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

