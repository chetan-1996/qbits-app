<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller as Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class BaseController extends Controller
{
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($result, $message): JsonResponse
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];

        return response()->json($response, 200);
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Handle exception safely and return clean JSON.
     */
    protected function handleException(Throwable $e, string $customMessage = null, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $customMessage ?? 'An unexpected error occurred.',
            'error'   => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
        ], $status);
    }
}
