<?php

namespace App\Http\Middleware;

use App\Support\Sdk\JsonObjectKeyValidator;
use App\Support\Sdk\SdkError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectDuplicateJsonKeys
{
    public function __construct(private readonly JsonObjectKeyValidator $validator) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();

        if ($request->isJson() && $content !== '' && ! $this->validator->validate($content)) {
            return SdkError::response(
                'invalid_json',
                'The request body must be valid JSON without duplicate object keys.',
                400,
            );
        }

        return $next($request);
    }
}
