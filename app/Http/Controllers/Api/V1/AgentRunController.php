<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RunMode;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StartRunRequest;
use App\Jobs\ProcessAgentRun;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Team;
use App\Support\Governance\IncidentGuard;
use App\Support\Governance\QuotaGuard;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\RunAuthorizer;
use App\Support\Runtime\RunPayload;
use App\Support\Sdk\CallerContextSigner;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The runtime invocation API: a registered application starts an agent run and
 * reads its status. Authentication and the application context are resolved by
 * the `sdk.auth` middleware; ownership and publication are enforced by the
 * {@see RunAuthorizer}.
 */
class AgentRunController extends Controller
{
    /**
     * Start a run for the given published agent. A synchronous run blocks until
     * it reaches a boundary and is returned `201`; an asynchronous run is queued
     * for a worker and returned `202`, to be observed via polling, streaming, or
     * a webhook.
     */
    public function store(StartRunRequest $request, RunAuthorizer $authorizer, IncidentGuard $incidents, QuotaGuard $quota, AgentRunner $runner, CallerContextSigner $callerContexts, string $agentSlug): JsonResponse
    {
        $context = SdkContext::fromRequest($request);
        $agent = $authorizer->resolveAgent($context->application, $agentSlug);

        $incidents->assert($context->application);
        $envelope = $request->callerContextEnvelope();
        $callerContext = $envelope !== null
            ? $callerContexts->verify($envelope, $context->application, $context->environment)
            : null;
        $idempotencyKey = $request->idempotencyKey();
        $requestHash = $request->requestHash($agentSlug);

        /** @var array{run: AgentRun, replayed: bool} $result */
        $result = DB::transaction(function () use ($context, $agent, $quota, $runner, $request, $callerContext, $idempotencyKey, $requestHash): array {
            Team::query()->whereKey($context->application->team_id)->lockForUpdate()->firstOrFail();
            $application = Application::query()->whereKey($context->application->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = AgentRun::query()
                    ->where('application_id', $application->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof AgentRun) {
                    if (! is_string($existing->request_hash) || ! hash_equals($existing->request_hash, $requestHash)) {
                        throw RuntimeRequestException::idempotencyConflict();
                    }

                    return ['run' => $existing, 'replayed' => true];
                }
            }

            $quota->assert($application, $agent, $context->environment);

            return [
                'run' => $runner->createRun(
                    $agent,
                    $application,
                    $context->environment,
                    $request->runInput(),
                    $request->caller(),
                    $request->mode(),
                    callerContext: $callerContext,
                    idempotencyKey: $idempotencyKey,
                    requestHash: $requestHash,
                ),
                'replayed' => false,
            ];
        }, 3);

        $run = $result['run'];

        if ($result['replayed']) {
            return (new JsonResponse(RunPayload::for($run), 200))->header('Idempotent-Replayed', 'true');
        }

        if ($request->mode() === RunMode::Async) {
            ProcessAgentRun::dispatch($run);

            return new JsonResponse(RunPayload::for($run), 202);
        }

        $run = $runner->process($run);

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
