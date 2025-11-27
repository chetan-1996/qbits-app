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
use Illuminate\Support\Facades\Http;

class AuthController extends BaseController
{

    public function generateCode()
    {
        $letters = [
            'A','B','C','D','E','F','G','H',
            'J','K','L','M','N','P','Q','R',
            'S','T','U','V','W','X','Y','Z'
        ];

        $result = [];

        // First fixed letter
        $result[] = 'A';

        // Random 6 letters
        for ($i = 0; $i < 6; $i++) {
            $result[] = $letters[array_rand($letters)];
        }

        // Last fixed letter
        $result[] = 'T';

        //return implode('', $result);

        return $this->sendResponse([
                'code' => implode('', $result),
            ], 'Company code successfully.');
    }

    public function companyRegister(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id'      => 'required|string|max:255',
                'company_name' => 'required|string|max:255',
                'email'        => 'required|email|max:255|unique:clients,email',
                'password'     => 'required|string',
                'c_password'   => 'required|same:password',
                'company_code' => 'required|string',
                'email_code'   => 'nullable|string|max:255',
            ]);

            // Single HTTP instance → Less CPU & Memory
            $http = Http::withOptions(['verify' => false]);

            /*
            |--------------------------------------------------------------------------
            | 1) SEND EMAIL CODE → ONLY IF email_code IS NOT SENT
            |--------------------------------------------------------------------------
            */
            if (empty($validated['email_code'])) {

                // External API call — streamed, no memory copy
                $res = $http->get(
                    'https://www.aotaisolarcloud.com/solarweb/auth/sendMailCodeWithCheck',
                    [
                        'email'    => $validated['email'],
                        'atun'     => '4gceshi',
                        'atpd'     => '123456',
                        'language' => 'en-us',
                    ]
                )->json();

                if (!isset($res['code'])) {
                    return $this->sendError('Invalid external response.', null, 500);
                }

                // Email already exists
                if ($res['code'] == -10) {
                    return $this->sendError(
                        'This email is already linked to an account.',
                        null,
                        400
                    );
                }

                // Now check company code only if email is valid
                if ($res['code'] == 0) {

                    $org = $http->get(
                        'https://www.aotaisolarcloud.com/ATSolarInfo/appcanxSearchOrganization.action',
                        ['organization' => $validated['company_code']]
                    )->json();

                    if (!isset($org['result'])) {
                        return $this->sendError('Invalid company API response.', null, 500);
                    }

                    if ($org['result']) {
                        return $this->sendError(
                            'Company code already exists.',
                            null,
                            400
                        );
                    }
                }

                return $this->sendResponse([], 'Verification code sent to email.');
            }

            /*
            |--------------------------------------------------------------------------
            | 2) REGISTER USER – ONLY after receiving email_code
            |--------------------------------------------------------------------------
            */
            $register = $http->get(
                'https://www.aotaisolarcloud.com/solarweb/user/register',
                [
                    'atun'      => $validated['user_id'],
                    'atpd'      => $validated['password'],
                    'code'      => $validated['company_code'],
                    'email'     => $validated['email'],
                    'checkcode' => $validated['email_code'],
                ]
            )->json();


            if (!isset($register['code'])) {
                return $this->sendError('Invalid registration response.', null, 500);
            }

            if ($register['code'] == -1) {
                return $this->sendError('Verification code error.', null, 400);
            }

            if ($register['code'] == -2) {
                return $this->sendError('Verification codes are inconsistent.', null, 400);
            }

            if ($register['code'] == -6) {
                return $this->sendError('Username overlap.', null, 400);
            }

            if ($register['code'] == 0) {

                // Webhook → lightweight POST (no headers needed)
                $webhookUrl = env('APP_URL') . "api/" . config('app.api_version') . "/webhook/company";

                $http->post($webhookUrl, [
                    'atun'                => $validated['user_id'],
                    'atpd'                => $validated['password'],
                    'code'                => $validated['company_code'],
                    'email'               => $validated['email'],
                    'mobile_device_token' => '',
                    'company_name'        => $validated['company_name'],
                ]);

                return $this->sendResponse([], 'Company registered successfully.');
            }

            return $this->sendError('Registration failed.', [$register], 400);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to register user.');
        }
    }

    // public function companyRegister(Request $request): JsonResponse
    // {
    //     try {
    //          $validated = $request->validate([
    //             'user_id'      => 'required|string|max:255',
    //             'company_name' => 'required|string|max:255',
    //             'email'    => 'required|email|max:255|unique:clients,email',
    //             'password' => 'required',
    //             'c_password' => 'required|same:password',
    //             'company_code' => 'required',
    //             'email_code'    => 'nullable|string|max:255',
    //         ]);


    //         if(!$validated['email_code']){
    //             $companyResponse = Http::withOptions([
    //                 'verify' => false,   // Disable SSL verification
    //             ])->get('https://www.aotaisolarcloud.com/solarweb/auth/sendMailCodeWithCheck', [
    //                 'email' => $validated['email'],
    //                 'atun' => '4gceshi',
    //                 'atpd' => '123456',
    //                 'language' => 'en-us',
    //             ]);

    //             $company_data = json_decode($companyResponse->body(), true);

    //             if($company_data['code']==-10){
    //                 return $this->sendError('This email address is already linked to an account.', 'This email address is already linked to an account.', 400);
    //             }

    //             if($company_data['code']==0){
    //                 $responses = Http::withOptions([
    //                     'verify' => false, // if you need SSL verification disabled
    //                 ])->get('https://www.aotaisolarcloud.com/ATSolarInfo/appcanxSearchOrganization.action', [
    //                     'organization' => $validated['company_code'],
    //                 ]);

    //                 $company_code_data = json_decode($responses->body(), true);

    //                 if($company_code_data['result']==true){
    //                     return $this->sendError('This email address is already linked to an account.', 'This email address is already linked to an account.', 400);
    //                 }
    //             }
    //         }

    //         if($validated['email_code']){
    //             $registerResponse = Http::withOptions([
    //                 'verify' => false, // Disable SSL verification (use only if needed)
    //             ])->get('https://www.aotaisolarcloud.com/solarweb/user/register', [
    //                 'atun'      =>  $validated['user_id'],
    //                 'atpd'      =>  $validated['password'],
    //                 'code'      => $validated['company_code'],
    //                 'email'     => $validated['email'],
    //                 'checkcode' => $validated['email_code'],
    //             ]);
    //             $data = json_decode($registerResponse->body(), true);

    //             if($data['code']==-1){
    //                 return $this->sendError('Verification code error',$registerResponse->body(), 400);
    //             }

    //             if($data['code']==0){
    //                 $url = env('APP_URL')."api/".config('app.api_version')."/webhook/company";
    //                 $response = Http::withOptions([
    //                     'verify' => false, // optional, only if SSL issues
    //                 ])->post($url, [
    //                     'atun' => $validated['user_id'],
    //                     'atpd' => $validated['password'],
    //                     'code' => $validated['company_code'],
    //                     'email' => $validated['email'],
    //                     'mobile_device_token' => '',
    //                     'company_name' => $validated['company_name'],
    //                 ]);

    //                 return $this->sendResponse([], 'Company registered successfully.');
    //             }
    //         }
    //     } catch (ValidationException $e) {
    //         return $this->sendError('Validation failed', $e->errors(), 400);
    //     } catch (\Throwable $e) {
    //         return $this->handleException($e, 'Failed to register user');
    //     }
    // }
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
