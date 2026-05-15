<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait JsonApiResponses
{
    protected function success(string $message, mixed $data = null, ?float $startedAt = null, int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ];

        if ($startedAt !== null) {
            $payload['meta'] = [
                'source' => 'database',
                'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, mixed $errors, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}
