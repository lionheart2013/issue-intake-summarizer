<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    /**
     * Every error response has the same shape: {"error": {"code", "message", "details"}}.
     *
     * @param  array<string, mixed>  $details
     */
    public static function response(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }
}
