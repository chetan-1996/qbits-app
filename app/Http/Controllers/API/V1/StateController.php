<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\State;

class StateController extends BaseController
{
    public function index()
    {
        $states = Cache::remember('states_all_active', 3600, function () {
            return State::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id','name']);
        });

        return $this->sendResponse($states, 'States fetched successfully.');
    }
}
