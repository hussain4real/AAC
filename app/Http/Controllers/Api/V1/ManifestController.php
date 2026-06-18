<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Sdk\SdkContext;
use App\Support\Sdk\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the SDK manifest: the agents an authenticated application may invoke
 * and the client-side tool contracts it must implement, with schemas, contract
 * versions, per-environment implementation status, and generated handler stubs.
 */
class ManifestController extends Controller
{
    /**
     * Return the manifest for the authenticated application + environment.
     */
    public function show(Request $request, ToolRegistry $registry): JsonResponse
    {
        $context = SdkContext::fromRequest($request);

        return new JsonResponse(
            $registry->manifest($context->application, $context->environment),
        );
    }
}
