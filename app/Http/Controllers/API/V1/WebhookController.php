<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class WebhookController extends Controller
{
    public function receive(Request $request)
    {
        try {
            // ✅ Optional: Verify webhook signature
            $signature = $request->header('X-Signature');
            if ($signature !== config('webhook.secret')) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // ✅ Log raw data for debugging
            Log::info('Webhook received:', $request->all());

            // ✅ Validate incoming data
            $validated = $request->validate([
                'lead_id' => 'required|string',
                'name' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'source' => 'nullable|string',
            ]);

            // ✅ Insert into DB
            // DB::table('webhook_leads')->insert([
            //     'lead_id' => $validated['lead_id'],
            //     'name' => $validated['name'] ?? null,
            //     'email' => $validated['email'] ?? null,
            //     'phone' => $validated['phone'] ?? null,
            //     'source' => $validated['source'] ?? 'unknown',
            //     'created_at' => now(),
            // ]);

            // ✅ Free memory
            gc_collect_cycles();

            // ✅ Send success response (React/Flutter compatible)
            return response()->json([
                'status' => true,
                'message' => 'Webhook processed successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Webhook error:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Error processing webhook',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
