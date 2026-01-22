<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class ClientController extends BaseController
{
    public function clientLogin(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $client = Client::where('username', $request->username)->first();

        if (!$client || $client->password !== $request->password) {
            return response()->json([
                'message' => 'Invalid username or password'
            ], 401);
        }

        // $companyId=$client->id;
        // if ($client->user_flag == 1) {

        //     // if (!$request->filled('qbits_company_code')) {
        //     //     return response()->json(['message' => 'Company code required'], 422);
        //     // }

        //     $companyId = Client::where('qbits_company_code', $client->qbits_company_code)
        //         // ->where('user_flag', 0)
        //         ->pluck('id')
        //         ->all();

        //     if (!$companyId) {
        //         return response()->json(['message' => 'Invalid company code'], 401);
        //     }

        //     // Runtime attach (DB ma save karvani jarur nathi)
        // }
        // $client->company_id = $companyId;

        $token = $client->createToken('client-token')->plainTextToken;

        $success['access_token'] =  $token;
        $success['name'] =  $client->name;
        $success['user'] = $client;
        $success['token_type'] = 'Bearer';

        return $this->sendResponse($success, 'User login successfully.');

        // return response()->json([
        //     'token'  => $token,
        //     'client' => $client,
        // ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return $this->sendResponse([], 'Logged out successfully.');
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Logout failed');
        }
    }
    // public function clientLogin(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required',
    //         'password' => 'required',
    //     ]);

    //     $client = \App\Models\Client::where('email', $request->email)->first();

    //     if (!$client || !Hash::check($request->password, $client->password)) {
    //         return response()->json(['message' => 'Invalid credentials'], 401);
    //     }

    //     $token = $client->createToken('client-token')->plainTextToken;

    //     return response()->json([
    //         'token' => $token,
    //         'client' => $client,
    //     ]);
    // }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'page'     => 'integer|min:1',
                'per_page' => 'integer|min:10|max:500',
                'search'   => 'nullable|string|max:100',
                'sort'     => 'nullable|in:id,name,email,created_at',
                'dir'      => 'nullable|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 1;
            $sort    = $validated['sort'] ?? 'id';
            $dir     = $validated['dir'] ?? 'desc';
            $search  = $validated['search'] ?? null;

            $query = Client::select('*')
                //->whereNull('qbits_company_code')
                ->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('qbits_company_code')
                            ->whereNull('dealer_id');
                    })
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('qbits_company_code')
                            ->whereNotNull('dealer_id');
                    });
                })
                ->when($search, function ($q) use ($search) {
                    // ✅ Use full-text or LIKE search depending on index
                    $q->where(function ($sub) use ($search) {
                        $sub->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $dir);

            // ✅ Use cursor pagination for large data
            $clients = $query->cursorPaginate($perPage);

            // return response()->json([
            //     'success'      => true,
            //     'message'      => 'Clients fetched successfully',
            //     'per_page'     => $clients->perPage(),
            //     'next_cursor'  => $clients->nextCursor()?->encode(),
            //     'prev_cursor'  => $clients->previousCursor()?->encode(),
            //     'data'         => $clients->items(),
            // ], 200);

            return $this->sendResponse([
                'per_page'     => $clients->perPage(),
                'next_cursor'  => $clients->nextCursor()?->encode(),
                'prev_cursor'  => $clients->previousCursor()?->encode(),
                'clients'      => $clients->items(),
            ], 'Clients fetched successfully.');

        } catch (Exception $e) {
            Log::error('Client List Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
            ], 500);
        } finally {
            gc_collect_cycles(); // memory cleanup
        }
    }

     public function companyUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'page'     => 'integer|min:1',
                'per_page' => 'integer|min:10|max:500',
                'search'   => 'nullable|string|max:100',
                'sort'     => 'nullable|in:id,name,email,created_at',
                'dir'      => 'nullable|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 1;
            $sort    = $validated['sort'] ?? 'id';
            $dir     = $validated['dir'] ?? 'desc';
            $search  = $validated['search'] ?? null;

            $query = Client::select('*')
                ->where('user_flag',1)
                // ->whereNotNull('qbits_company_code')->whereNull('dealer_id')
                ->when($search, function ($q) use ($search) {
                    // ✅ Use full-text or LIKE search depending on index
                    $q->where(function ($sub) use ($search) {
                        $sub->where('username', 'like', "%{$search}%")
                            ->orwhere('company_name', 'like', "%{$search}%")
                            ->orwhere('qbits_company_code', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $dir);
//                 dd([
//     'sql' => $query->toSql(),
//     'bindings' => $query->getBindings()
// ]);
// dd($query);
            // ✅ Use cursor pagination for large data
            $clients = $query->cursorPaginate($perPage);

            // return response()->json([
            //     'success'      => true,
            //     'message'      => 'Clients fetched successfully',
            //     'per_page'     => $clients->perPage(),
            //     'next_cursor'  => $clients->nextCursor()?->encode(),
            //     'prev_cursor'  => $clients->previousCursor()?->encode(),
            //     'data'         => $clients->items(),
            // ], 200);

            return $this->sendResponse([
                'per_page'     => $clients->perPage(),
                'next_cursor'  => $clients->nextCursor()?->encode(),
                'prev_cursor'  => $clients->previousCursor()?->encode(),
                'clients'      => $clients->items(),
            ], 'Clients fetched successfully.');

        } catch (Exception $e) {
            Log::error('Client List Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
            ], 500);
        } finally {
            gc_collect_cycles(); // memory cleanup
        }
    }

    public function postWhatsAppNotificationUpdate(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:clients,id',
        ]);

        $id = $validated['id'];

        // Allowed fields that can be updated
        $allowedFlags = [
            'whatsapp_notification_flag',
            'inverter_fault_flag',
            'daily_generation_report_flag',
            'weekly_generation_report_flag',
            'monthly_generation_report_flag',
        ];

        try {
            // Filter and sanitize only allowed keys
            $updateData = collect($request->only($allowedFlags))
                ->map(fn($value) => (int)($value ?? 0))
                ->toArray();

            // Always update timestamp
            $updateData['updated_at'] = now();

            // ✅ Update record in DB
            DB::table('clients')->where('id', $id)->update($updateData);

            $response =$updateData;


            // ✅ Free used variables
            unset($id, $updateData, $allowedFlags, $validated, $request);

            return $this->sendResponse($response, 'Saved successfully.');
        } catch (\Exception $e) {
            \Log::error('Qbits Notification Update Error: ' . $e->getMessage());
            return $this->sendError('Database operation failed', [], 400);
        } finally {
            // ✅ Release DB connection and collect garbage memory
            DB::disconnect();
            gc_collect_cycles();
        }
    }


    public function setCompanyCodeToIndivisualUser(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'company_code' => 'required|string',
        ]);

        $dealer_id = null;
        $user_cpy = Client::where('qbits_company_code', $validated['company_code'])->first();
        if (!$user_cpy)
        {
            return $this->sendError('Company code is invalid', [], 400);
        }
         $dealer_id=$user_cpy->id;

        $id = $validated['id'];



        try {

            // Always update timestamp
            $updateData['qbits_company_code'] = $validated['company_code'];
            $updateData['dealer_id'] = $dealer_id;
            $updateData['updated_at'] = now();

            // ✅ Update record in DB
            DB::table('clients')->where('id', $id)->update($updateData);

            $response =$updateData;


            // ✅ Free used variables
            unset($id, $updateData, $validated, $request);

            return $this->sendResponse($response, 'Saved successfully.');
        } catch (\Exception $e) {
            \Log::error('Qbits Notification Update Error: ' . $e->getMessage());
            return $this->sendError('Database operation failed', [], 400);
        } finally {
            // ✅ Release DB connection and collect garbage memory
            DB::disconnect();
            gc_collect_cycles();
        }
    }

    public function totals()
    {
        // FAST: Single SQL query – no loops, no memory usage
        $result = DB::table('inverter_status')->selectRaw('
            SUM(all_plant) as total_all_plant,
            SUM(normal_plant) as total_normal_plant,
            SUM(alarm_plant) as total_alarm_plant,
            SUM(offline_plant) as total_offline_plant
        ')->first();


        return $this->sendResponse($result, 'fetched successfully.');
    }

public function groupedClients(Request $request)
{
    $search   = $request->search;
    $perPage  = $request->per_page ?? 20;

    $pages = [
        'all'     => $request->page_all     ?? 1,
        'normal'  => $request->page_normal  ?? 1,
        'alarm'   => $request->page_alarm   ?? 1,
        'offline' => $request->page_offline ?? 1,
    ];

    // Cache key based on search + pagination
    $cacheKey = function($type, $page) use ($search, $perPage) {
        return "clients_{$type}_{$search}_{$page}_{$perPage}";
    };

    // Cached responses ↓ this avoids 90% DB hits
    $cached = function($key, $callback) {
        return Cache::remember($key, 1, $callback); // 1 minute cache
    };

    // Base Query
    $base = DB::table('clients as c')
        ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
        ->select(
            'c.*',
            DB::raw('COALESCE(s.all_plant,0)     AS all_plant'),
            DB::raw('COALESCE(s.normal_plant,0)  AS normal_plant'),
            DB::raw('COALESCE(s.alarm_plant,0)   AS alarm_plant'),
            DB::raw('COALESCE(s.offline_plant,0) AS offline_plant'),
            DB::raw('COALESCE(s.power,0) AS power'),
            DB::raw('COALESCE(s.capacity,0) AS capacity'),
            DB::raw('COALESCE(s.day_power,0) AS day_power'),
            DB::raw('COALESCE(s.month_power,0) AS month_power'),
            DB::raw('COALESCE(s.total_power,0) AS total_power'),
            's.updated_at'
        );

    if ($search) {
        $base->where(function ($q) use ($search) {
            $q->where('c.username', 'LIKE', "%{$search}%")
              ->orWhere('c.company_name', 'LIKE', "%{$search}%")
              ->orWhere('c.qbits_company_code', 'LIKE', "%{$search}%")
              ->orWhere('c.email', 'LIKE', "%{$search}%")
              ->orWhere('c.collector', 'LIKE', "%{$search}%")
              ->orWhere('c.plant_name', 'LIKE', "%{$search}%")
              ->orWhere('c.phone', 'LIKE', "%{$search}%");
        });
    }

    // Helper for pagination
    $paginate = function ($query, $pageName, $page) use ($perPage) {
        return $query->paginate($perPage, ['*'], $pageName, $page);
    };

    // Group-wise cached pagination
    $allPlant = $cached($cacheKey('all', $pages['all']), function () use ($base, $paginate, $pages) {
        return $paginate((clone $base), 'page_all', $pages['all']);
    });

    $normalPlant = $cached($cacheKey('normal', $pages['normal']), function () use ($base, $paginate, $pages) {
        return $paginate((clone $base)->where('s.normal_plant', '>', 0), 'page_normal', $pages['normal']);
    });

    $alarmPlant = $cached($cacheKey('alarm', $pages['alarm']), function () use ($base, $paginate, $pages) {
        return $paginate((clone $base)->where('s.alarm_plant', '>', 0), 'page_alarm', $pages['alarm']);
    });

    $offlinePlant = $cached($cacheKey('offline', $pages['offline']), function () use ($base, $paginate, $pages) {
        return $paginate((clone $base)->where('s.offline_plant', '>', 0), 'page_offline', $pages['offline']);
    });

    return $this->sendResponse([
        'all_plant'     => $allPlant,
        'normal_plant'  => $normalPlant,
        'alarm_plant'   => $alarmPlant,
        'offline_plant' => $offlinePlant
    ], 'MAX optimized client list.');
}

    // public function frontendTotals()
    // {


    //     $user = Auth::user();

    //     $companyId=[$user->id];
    //     if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
    //         $companyId = Client::where('qbits_company_code', $user->qbits_company_code)
    //                 // ->where('user_flag', 0)
    //                 ->pluck('id')
    //                 ->all();
    //     }

    //     // FAST: Single SQL query – no loops, no memory usage
    //     $result = DB::table('inverter_status')->selectRaw('
    //         SUM(all_plant) as total_all_plant,
    //         SUM(normal_plant) as total_normal_plant,
    //         SUM(alarm_plant) as total_alarm_plant,
    //         SUM(offline_plant) as total_offline_plant
    //     ')->whereIn('user_id', $companyId)->first();


    //     return $this->sendResponse($result, 'fetched successfully.');
    // }

    public function frontendTotals()
    {
        $user = Auth::user();

        $query = DB::table('inverter_status as s')
            ->join('clients as c', 'c.id', '=', 's.user_id')
            ->selectRaw('
                COALESCE(SUM(s.all_plant),0) as total_all_plant,
                COALESCE(SUM(s.normal_plant),0) as total_normal_plant,
                COALESCE(SUM(s.alarm_plant),0) as total_alarm_plant,
                COALESCE(SUM(s.offline_plant),0) as total_offline_plant
            ');

        if ($user->user_flag == 1 && !empty($user->qbits_company_code)) {
            $query->where('c.qbits_company_code', $user->qbits_company_code);
        } else {
            $query->where('c.id', $user->id);
        }

        $result = $query->first();

        return $this->sendResponse($result, 'fetched successfully.');
    }

    public function frontendGroupedClients1(Request $request)
    {

        $user = Auth::user();

        $companyId=[$user->id];
        if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
            $companyId = Client::where('qbits_company_code', $user->qbits_company_code)
                    // ->where('user_flag', 0)
                    ->pluck('id')
                    ->all();
        }
        $search   = $request->search;
        $perPage  = $request->per_page ?? 20;

        $pages = [
            'all'     => $request->page_all     ?? 1,
            'normal'  => $request->page_normal  ?? 1,
            'alarm'   => $request->page_alarm   ?? 1,
            'offline' => $request->page_offline ?? 1,
        ];

        // Cache key based on search + pagination
        $cacheKey = function($type, $page) use ($search, $perPage) {
            return "clients_{$type}_{$search}_{$page}_{$perPage}";
        };

        // Cached responses ↓ this avoids 90% DB hits
        $cached = function($key, $callback) {
            return Cache::remember($key, 1, $callback); // 1 minute cache
        };

        // Base Query
        $base = DB::table('clients as c')
            ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
            ->select(
                'c.*',
                DB::raw('COALESCE(s.all_plant,0)     AS all_plant'),
                DB::raw('COALESCE(s.normal_plant,0)  AS normal_plant'),
                DB::raw('COALESCE(s.alarm_plant,0)   AS alarm_plant'),
                DB::raw('COALESCE(s.offline_plant,0) AS offline_plant'),
                DB::raw('COALESCE(s.power,0) AS power'),
                DB::raw('COALESCE(s.capacity,0) AS capacity'),
                DB::raw('COALESCE(s.day_power,0) AS day_power'),
                DB::raw('COALESCE(s.month_power,0) AS month_power'),
                DB::raw('COALESCE(s.total_power,0) AS total_power'),
                's.updated_at'
            );
        $base->whereIn('c.id', $companyId);
        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('c.username', 'LIKE', "%{$search}%")
                ->orWhere('c.company_name', 'LIKE', "%{$search}%")
                ->orWhere('c.qbits_company_code', 'LIKE', "%{$search}%")
                ->orWhere('c.email', 'LIKE', "%{$search}%")
                ->orWhere('c.collector', 'LIKE', "%{$search}%")
                ->orWhere('c.plant_name', 'LIKE', "%{$search}%")
                ->orWhere('c.phone', 'LIKE', "%{$search}%");
            });
        }

        // Helper for pagination
        $paginate = function ($query, $pageName, $page) use ($perPage) {
            return $query->paginate($perPage, ['*'], $pageName, $page);
        };

        // Group-wise cached pagination
        $allPlant = $cached($cacheKey('all', $pages['all']), function () use ($base, $paginate, $pages) {
            return $paginate((clone $base), 'page_all', $pages['all']);
        });

        $normalPlant = $cached($cacheKey('normal', $pages['normal']), function () use ($base, $paginate, $pages) {
            return $paginate((clone $base)->where('s.normal_plant', '>', 0), 'page_normal', $pages['normal']);
        });

        $alarmPlant = $cached($cacheKey('alarm', $pages['alarm']), function () use ($base, $paginate, $pages) {
            return $paginate((clone $base)->where('s.alarm_plant', '>', 0), 'page_alarm', $pages['alarm']);
        });

        $offlinePlant = $cached($cacheKey('offline', $pages['offline']), function () use ($base, $paginate, $pages) {
            return $paginate((clone $base)->where('s.offline_plant', '>', 0), 'page_offline', $pages['offline']);
        });

        return $this->sendResponse([
            'all_plant'     => $allPlant,
            'normal_plant'  => $normalPlant,
            'alarm_plant'   => $alarmPlant,
            'offline_plant' => $offlinePlant
        ], 'MAX optimized client list.');
    }

    public function frontendGroupedClients(Request $request)
{
    $user = Auth::user();

    $companyId = [$user->id];
    if ($user->user_flag == 1 && !empty($user->qbits_company_code)) {
        $companyId = Client::where('qbits_company_code', $user->qbits_company_code)
            ->pluck('id')
            ->all();
    }

    $search  = trim($request->search ?? '');
    $perPage = (int) ($request->per_page ?? 20);

    $pages = [
        'all'     => (int) ($request->page_all     ?? 1),
        'normal'  => (int) ($request->page_normal  ?? 1),
        'alarm'   => (int) ($request->page_alarm   ?? 1),
        'offline' => (int) ($request->page_offline ?? 1),
    ];

    $companyHash = md5(json_encode($companyId));

    $cacheKey = function ($type, $page) use ($search, $perPage, $companyHash) {
        return "clients:{$companyHash}:{$type}:{$search}:{$page}:{$perPage}";
    };

    $cached = function ($key, $callback) {
        return Cache::remember($key, now()->addSeconds(30), $callback); // 30 sec enough
    };

    // ✅ Create fresh builder each time (NO clone)
    $makeQuery = function () use ($companyId, $search) {
        $q = DB::table('clients as c')
            ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
            ->whereIn('c.id', $companyId)
            ->select(
                'c.id',
                'c.username',
                'c.company_name',
                'c.phone',
                'c.email',
                'c.qbits_company_code',
                'c.plant_name',
                DB::raw('COALESCE(s.all_plant,0)     AS all_plant'),
                DB::raw('COALESCE(s.normal_plant,0)  AS normal_plant'),
                DB::raw('COALESCE(s.alarm_plant,0)   AS alarm_plant'),
                DB::raw('COALESCE(s.offline_plant,0) AS offline_plant'),
                DB::raw('COALESCE(s.power,0) AS power'),
                DB::raw('COALESCE(s.capacity,0) AS capacity'),
                DB::raw('COALESCE(s.day_power,0) AS day_power'),
                DB::raw('COALESCE(s.month_power,0) AS month_power'),
                DB::raw('COALESCE(s.total_power,0) AS total_power'),
                's.updated_at'
            );

        if (!empty($search)) {
            $q->where(function ($qq) use ($search) {
                $qq->where('c.username', 'LIKE', "%{$search}%")
                    ->orWhere('c.company_name', 'LIKE', "%{$search}%")
                    ->orWhere('c.qbits_company_code', 'LIKE', "%{$search}%")
                    ->orWhere('c.email', 'LIKE', "%{$search}%")
                    ->orWhere('c.collector', 'LIKE', "%{$search}%")
                    ->orWhere('c.plant_name', 'LIKE', "%{$search}%")
                    ->orWhere('c.phone', 'LIKE', "%{$search}%");
            });
        }

        return $q->orderByDesc('c.id');
    };

    // ✅ simplePaginate (NO count query)
    $simplePaginate = function ($query, $pageName, $page) use ($perPage) {
        return $query->simplePaginate($perPage, ['*'], $pageName, $page);
    };

    $allPlant = $cached($cacheKey('all', $pages['all']), function () use ($makeQuery, $simplePaginate, $pages) {
        return $simplePaginate($makeQuery(), 'page_all', $pages['all']);
    });

    $normalPlant = $cached($cacheKey('normal', $pages['normal']), function () use ($makeQuery, $simplePaginate, $pages) {
        return $simplePaginate($makeQuery()->where('s.normal_plant', '>', 0), 'page_normal', $pages['normal']);
    });

    $alarmPlant = $cached($cacheKey('alarm', $pages['alarm']), function () use ($makeQuery, $simplePaginate, $pages) {
        return $simplePaginate($makeQuery()->where('s.alarm_plant', '>', 0), 'page_alarm', $pages['alarm']);
    });

    $offlinePlant = $cached($cacheKey('offline', $pages['offline']), function () use ($makeQuery, $simplePaginate, $pages) {
        return $simplePaginate($makeQuery()->where('s.offline_plant', '>', 0), 'page_offline', $pages['offline']);
    });

    return $this->sendResponse([
        'all_plant'     => $allPlant,
        'normal_plant'  => $normalPlant,
        'alarm_plant'   => $alarmPlant,
        'offline_plant' => $offlinePlant,
    ], 'MAX optimized client list.');
}

}
