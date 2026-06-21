<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StartRunRequest;
use App\Support\Governance\QuotaGuard;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\RunAuthorizer;
use App\Support\Runtime\RunPayload;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The runtime invocation API: a registered application starts an agent run and
 * reads its status. Authentication and the application context are resolved by
 * the `sdk.auth` middleware; ownership and publication are enforced by the
 * {@see RunAuthorizer}.
 */
class AgentRunController extends Controller
{
    /**
     * Start a run for the given published agent.
     */
    public function store(StartRunRequest $request, RunAuthorizer $authorizer, QuotaGuard $quota, AgentRunner $runner, string $agentSlug): JsonResponse
    {
        $context = SdkContext::fromRequest($request);
        $agent = $authorizer->resolveAgent($context->application, $agentSlug);

        $quota->assert($context->application, $agent, $context->environment);

        $run = $runner->start($agent, $context->application, $context->environment, $request->runInput(), $request->caller());

        return new JsonResponse(RunPayload::for($run), 201);
    }

    /**
     * Return the current status of a run owned by the application.
     */
    public function show(Request $request, RunAuthorizer $authorizer, AgentRunner $runner, string $runId): JsonResponse
    {
        $context = SdkContext::fromRequest($request);
        $run = $runner->refreshExpiry($authorizer->resolveRun($context->application, $runId));

        return new JsonResponse(RunPayload::for($run));
    }
}
