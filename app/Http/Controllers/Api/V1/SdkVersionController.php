<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Sdk\SdkPlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The SDK version-negotiation endpoint (Phase 6C). Returns MAAC's API contract
 * version, the supported client-package window, the published-package registry,
 * and active deprecations — plus a `compatibility` verdict for the SDK version
 * the caller reports (via the `X-Maac-Sdk-Version` header or `client_version`
 * query). Lets an SDK detect, before invoking anything, whether its installed
 * package is compatible with this MAAC instance.
 */
class SdkVersionController extends Controller
{
    /**
     * Return the SDK platform descriptor + compatibility for the caller.
     */
    public function show(Request $request, SdkPlatform $platform): JsonResponse
    {
        return new JsonResponse([
            ...$platform->descriptor(),
            'compatibility' => $platform->compatibility(
                $this->reportedValue($request, 'X-Maac-Sdk-Version', 'client_version'),
                $this->reportedValue($request, 'X-Maac-Sdk-Language', 'language'),
            ),
        ]);
    }

    /**
     * Read a client-reported value from a request header, falling back to a
     * query-string parameter.
     */
    private function reportedValue(Request $request, string $header, string $query): ?string
    {
        $value = $request->header($header);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fallback = $request->query($query);

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }
}
