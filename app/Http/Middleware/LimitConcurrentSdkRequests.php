<?php

namespace App\Http\Middleware;

use App\Support\Sdk\SdkContext;
use App\Support\Sdk\SdkError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Atomic application-scoped admission control for concurrent SDK requests. */
class LimitConcurrentSdkRequests
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (str_ends_with($request->path(), '/stream')) {
            return $next($request);
        }

        $applicationId = SdkContext::fromRequest($request)->application->id;
        $category = $this->category($request);
        $limit = $this->limit($category);
        $ttl = max(5, (int) config('maacc.runtime.gateway.request_timeout_seconds', 150) + 10);
        $lease = null;

        for ($slot = 1; $slot <= $limit; $slot++) {
            $candidate = Cache::lock("maacc:api-concurrency:{$applicationId}:{$category}:{$slot}", $ttl);

            if ($candidate->get()) {
                $lease = $candidate;

                break;
            }
        }

        if ($lease === null) {
            return SdkError::response('api_concurrency_exceeded', 'This application has reached its concurrent API request limit.', 429)
                ->withHeaders(['Retry-After' => '1', 'X-MAACC-Backpressure' => 'api-concurrency']);
        }

        try {
            return $next($request);
        } finally {
            $lease->release();
        }
    }

    private function category(Request $request): string
    {
        return match (true) {
            $request->isMethod('POST') && str_ends_with($request->path(), '/runs') => 'run',
            $request->isMethod('POST') && str_ends_with($request->path(), '/tool-results') => 'callback',
            $request->isMethod('POST') => 'write',
            default => 'read',
        };
    }

    private function limit(string $category): int
    {
        $configured = (array) config('maacc.runtime.api_concurrency', []);

        return max(1, (int) ($configured[$category] ?? 10));
    }
}
