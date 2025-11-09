<?php

namespace App\Http\Controllers\API\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255|unique:users,email',
                'password' => 'required',
                'c_password' => 'required|same:password',
            ]);

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->sendResponse([
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user,
            ], 'User registered successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to register user');
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($validated)) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            $user = Auth::user();
            $success['access_token'] =  $user->createToken('auth_token')->plainTextToken;
            $success['name'] =  $user->name;
            $success['user'] = $user;
            $success['token_type'] = 'Bearer';

            return $this->sendResponse($success, 'User login successfully.');

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to login');
        }
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

    public function profile(Request $request): JsonResponse
    {
        try {
            return $this->sendResponse($request->user(), 'User profile fetched successfully.');
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to fetch profile');
        }
    }

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

            $query = User::select('id', 'name', 'email', 'created_at')
                ->when($search, function ($q) use ($search) {
                    // ✅ Use full-text or LIKE search depending on index
                    $q->where(function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $dir);

            // ✅ Use cursor pagination for large data
            $users = $query->cursorPaginate($perPage);

            return response()->json([
                'success'      => true,
                'message'      => 'Users fetched successfully',
                'per_page'     => $users->perPage(),
                'next_cursor'  => $users->nextCursor()?->encode(),
                'prev_cursor'  => $users->previousCursor()?->encode(),
                'data'         => $users->items(),
            ], 200);

        } catch (Exception $e) {
            Log::error('User List Error', ['error' => $e->getMessage()]);
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

    //     $query = User::select('id', 'name', 'email', 'created_at')
    //         ->orderBy('id', 'asc');

    //     if ($lastId) {
    //         $query->where('id', '>', $lastId);
    //     }

    //     $users = $query->limit($perPage)->get();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Batch fetched successfully',
    //         'last_id' => $users->last()?->id,
    //         'has_more' => $users->count() === $perPage,
    //         'data' => $users,
    //     ], 200);
    // }
}
