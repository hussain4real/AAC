<?php

declare(strict_types=1);

namespace Maac\Sdk;

use Maac\Sdk\Auth\TokenProvider;
use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\Exceptions\MaacApiException;
use Maac\Sdk\Exceptions\MissingToolHandlerException;
use Maac\Sdk\Exceptions\RunNotResolvedException;
use Maac\Sdk\Http\CurlTransport;
use Maac\Sdk\Http\HttpRequest;
use Maac\Sdk\Http\HttpResponse;
use Maac\Sdk\Resources\Manifest;
use Maac\Sdk\Resources\ManifestTool;
use Maac\Sdk\Resources\Run;
use Maac\Sdk\Resources\SdkCompatibility;
use Maac\Sdk\Tools\ToolContext;
use Maac\Sdk\Tools\ToolHandler;
use Maac\Sdk\Tools\ToolHandlerRegistry;

/**
 * The public entry point for integrating an application with MAAC. It speaks the
 * documented SDK and runtime contracts only — token exchange, manifest sync,
 * implementation reporting, agent invocation, and client-side tool pause/resume
 * — so any consumer (Laravel, plain PHP, or otherwise) drives MAAC the same way.
 *
 * The headline method is {@see self::run()}: it starts a run and automatically
 * services every client-side tool pause from a local handler registry until the
 * run reaches a terminal state.
 */
final class MaacClient
{
    /**
     * The semantic version of this SDK client package. Reported to MAAC on every
     * request (`X-Maac-Sdk-Version`) and in implementation reports so the server
     * can flag clients below its supported minimum.
     */
    public const VERSION = '1.0.0';

    /**
     * The SDK language identifier reported to MAAC.
     */
    public const LANGUAGE = 'php';

    private readonly Transport $transport;

    private readonly TokenProvider $tokens;

    public function __construct(
        private readonly MaacConfig $config,
        ?Transport $transport = null,
        ?TokenProvider $tokens = null,
    ) {
        $this->transport = $transport ?? new CurlTransport($config->timeout, $config->connectTimeout);
        $this->tokens = $tokens ?? new TokenProvider($config, $this->transport);
    }

    /**
     * Eagerly exchange the credential for an access token and return it. Calling
     * this is optional — every API method authenticates lazily — but it lets a
     * consumer prove the credential is valid up front.
     */
    public function authenticate(): string
    {
        return $this->tokens->token();
    }

    /**
     * Ask MAAC whether this installed SDK client is compatible with the server's
     * current API contract. Reports {@see self::VERSION} by default; pass an
     * explicit version to probe a different one. Lets a consumer detect and act
     * on an incompatible/outdated client before invoking anything.
     */
    public function compatibility(?string $clientVersion = null): SdkCompatibility
    {
        $response = $this->request(new HttpRequest('GET', $this->config->url('/api/v1/sdk'), [
            'Accept' => 'application/json',
            'X-Maac-Sdk-Version' => $clientVersion ?? self::VERSION,
            'X-Maac-Sdk-Language' => self::LANGUAGE,
        ]));

        return SdkCompatibility::fromArray($this->decode($response));
    }

    /**
     * Fetch the SDK manifest: the agents this application may invoke and the
     * client-side tools it must implement, for the credential's environment.
     */
    public function manifest(): Manifest
    {
        $response = $this->request(new HttpRequest('GET', $this->config->url('/api/v1/manifest'), ['Accept' => 'application/json']));

        return Manifest::fromArray($this->decode($response));
    }

    /**
     * Report a batch of local tool-handler implementations and return MAAC's
     * per-tool reconciliation results.
     *
     * @param  array<int, array<string, mixed>>  $implementations
     * @return array<int, array<string, mixed>>
     */
    public function reportImplementations(array $implementations): array
    {
        $response = $this->request(HttpRequest::json('POST', $this->config->url('/api/v1/tool-implementations'), [
            'implementations' => array_values(array_map(
                fn (array $report): array => ['sdk_version' => self::VERSION, ...$report],
                $implementations,
            )),
        ]));

        return $this->resultList($this->decode($response)['results'] ?? null);
    }

    /**
     * Report a single handler for a manifest tool, reusing the tool's current
     * version and schema fingerprint so MAAC can confirm compatibility.
     *
     * @return array<string, mixed>
     */
    public function reportImplementation(ManifestTool $tool, string $handlerName, string $language = 'php'): array
    {
        $results = $this->reportImplementations([[
            'tool' => $tool->name,
            'handler_name' => $handlerName,
            'version' => $tool->version,
            'schema_fingerprint' => $tool->schemaFingerprint,
            'language' => $language,
        ]]);

        return $results[0] ?? [];
    }

