<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use App\Models\ChannelPartner;
use App\Models\PlantInfo;
use App\Models\InverterFault;
use Exception;

class WebhookController extends Controller
{
    public function postWhatsAppNotification(Request $request){
        $input = $request->all();

        $username = $input['user_name'];

        try {
            $existing = DB::table('clients')->where('username', $username)->exists();

            if ($existing) {
                // ✅ Update record
                DB::table('clients')
                    ->where('username', $username)
                    ->update([
                        'whatsapp_notification_flag' => $input['whatsapp_notification_flag'] ?? 0,
                        'inverter_fault_flag' => $input['inverter_fault_flag'] ?? 0,
                        'weekly_generation_report_flag' => $input['weekly_generation_report_flag'] ?? 0,
                        'monthly_generation_report_flag' => $input['monthly_generation_report_flag'] ?? 0,
                        'daily_generation_report_flag' => $input['daily_generation_report_flag'] ?? 0,
                        'updated_at'    => now(),
                    ]);
            }
            unset($username, $request);

            $response = [
                'success' => true,
                'data'    => $input,
                'message' => "Qbits whatsapp Notification updated successfully",
            ];

        } catch (\Exception $e) {
            \Log::error('Qbits Insert/Update Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Database operation failed"
            ], 500);
        } finally {
            // ✅ Release DB connection and free memory
            DB::disconnect();
            gc_collect_cycles();
        }

