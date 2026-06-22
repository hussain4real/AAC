<?php

namespace App\Http\Middleware;

use App\Support\Sdk\SdkPlatform;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every SDK/runtime API response with the `X-Maac-Api-Version` header so
 * a client can always see which contract shape produced the response and detect
 * a server-side contract change (Phase 6C).
 */
class AddApiVersionHeader
{
    public function __construct(private readonly SdkPlatform $platform) {}

    /**
     * Handle an incoming request, adding the API version header to the response.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Maac-Api-Version', $this->platform->apiVersion());

        return $response;
    }
}
