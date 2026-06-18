<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitToolResultRequest;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\RunAuthorizer;
use App\Support\Runtime\RunPayload;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\JsonResponse;

/**
 * Receives client-side tool results for a paused run. The runtime validates the
 * tool-call identity, payload size, run status, and output schema before
 * resuming the run.
 */
class ToolResultController extends Controller
{
    /**
     * Submit a tool result and resume the run.
     */
    public function store(SubmitToolResultRequest $request, RunAuthorizer $authorizer, AgentRunner $runner, string $runId): JsonResponse
    {
        $context = SdkContext::fromRequest($request);
        $run = $authorizer->resolveRun($context->application, $runId);

        $resumed = $runner->resume($run, $request->toolCallId(), $request->result());

        return new JsonResponse(RunPayload::for($resumed));
    }
}