        return response()->json($response, 200);
    }

    public function getWhatsAppNotification($userId){
        try {
            $user_data = DB::table('clients')->where('username', $userId)->first();

            $response = [
                'success' => true,
                'data'    => $user_data,
                'message' => "Qbits whatsapp Notification updated successfully",
            ];
            unset($user_data, $userId);

        } catch (\Exception $e) {
            \Log::error('Qbits Insert/Update Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Database operation failed"
            ], 500);
        } finally {
            // ✅ Release DB connection and free memory
            DB::disconnect();
            gc_collect_cycles();
        }

        return response()->json($response, 200);
    }

    public function validateCompanyCode(Request $request)
    {
        $signature = $request->header('X-Signature');
        if ($signature !== config('webhook.secret')) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;

        // Validate input
        $request->validate([
            'company_code' => 'required|string|max:50'
        ]);

        // Fetch client where dealer_id is null
        $client = Client::where('qbits_company_code', $data['company_code'])
                        ->whereNull('dealer_id')
                        ->first();

        if (!$client) {
            return response()->json([
                'status'  => false,
                'message' => 'Company code is invalid',
            ], 400); // ❌ Not found
        }

        return response()->json([
            'status'  => true,
            'message' => 'Company code is valid',
            'data' => [
                'dealer_id' => $client->id
            ]
        ], 200); // ✅ OK
    }


    public function individualReceive(Request $request)
    {
        try {
            /* ----------------------------------------------------
             * ✅ 1. Verify HMAC Signature (secure)
             * ---------------------------------------------------- */
            // ✅ Optional: Verify webhook signature
            $signature = $request->header('X-Signature');
            if ($signature !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // ✅ Log raw data for debugging
            Log::info('Webhook received:', $request->all());

            /* ----------------------------------------------------
             * ✅ 2. Parse JSON payload
             * ---------------------------------------------------- */
            $payload = $request->json()->all();
            $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;
            $username = $data['userName'] ?? null;

            if (empty($username)) {
                return response()->json(['error' => 'Username missing'], 422);
            }

            /* ----------------------------------------------------
             * ✅ 3. Prepare cleaned mapping
             * ---------------------------------------------------- */
            $user = Client::firstOrNew(['username' => $username]);

            $dealer_id = null;
            if(isset($data['company_code']) && $data['company_code']){
                $user_cpy = Client::where('qbits_company_code', $data['company_code'])->whereNull('dealer_id')->first();
                if ($user_cpy){
                    $dealer_id=$user_cpy->id;
                }
                // if (!$user_cpy)
                // {
                //     return response()->json([
                //         'status'  => false,
                //         'message' => 'Company code is invalid',
                //     ], 400);
                // }
                //$dealer_id=$user_cpy->id;
            }


            // Always update these fields
            $user->password            = $data['password'] ?? $user->password;
            $user->phone               = $data['phone'] ?? $user->phone;
            $user->qq                  = $data['QQ'] ?? $user->qq;
            $user->email               = $data['email'] ?? $user->email;
            $user->collector           = $data['collector'] ?? $user->collector;
            $user->qbits_company_code  = $data['company_code'] ?? $user->qbits_company_code;
            $user->user_flag           = 0;
            $user->server_flag         = $user->server_flag;


            // Only fill plant-related fields when creating new record
            if (!$user->exists) {
                $user->server_flag   = $data['server_flag'] ?? 0;
                $user->plant_name    = $data['plantName'] ?? null;
                $user->inverter_type = $data['invertertype'] ?? null;
                $user->city_name     = $data['cityname'] ?? null;
                $user->longitude     = $data['longitude'] ?? null;
                $user->latitude      = $data['latitude'] ?? null;
                $user->parent        = $data['parent'] ?? null;
                $user->gmt           = $data['gmt'] ?? null;
                $user->plant_type    = $data['plantType'] ?? null;
                $user->iserial       = $data['iSerial'] ?? null;
                $user->dealer_id     = $dealer_id;
            }

            $user->save();

            /* ----------------------------------------------------
             * ✅ 4. One fast upsert (no "exists" check)
             * ---------------------------------------------------- */
            $affected = $user->wasRecentlyCreated ? 'inserted' : 'updated';
            /* ----------------------------------------------------
             * ✅ 5. Send WhatsApp only if new insert
             * ---------------------------------------------------- */
            if ($affected=='inserted' && !empty($data['phone'])) {
                if ($data['server_flag'] == 1) {
                    $lastInsertedId = $user->id;
                    $plandata = [
                        'plant_name' => $data['plantName'] ?? null,
                        'remark2' => ($data['longitude'] ?? null)."|".($data['latitude'] ?? null),
                        'remark1' => $data['cityname'] ?? null,
                        'plantstate' => 1,
                        'plant_user' => $username,
                        'record_time' => date("Y-m-d"),
                        'time' => now(),
                        'user_id' => $lastInsertedId,
                        'date' => date("Y-m"),
                        'atun' => $username,
                        'atpd' => $data['password'] ?? null,
                        'server_flag' => 1,
                    ];

                    try {
                        $plantInfoId = \DB::table('plant_infos')->insertGetId($plandata);
                        \DB::table('plant_infos')->where('id', $plantInfoId)->update(['plant_no' => $plantInfoId]);
                        Log::info('plant_infos inserted', ['id' => $plantInfoId]);
                    } catch (\Throwable $e) {
                        Log::error('plant_infos insert failed: ' . $e->getMessage());
                    }
                }
                //$this->sendWhatsApp($data);
            }

            /* ----------------------------------------------------
             * ✅ 6. Free memory and close DB
             * ---------------------------------------------------- */
            DB::disconnect();
            gc_collect_cycles();

            /* ----------------------------------------------------
             * ✅ 7. Instant lightweight response
             * ---------------------------------------------------- */
            return response()->json([
                'status'  => true,
                'message' => 'Webhook processed successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Webhook error', ['error' => $e->getMessage()]);
            DB::disconnect();
            gc_collect_cycles();

            return response()->json([
                'status'  => false,
                'message' => 'Error processing webhook',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ------------------------------------------------------------
     * 📲 Send WhatsApp notification (fast, 2 s timeout)
     * ------------------------------------------------------------ */
    private function sendWhatsApp(array $data): void
    {
        try {
            $msg = "Welcome {$data['userName']}!\n

Your inverter has been successfully connected to our application. Now you can monitor your power consumption, solar energy production, and the overall performance of your system — all in one place.

With this app, you can enjoy the following features:

*Real-Time Monitoring:* Instantly view your inverter’s performance.

*Historical Data:* Check the records of your electricity consumption and energy production.

*Notifications:* Receive instant alerts whenever there’s an issue or any change in your system.

We hope you enjoy using this feature. If you have any questions, please feel free to contact our support team.

Thank you,
*Qbits Energy*";

            $payload = [
                'Name'    => $data['userName'] ?? 'User',
                'Number'  => $data['phone'],
                'Message' => $msg,
            ];

            // ⏱ Fast non-blocking API call (2 s timeout)
            $wabbWebhookUrl = config('services.webhook.url');
            Http::timeout(2)->get(
                $wabbWebhookUrl,
                $payload
            );
        } catch (Exception $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage()]);
        }
    }   /// ARN 64 docker image


    public function companyReceive(Request $request)
    {
        try {
            /* ----------------------------------------------------
             * ✅ 1. Verify HMAC Signature (secure)
             * ---------------------------------------------------- */
            // ✅ Optional: Verify webhook signature
            $signature = $request->header('X-Signature');
            if ($signature !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // ✅ Log raw data for debugging
            Log::info('Webhook received:', $request->all());

            /* ----------------------------------------------------
             * ✅ 2. Parse JSON payload
             * ---------------------------------------------------- */
            $payload = $request->json()->all();
            $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;
            $username = $data['atun'] ?? null;

            if (empty($username)) {
                return response()->json(['error' => 'Username missing'], 422);
            }

            /* ----------------------------------------------------
             * ✅ 3. Prepare cleaned mapping
             * ---------------------------------------------------- */
            $user = Client::firstOrNew(['username' => $username]);

            // Always update these fields
            $user->password           = $data['atpd'] ?? $user->password;
            $user->company_code       = $data['code'] ?? $user->code;
            $user->email              = $data['email'] ?? $user->email;
            $user->qbits_company_code = $data['code'] ?? $user->code;
            $user->company_name       = $data['company_name'] ?? $user->company_name;
            $user->user_flag          = 1;

            // Only fill plant-related fields when creating new record
            if (!$user->exists) {
                $user->username      = $username;
                $user->password      = $data['atpd'] ?? null;
                $user->company_code  = $data['code'] ?? null;
                $user->email         = $data['email'] ?? null;
            }

            $user->save();

            /* ----------------------------------------------------
             * ✅ 4. Free memory and close DB
             * ---------------------------------------------------- */
            DB::disconnect();
            gc_collect_cycles();

            /* ----------------------------------------------------
             * ✅ 7. Instant lightweight response
             * ---------------------------------------------------- */
            return response()->json([
                'status'  => true,
                'message' => 'Qbits Company inserted or updated successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Webhook error', ['error' => $e->getMessage()]);
            DB::disconnect();
            gc_collect_cycles();

            return response()->json([
                'status'  => false,
                'message' => 'Error processing webhook',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function channelPartenList(Request $request)
    {
        try {
            if ($request->header('X-Signature') !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // normalize inputs
            $state = (int) $request->get('state_id', 0);
            $city  = (int) $request->get('city', 0);
            $page  = (int) $request->get('page', 1);

            $cacheKey = "cp_list_s{$state}_c{$city}_p{$page}";

            $partners = Cache::tags(['channel_partners'])
                ->remember($cacheKey, 300, function () use ($request) {

                    $query = ChannelPartner::query()
                        ->from('channel_partners as cp')
                        ->select([
                            'cp.id',
                            'cp.name',
                            'cp.designation',
                            'cp.company_name',
                            'cp.mobile',
                            'cp.whatsapp_no',
                            'cp.photo',
                            'cp.city',
                            's.name as state_name',
                            'c.name as city_name'
                        ])
                        ->join('states as s', 's.id', '=', 'cp.state')
                        ->join('cities as c', 'c.id', '=', 'cp.city');

                    if ($request->filled('state_id')) {
                        $query->where('cp.state', $request->integer('state_id'));
                    }

                    if ($request->filled('city')) {
                        $query->where('cp.city', $request->city);
                    }

                    return $query->orderByDesc('cp.id')
                                ->simplePaginate(20);
                });

            return response()->json([
                'status' => true,
                'message' => 'Partner List View',
                'data' => $partners
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function channelPartenMapList(Request $request)
    {
        try {
            /* ----------------------------------------------------
             * ✅ 1. Verify HMAC Signature (secure)
             * ---------------------------------------------------- */
            // ✅ Optional: Verify webhook signature
            $signature = $request->header('X-Signature');
            if ($signature !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            /* ----------------------------------------------------
             * ✅ 2. Parse JSON payload
             * ---------------------------------------------------- */
            // $payload = $request->json()->all();
            // $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;


            /* ----------------------------------------------------
             * ✅ 3. Prepare cleaned mapping
             * ---------------------------------------------------- */
            $query = ChannelPartner::query()
            ->from('channel_partners as cp')
            ->select([
                'cp.id',
                'cp.name',
                'cp.designation',
                'cp.company_name',
                'cp.mobile',
                'cp.whatsapp_no',
                'cp.photo',
                'cp.city',
                'cp.address',
                'cp.latitude',
                'cp.longitude',
                's.name as state_name',
                'c.name as city_name'
            ])
            ->join('states as s', 's.id', '=', 'cp.state')
            ->join('cities as c', 'c.id', '=', 'cp.city')
            ->orderBy('cp.id');

            // ✅ Stream response (no big array in memory)
            return response()->stream(function () use ($query) {

                echo '{"status":true,"message":"Partner Map View","data":[';

                $first = true;

                foreach ($query->cursor() as $row) {
                    if (!$first) {
                        echo ',';
                    }

                    echo json_encode($row, JSON_UNESCAPED_UNICODE);
                    $first = false;
                }

                echo ']}';

            }, 200, [
                'Content-Type' => 'application/json',
    ]);
        } catch (Exception $e) {
            gc_collect_cycles();

            return response()->json([
                'status'  => false,
                'message' => 'Error processing webhook',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function stateList(Request $request)
    {
        try {
            /* ----------------------------------------------------
             * ✅ 1. Verify HMAC Signature (secure)
             * ---------------------------------------------------- */
            // ✅ Optional: Verify webhook signature
            $signature = $request->header('X-Signature');
            if ($signature !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            /* ----------------------------------------------------
             * ✅ 2. Parse JSON payload
             * ---------------------------------------------------- */
            // $payload = $request->json()->all();
            // $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;


            /* ----------------------------------------------------
             * ✅ 3. Prepare cleaned mapping
             * ---------------------------------------------------- */
            $states = Cache::remember('states_list', 86400, function () {
                return DB::table('states')
                    ->select('id','name')
                    ->where('status',1)
                    ->orderBy('name')
                    ->get();
            });



        return response()->json([
            'status' => true,
            'message' => 'state List',
            'data' => $states
        ]);
        } catch (Exception $e) {
            gc_collect_cycles();

            return response()->json([
                'status'  => false,
                'message' => 'Error processing webhook',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function cityList(Request $request,$id)
    {
        try {
            /* ----------------------------------------------------
             * ✅ 1. Verify HMAC Signature (secure)
             * ---------------------------------------------------- */
            // ✅ Optional: Verify webhook signature
            $signature = $request->header('X-Signature');
            if ($signature !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            /* ----------------------------------------------------
             * ✅ 2. Parse JSON payload
             * ---------------------------------------------------- */
            // $payload = $request->json()->all();
            // $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;


            /* ----------------------------------------------------
             * ✅ 3. Prepare cleaned mapping
             * ---------------------------------------------------- */
            $cities = Cache::remember("cities_$id", 86400, function () use ($id) {
                return DB::table('cities')
                    ->select('id','name')
                    ->where('state_id',$id)
                    ->where('status',1)
                    ->orderBy('name')
                    ->get();
            });



        return response()->json([
            'status' => true,
            'message' => 'city List',
            'data' => $cities
        ]);
        } catch (Exception $e) {
            gc_collect_cycles();

            return response()->json([
                'status'  => false,
                'message' => 'Error processing webhook',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllPlanList(Request $request)
    {
        $token = $request->header('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 401);
        }

        $cacheKey = 'plant_list_' . md5($token);

        $plants = Cache::remember($cacheKey, 100, function () use ($token) {

            $client = Client::select('qbits_company_code')
                ->where([
                    'api_token' => $token,
                    'user_flag' => 1
                ])
                ->first();

            if (!$client) {
                return null;
            }

            return PlantInfo::query()
                ->join('clients', 'clients.id', '=', 'plant_infos.user_id')
                ->join('solar_power_logs', function ($join) {
                    $join->on('solar_power_logs.plant_id', '=', 'plant_infos.plant_no');
                })
                ->where('clients.qbits_company_code', $client->qbits_company_code)
                ->where('solar_power_logs.record_date', today()->subDay())
                ->select([
                    'plant_infos.id',
                    'plant_infos.user_id',
                    'plant_infos.plant_no as plant_id',
                    'plant_infos.plant_name as name',
                    DB::raw("'India' as country"),
                    'clients.longitude',
                    'clients.latitude',
                    'solar_power_logs.json_payload as peak_power',
                    'solar_power_logs.eday as total_energy'
                ])
                ->get();
        });

        if (!$plants) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $plants->transform(function ($plant) {
            if (is_string($plant->peak_power)) {
                $decodedPeakPower = json_decode($plant->peak_power, true);
                $plant->peak_power = json_last_error() === JSON_ERROR_NONE ? $decodedPeakPower : [];
            }

            return $plant;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Plant list fetched successfully',
            'data'    => [
                'plants' => $plants
            ]
        ]);
    }

    public function getPlantDetails(Request $request,$id)
    {
        $token = $request->header('token');
        $plantNo = $id;

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 401);
        }

        if (!$plantNo) {
            return response()->json([
                'success' => false,
                'message' => 'Plant No is required'
            ], 400);
        }

        $cacheKey = 'plant_details_' . md5($token . '_' . $plantNo);

        $plant = Cache::remember($cacheKey, 900, function () use ($token, $plantNo) {

            $client = Client::select('qbits_company_code')
                ->where('api_token', $token)
                ->where('user_flag', 1)
                ->first();

            if (!$client) {
                return null;
            }

            return PlantInfo::query()
                ->join('clients', 'clients.id', '=', 'plant_infos.user_id')
                ->join('solar_power_logs', 'solar_power_logs.plant_id', '=', 'plant_infos.plant_no')
                // ->where('clients.qbits_company_code', $client->qbits_company_code)
                ->where('plant_infos.plant_no', $plantNo)
                ->where('solar_power_logs.record_date', today()->subDay())
                ->select([
                    'plant_infos.id',
                    'plant_infos.user_id',
                    'plant_infos.plant_no as plant_id',
                    'plant_infos.plant_name as name',
                    DB::raw("'India' as country"),
                    'clients.longitude',
                    'clients.latitude',
                    'solar_power_logs.json_payload as peak_power',
                    'solar_power_logs.eday as total_energy'
                ])
                ->orderByDesc('solar_power_logs.id')
                ->first();
        });

        if (!$plant) {
            return response()->json([
                'success' => false,
                'message' => 'Plant not found or invalid token'
            ], 404);
        }

        if (is_string($plant->peak_power)) {
            $decodedPeakPower = json_decode($plant->peak_power, true);
            $plant->peak_power = json_last_error() === JSON_ERROR_NONE ? $decodedPeakPower : [];
        }

        return response()->json([
            'status'  => true,
            'message' => 'Plant details fetched successfully',
            'data'    => $plant
        ], 200);
    }

    public function getAllPlanInfo(Request $request)
    {
        $token = $request->header('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 401);
        }

        $cacheKey = 'plant_info_' . md5($token);

        $plants = Cache::remember($cacheKey, 100, function () use ($token) {

            $client = Client::select('qbits_company_code')
                ->where([
                    'api_token' => $token,
                    'user_flag' => 1
                ])
                ->first();

            if (!$client) {
                return null;
            }

            return PlantInfo::query()
                ->join('clients', 'clients.id', '=', 'plant_infos.user_id')
                ->where('clients.qbits_company_code', $client->qbits_company_code)
                ->select([
                    'plant_infos.id',
                    'plant_infos.user_id',
                    'plant_infos.plant_no as plant_id',
                    'plant_infos.plant_name as name',
                    DB::raw("'India' as country"),
                    'clients.longitude',
                    'clients.latitude',
                    'plant_infos.capacity',
                    'plant_infos.acpower as peak_power',
                    'plant_infos.eday as day_production',
                    'plant_infos.etot as total_production',
                    'plant_infos.month_power as month_production',
                    'plant_infos.year_power as year_production',
                    'plant_infos.remark1 as location',
                    'plant_infos.plantstate as plant_status',
                ])
                ->get();
        });

        if (!$plants) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Plant info fetched successfully',
            'data'    => [
                'plants' => $plants
            ]
        ]);
    }

    public function getInverterFaults(Request $request)
    {
        $token = $request->header('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 401);
        }

        $client = Client::select('qbits_company_code')
            ->where('api_token', $token)
            ->where('user_flag', 1)
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        // Get all plant_nos for this company
        $plantIds = PlantInfo::join('clients', 'clients.id', '=', 'plant_infos.user_id')
            ->where('clients.qbits_company_code', $client->qbits_company_code)
            ->pluck('plant_infos.plant_no')
            ->toArray();

        if (empty($plantIds)) {
            return response()->json([
                'status'  => true,
                'message' => 'No plants found',
                'data'    => [
                    'faults' => []
                ]
            ]);
        }

        $query = InverterFault::query()
            ->with([
                'inverter:id,plant_id,inverter_no,model,state',
                'inverter.plant:plant_name,plant_no'
            ])
            ->select([
                'id',
                'inverter_id',
                'plant_id',
                'status',
                'itype',
                'inverter_sn',
                'stime',
                'etime',
                'meta',
                'message_en'
            ])
            ->whereIn('plant_id', $plantIds);

        // Filter by inverter_id
        if ($request->filled('inverter_id')) {
            $query->where('inverter_id', $request->inverter_id);
        }

        // Filter by plant_id
        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }

        // Filter by status
        $status = $request->input('status');
        if (!is_null($status) && $status !== '' && (int) $status !== -1) {
            $query->where('status', $status);
        }

        $query->orderBy('stime', 'desc');

        // Limit (default 20)
        $limit = (int) $request->get('limit', 20);

        $faults = $query->simplePaginate($limit)->appends($request->query());

        return response()->json([
            'status'  => true,
            'message' => 'Inverter fault list fetched successfully',
            'data'    => [
                'faults' => $faults
            ]
        ]);
    }

    /*public function getAllPlanList(Request $request)
    {
        $token = $request->header('token');

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 401);
        }

        $cacheKey = 'plant_list_' . md5($token);

        $result = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($token) {

            $client = Client::select('id', 'qbits_company_code')
                ->where('api_token', $token)
                ->where('user_flag', 1)
                ->first();

            if (!$client) return null;

            $clients = Client::select('id', 'longitude', 'latitude')
                ->where('qbits_company_code', $client->qbits_company_code)
                ->get()
                ->keyBy('id');

            $clientIds = $clients->keys();

            $plants = PlantInfo::join('solar_power_logs', 'solar_power_logs.plant_id', '=', 'plant_infos.plant_no')
                ->select(
                    'plant_infos.id',
                    'plant_infos.plant_no as plant_id',
                    'plant_infos.plant_name as name',
                    'plant_infos.user_id',
                    'plant_infos.country',
                    'solar_power_logs.json_payload as peak_power',
                    'solar_power_logs.eday as total_energy'
                )
                ->whereIn('plant_infos.user_id', $clientIds)
                ->where('plant_infos.record_date', now()->toDateString())
                ->get();

            return $plants->map(function ($plant) use ($clients) {
                $clientData = $clients->get($plant->user_id);
                $plant->longitude = $clientData->longitude ?? null;
                $plant->latitude  = $clientData->latitude ?? null;
                return $plant;
            });
        });

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Company code is valid',
            'data'    => [
                'plants' => $result
            ]
        ], 200);
    }*/

    /*public function getAllPlanList(Request $request)
    {
        $token = $request->header('token');

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 401);
        }

         $client = Client::select('id', 'name', 'qbits_company_code')
            ->where('api_token', $token)
            ->where('user_flag', 1)
            ->first();

            $clients = Client::select('id', 'longitude','latitude', 'qbits_company_code')
                ->where('qbits_company_code', $client->qbits_company_code)
                ->get();

            foreach ($clients as $clientData) {

            $plantInfos = PlantInfo::join('solar_power_logs', 'solar_power_logs.plant_id', '=', 'plant_infos.plant_no')
                            ->select('plant_infos.id','plant_infos.plant_no as plant_id', 'plant_infos.plant_name as name', 'plant_infos.user_id', 'plant_infos.country','solar_power_logs.json_payload as peak_power',' solar_power_logs.eday as total_energy')
                            ->where('plant_infos.user_id', $clientData->id)
                            ->where('plant_infos.record_date', date('Y-m-d'))
                            ->get();

                $plantInfos->longitude = $clientData->longitude;
                $plantInfos->latitude = $clientData->latitude;
               
            }
        return response()->json([
            'status'  => true,
            'message' => 'Company code is valid',
            'data' => [
                'dealer_id' => $client->id
            ]
        ], 200); // ✅ OK 
    }*/
}
