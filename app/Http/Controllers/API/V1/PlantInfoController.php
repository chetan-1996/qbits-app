<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PlantInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
                'remark1','date','watch','time'
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
        $plant = PlantInfo::find($id);

        if (!$plant) {
            return response()->json([
                'status' => false,
                'message' => 'Plant not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Plant fetched successfully',
            'data' => $plant
        ]);
    }
}
