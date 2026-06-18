<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maac\ReportToolImplementation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportImplementationRequest;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\JsonResponse;

/**
 * Receives SDK implementation-status reports: an application reports the local
 * handlers it has implemented for its client-side tools, and MAAC reconciles
 * each against the current contract version, returning the resolved statuses.
 */
class ToolImplementationController extends Controller
{
    /**
     * Reconcile reported client-side handlers for the authenticated application.
     */
    public function store(ReportImplementationRequest $request, ReportToolImplementation $reporter): JsonResponse
    {
        $context = SdkContext::fromRequest($request);

        /** @var array<int, array<string, mixed>> $implementations */
        $implementations = $request->validated('implementations');

        $results = $reporter->handle(
            $context->application,
            $context->environment,
            $implementations,
        );

        return new JsonResponse(['results' => $results]);
    }
}
