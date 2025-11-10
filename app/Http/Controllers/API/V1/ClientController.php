<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Exception;

class ClientController extends Controller
{
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

            return response()->json([
                'success'      => true,
                'message'      => 'Clients fetched successfully',
                'per_page'     => $clients->perPage(),
                'next_cursor'  => $clients->nextCursor()?->encode(),
                'prev_cursor'  => $clients->previousCursor()?->encode(),
                'data'         => $clients->items(),
            ], 200);

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

    // public function index(Request $request)
    // {
    //     $perPage = $request->input('per_page', 1);
    //     $lastId = $request->input('last_id', null);

    //     $query = Client::select('id', 'name', 'email', 'created_at')
    //         ->orderBy('id', 'asc');

    //     if ($lastId) {
    //         $query->where('id', '>', $lastId);
    //     }

    //     $clients = $query->limit($perPage)->get();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Batch fetched successfully',
    //         'last_id' => $clients->last()?->id,
    //         'has_more' => $clients->count() === $perPage,
    //         'data' => $clients,
    //     ], 200);
    // }
}
