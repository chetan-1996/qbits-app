<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InverterFault;

class InverterFaultController extends BaseController
{
    public function index(Request $request)
    {
        $query = InverterFault::query()
            ->select('*'); // select only needed columns

        // Filter by inverter_id
        if ($request->filled('inverter_id')) {
            $query->where('inverter_id', $request->inverter_id);
        }

        // Filter by plant_id
        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }

        if ($request->filled('status') != -1) {
            $query->where('status', $request->status);
        }
        // Limit (default 20)
        $limit = (int) $request->get('limit', 20);

        // Fastest pagination
        $faults = $query->simplePaginate($limit);

        return $this->sendResponse([
            'faults' => $faults
        ], 'Inverter fault list fetched successfully');
    }
}
