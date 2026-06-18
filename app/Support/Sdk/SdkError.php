<?php

namespace App\Support\Sdk;

use Illuminate\Http\JsonResponse;

/**
 * Builds the controlled JSON error envelope returned by the SDK/runtime API for
 * authentication, authorization, and tool-handler failures.
 */
class SdkError
{
    /**
     * Build a structured SDK error response.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function response(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse([
            'error' => $code,
            'message' => $message,
            ...$extra,
        ], $status);
    }
}
