<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PlantInfo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PlantInfoController extends BaseController
{
    /**
     * GET /api/v1/plants
     * List plants with filters + pagination
     */
    public function index($id)
    {
        $limit = request('limit', 20);
        $page  = max((int) request('page', 1), 1);
        // $search = request('search');

        // 1. FETCH CURSOR FOR PAGE
        $cursorKey = "pi_cursor_map:{$id}:p{$page}";
        $cursor = Cache::get($cursorKey, 0);

        // 2. FETCH DATA FOR THIS PAGE (KEYSET)
        $rows = DB::table('plant_infos')
            ->select([
                'id','plant_no','plant_name','capacity','acpower',
                'eday','etot','kpi','month_power','year_power',
                'remark1','date','watch','time','plantstate'
            ])
            ->where('user_id', $id)
            ->when($cursor > 0, fn($q) => $q->where('id', '>', $cursor))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array) $r);

        // 3. STORE CURSOR FOR NEXT PAGE
        $nextCursor = $rows->last()['id'] ?? null;
        Cache::put("pi_cursor_map:{$id}:p" . ($page + 1), $nextCursor, 3600);

        // 4. TOTAL COUNT (CACHED)
        $totalRows = Cache::remember("pi_total_{$id}", 300, function () use ($id) {
            return DB::table('plant_infos')->where('user_id', $id)->count();
        });

        $totalPages = ceil($totalRows / $limit);

        return $this->sendResponse([
            'plants' => $rows,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total_rows'  => $totalRows,
                'total_pages' => $totalPages,
                'next_page'   => $page < $totalPages ? $page + 1 : null,
                'prev_page'   => $page > 1 ? $page - 1 : null,
            ]
        ], 'Plant list fetched');
    }




    /**
     * GET /api/v1/plants/{id}
     * Show single plant entry
     */
    public function show($id)
    {
        // $plant = PlantInfo::find($id);

        $plant = PlantInfo::where('plant_no',$id)->first();

        if (!$plant) {
            return $this->sendError('Plant not found', [], 400);
        }

        return $this->sendResponse([
            'plants' => $plant,
        ], 'Plant fetched successfully');
    }

    public function byDay(Request $request)
    {
        $request->validate([
            'startTime' => 'required|date',
            'plantId'   => 'required|integer',
            'atun'      => 'required|string',
            'atpd'      => 'required|string',
        ]);

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
            ->timeout(20)
            ->get(
                'https://www.aotaisolarcloud.com/ATSolarInfo/appcanPlantStatisticsByDay.action',
                [
                    'startTime' => $request->startTime,
                    'plantId'   => $request->plantId,
                    'atun'      => $request->atun,
                    'atpd'      => $request->atpd,
                ]
            );

            if (!$response->successful()) {
                return $this->sendError('Aotai API failed', [], 400);
            }

            return $this->sendResponse([
                'byday' => $response->json(),
            ], 'Plant fetched successfully');


        } catch (\Throwable $e) {
            return $this->sendError('Plant not found', [$e->getMessage()], 400);
        }
    }

    public function byMonth(Request $request)
    {
        $request->validate([
            'startTime' => 'required|date',
            'plantId'   => 'required|integer',
            'atun'      => 'required|string',
            'atpd'      => 'required|string',
        ]);

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
            ->timeout(20)
            ->get(
                'https://www.aotaisolarcloud.com/ATSolarInfo/appcanPlantStatisticsByMonth.action',
                [
                    'startTime' => $request->startTime,
                    'plantId'   => $request->plantId,
                    'atun'      => $request->atun,
                    'atpd'      => $request->atpd,
                ]
            );

            if (!$response->successful()) {
                return $this->sendError('Aotai API failed', [], 400);
            }

            return $this->sendResponse([
                'bymonth' => $response->json(),
            ], 'Plant fetched successfully');


        } catch (\Throwable $e) {
            return $this->sendError('Plant not found', [$e->getMessage()], 400);
        }
    }

    public function byYear(Request $request)
    {

        $request->validate([
            'startTime' => 'required',
            'plantId'   => 'required|integer',
            'atun'      => 'required|string',
            'atpd'      => 'required|string',
        ]);

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
            ->timeout(20)
            ->get(
                'https://www.aotaisolarcloud.com/ATSolarInfo/appcanPlantStatisticsByYear.action',
                [
                    'startTime' => $request->startTime,
                    'plantId'   => $request->plantId,
                    'atun'      => $request->atun,
                    'atpd'      => $request->atpd,
                ]
            );

            if (!$response->successful()) {
                return $this->sendError('Aotai API failed', [], 400);
            }

            return $this->sendResponse([
                'byyear' => $response->json(),
            ], 'Plant fetched successfully');


        } catch (\Throwable $e) {
            return $this->sendError('Plant not found', [$e->getMessage()], 400);
        }
    }

    public function byTotal(Request $request)
    {

        $request->validate([
            'startTime' => 'required',
            'plantId'   => 'required|integer',
            'atun'      => 'required|string',
            'atpd'      => 'required|string',
        ]);

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
            ->timeout(20)
            ->get(
                'https://www.aotaisolarcloud.com/ATSolarInfo/requestPlantEnergyList.action',
                [
                    'plantId'   => $request->plantId,
                    'atun'      => $request->atun,
                    'atpd'      => $request->atpd,
                ]
            );

            if (!$response->successful()) {
                return $this->sendError('Aotai API failed', [], 400);
            }

            return $this->sendResponse([
                'bytotal' => $response->json(),
            ], 'Plant fetched successfully');


        } catch (\Throwable $e) {
            return $this->sendError('Plant not found', [$e->getMessage()], 400);
        }
    }
}
