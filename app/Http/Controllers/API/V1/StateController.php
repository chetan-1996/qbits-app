<?php

namespace App\Http\Controllers\API\V1;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class StateController extends BaseController
{
    /**
     * Get all active states
     */
    public function index()
    {
        $states = Cache::remember('states:list', 86400, function () {
            return DB::table('states')
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        return $this->sendResponse($states, 'States fetched successfully.');
    }

    /**
     * Get cities by state ID
     */
    public function cityList(int $stateId)
    {
        $cacheKey = "cities:state:$stateId";

        $cities = Cache::remember($cacheKey, 86400, function () use ($stateId) {
            return DB::table('cities')
                ->where('state_id', $stateId)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        return $this->sendResponse($cities, 'Cities fetched successfully.');
    }

    /**
     * Store city
     */
    public function store(Request $request)
    {
        $input = $request->all();

        // normalize city name
        $input['name'] = ucfirst(strtolower(trim($input['name'] ?? '')));

        $validator = Validator::make($input, [
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cities')->where(fn ($q) =>
                    $q->where('state_id', $input['state_id'])
                ),
            ],
            'status' => ['nullable', 'boolean']
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        try {

            $city = City::create([
                'state_id' => $input['state_id'],
                'name' => $input['name'],
                'status' => $input['status'] ?? true,
            ]);

            Cache::forget("cities:state:{$input['state_id']}");

            return $this->sendResponse($city, 'City created successfully.');

        } catch (QueryException $e) {

            return $this->sendError('Validation Error.', [
                'name' => ['The city already exists for this state.']
            ], 400);
        }
    }
}