    /**
     * Report every handler in the registry that the manifest still expects,
     * reusing each contract's current version and fingerprint. This is the
     * one-call "sync my implementations" path every consumer shares.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reportHandlers(Manifest $manifest, ToolHandlerRegistry $registry, string $language = 'php'): array
    {
        $reports = [];

        foreach ($registry->registered() as $tool) {
            $contract = $manifest->tool($tool);

            if ($contract === null) {
                continue;
            }

            $reports[] = [
                'tool' => $contract->name,
                'handler_name' => $this->handlerName($registry->resolve($tool)),
                'version' => $contract->version,
                'schema_fingerprint' => $contract->schemaFingerprint,
                'language' => $language,
            ];
        }

        return $reports === [] ? [] : $this->reportImplementations($reports);
    }

    /**
     * Start a run for a published agent.
     */
    public function startRun(string $agentSlug, string $input, ?string $caller = null): Run
    {
        $payload = ['input' => $input];

        if ($caller !== null) {
            $payload['caller'] = $caller;
        }

        $response = $this->request(HttpRequest::json('POST', $this->config->url('/api/v1/agents/'.rawurlencode($agentSlug).'/runs'), $payload));

        return Run::fromArray($this->decode($response));
    }

    /**
     * Read the current status of a run.
     */
    public function getRun(string $runId): Run
    {
        $response = $this->request(new HttpRequest('GET', $this->config->url('/api/v1/runs/'.rawurlencode($runId)), ['Accept' => 'application/json']));

        return Run::fromArray($this->decode($response));
    }

    /**
     * Submit a client-side tool result for a paused run, resuming it.
     *
     * @param  array<string, mixed>  $result
     */
    public function submitToolResult(string $runId, string $toolCallId, array $result): Run
    {
        $response = $this->request(HttpRequest::json('POST', $this->config->url('/api/v1/runs/'.rawurlencode($runId).'/tool-results'), [
            'tool_call_id' => $toolCallId,
            'result' => $result,
        ]));

        return Run::fromArray($this->decode($response));
    }

    /**
     * Start a run and drive it to completion, servicing each client-side tool
     * pause from the registry. Returns the terminal run (completed, failed,
     * expired, or cancelled).
     *
     * @throws MissingToolHandlerException when MAAC pauses for an unregistered tool
     * @throws RunNotResolvedException when the run cannot be driven to a terminal state
     */
    public function run(string $agentSlug, string $input, ToolHandlerRegistry $registry, ?string $caller = null, int $maxIterations = 16): Run
    {
        $run = $this->startRun($agentSlug, $input, $caller);

        for ($iteration = 0; $run->isWaiting(); $iteration++) {
            if ($iteration >= $maxIterations) {
                throw RunNotResolvedException::exhausted($run, $maxIterations);
            }

            $toolCall = $run->toolCall;

            if ($toolCall === null) {
                throw new RunNotResolvedException($run, "The run [{$run->runId}] is waiting but MAAC returned no pending tool call.");
            }

            $handler = $registry->resolve($toolCall->tool);

            if ($handler === null) {
                throw new MissingToolHandlerException($toolCall->tool);
            }

            $result = $handler->handle($toolCall->arguments, new ToolContext($run, $toolCall));
            $run = $this->submitToolResult($run->runId, $toolCall->id, $result);
        }

        return $run;
    }

    /**
     * Authenticate, send, and transparently refresh the token once on a 401.
     */
    private function request(HttpRequest $request): HttpResponse
    {
        $response = $this->transport->send($this->authorize($request, $this->tokens->token()));

        if ($response->status === 401) {
            $response = $this->transport->send($this->authorize($request, $this->tokens->refresh()));
        }

        return $response;
    }

    /**
     * Stamp a request with the bearer token and the SDK version/language headers.
     * The request's own headers take precedence, so {@see self::compatibility()}
     * can probe an explicit version.
     */
    private function authorize(HttpRequest $request, string $token): HttpRequest
    {
        return new HttpRequest($request->method, $request->url, [
            'X-Maac-Sdk-Version' => self::VERSION,
            'X-Maac-Sdk-Language' => self::LANGUAGE,
            ...$request->headers,
            'Authorization' => 'Bearer '.$token,
        ], $request->body);
    }

    /**
     * Normalize a decoded `results` payload into a list of string-keyed rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resultList(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        $rows = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $row = [];

            foreach ($result as $key => $value) {
                $row[(string) $key] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Derive a human-friendly handler name (the handler's short class name) to
     * report to MAAC's SDK Implementation Center.
     */
    private function handlerName(?ToolHandler $handler): string
    {
        if ($handler === null) {
            return 'UnknownHandler';
        }

        $class = get_class($handler);
        $separator = strrchr($class, '\\');

        return $separator === false ? $class : substr($separator, 1);
    }

    /**
     * Decode a successful response or throw the controlled MAAC error.
     *
     * @return array<string, mixed>
     *
     * @throws MaacApiException
     */
    private function decode(HttpResponse $response): array
    {
        if (! $response->successful()) {
            throw MaacApiException::fromResponse($response);
        }

        return $response->json();
    }
}
