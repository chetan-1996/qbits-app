<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InverterFault;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;

class InverterFaultController extends BaseController
{
    public function index(Request $request)
    {
        $query = InverterFault::query()
        ->with([
            'inverter:id,plant_id,inverter_no,model,state',
            'inverter.plant:plant_name,plant_no,country,city'
        ])
            ->select('*'); // select only needed columns

        // Filter by inverter_id
        if ($request->filled('inverter_id')) {
            $query->where('inverter_id', $request->inverter_id);
        }

        // Filter by plant_id
        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }

        if ($request->status != -1) {
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

    public function frontendIndex(Request $request)
    {
        $user = Auth::user();

        $companyId=[$user->id];
        if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
            $companyId = Client::where('qbits_company_code', $user->qbits_company_code)
                    // ->where('user_flag', 0)
                    ->pluck('id')
                    ->all();
        }
        $query = InverterFault::query()
        ->with([
            'inverter:id,plant_id,inverter_no,model,state',
            'inverter.plant:id,plant_name,plant_no,country,city'
        ])
            ->select('*')->whereIn('user_id', $companyId); // select only needed columns

        // Filter by inverter_id
        if ($request->filled('inverter_id')) {
            $query->where('inverter_id', $request->inverter_id);
        }

        // Filter by plant_id
        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }

        if ($request->status != -1) {
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
