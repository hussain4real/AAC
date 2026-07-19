<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueCallerContextRequest;
use App\Support\Sdk\CallerContextSigner;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\JsonResponse;

class CallerContextController extends Controller
{
    public function store(IssueCallerContextRequest $request, CallerContextSigner $signer): JsonResponse
    {
        $context = SdkContext::fromRequest($request);
        $issued = $signer->issue($context->application, $context->environment, $request->validated());

        return new JsonResponse($issued, 201);
    }
}
