<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use App\Models\ChannelPartner;
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


            // Only fill plant-related fields when creating new record
            if (!$user->exists) {
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
                $this->sendWhatsApp($data);
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
                's.name as state_name'
            ])
            ->leftJoin('states as s', 's.id', '=', 'cp.state');

        // ✅ state filter
        if ($request->filled('state_id')) {
            $query->where('cp.state_id', $request->integer('state_id'));
        }

        // ✅ city filter
        if ($request->filled('city')) {
            $query->where('cp.city', $request->city);
        }

        $partners = $query
            ->orderByDesc('cp.id')
            ->simplePaginate(20);   // ⚡ fast paginate

        return response()->json([
            'status' => true,
            'message' => 'Partner List View',
            'data' => $partners
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
                's.name as state_name'
            ])
            ->leftJoin('states as s', 's.id', '=', 'cp.state')
            ->orderBy('cp.id');

            // ✅ Stream response (no big array in memory)
            return response()->stream(function () use ($query) {

                echo '{"status":true,"message":"Partner List View","data":[';

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
            $query = DB::table('states')->where('status', 1)
                ->orderBy('name')
                ->get(['id','name']);



        return response()->json([
            'status' => true,
            'message' => 'state List',
            'data' => $query
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
}
