<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Class BaseController
 *
 * Provides common helper methods for API responses.
 */
class BaseController extends Controller
{
    /**
     * Send a success response.
     *
     * @param  mixed  $result
     * @param  string  $message
     * @param  int  $statusCode
     * @return JsonResponse
     */
    protected function sendResponse($result, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ], $statusCode);
    }

    /**
     * Send an error response.
     *
     * @param  string  $error
     * @param  array<string, mixed>  $errorMessages
     * @param  int  $statusCode
     * @return JsonResponse
     */
    protected function sendError(string $error, array $errorMessages = [], int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $statusCode);
    }
}