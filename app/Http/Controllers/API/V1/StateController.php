<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StateController extends BaseController
{
    /**
     * Get all active states
     */
    public function index()
    {
        $states = Cache::rememberForever('states:list', function () {
            return DB::table('states')
                ->select(['id', 'name'])
                ->where('status', 1)
                ->orderBy('name')
                ->get();
        });

        return $this->sendResponse($states, 'States fetched successfully.');
    }

    /**
     * Get cities by state ID
     */
    public function cityList(int $stateId)
    {
        $cities = Cache::rememberForever("cities:state:{$stateId}", function () use ($stateId) {
            return DB::table('cities')
                ->select(['id', 'name'])
                ->where('state_id', $stateId)
                ->where('status', 1)
                ->orderBy('name')
                ->get();
        });

        return $this->sendResponse($cities, 'Cities fetched successfully.');
    }
}
