<?php

namespace App\Http\Middleware;

use App\Support\Sdk\SdkError;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application-side defense in depth for the gateway's public API body/header
 * limits. The same values are published for the ingress configuration, while
 * this middleware keeps an accidentally permissive gateway from bypassing them.
 */
class EnforceApiEnvelopeLimits
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxBody = max(1, (int) config('maacc.runtime.gateway.max_body_kb', 256)) * 1024;
        $contentLengthHeader = $request->headers->get('Content-Length');
        $contentLength = is_string($contentLengthHeader)
            ? (int) $contentLengthHeader
            : strlen((string) $request->getContent());

        if ($contentLength > $maxBody) {
            return $this->error('request_body_too_large', 'The request body exceeds the public API limit.', 413);
        }

        $headerBytes = 0;

        foreach ($request->headers->all() as $name => $values) {
            $headerBytes += strlen($name) + strlen(implode(',', $values)) + 4;
        }

        if ($headerBytes > max(1, (int) config('maacc.runtime.gateway.max_header_kb', 16)) * 1024) {
            return $this->error('request_headers_too_large', 'The request headers exceed the public API limit.', 431);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(max(1, (int) config('maacc.runtime.gateway.request_timeout_seconds', 150)));
        }

        return $next($request);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return SdkError::response($code, $message, $status);
    }
}
