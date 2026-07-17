<?php

namespace App\Support\Runtime;

use App\Models\AgentRun;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Stores raw, resumable runtime state outside the retained audit database.
 *
 * Values are tenant-scoped, application-encrypted, and expire no later than the
 * run itself. Only redacted audit data belongs on the AgentRun record.
 */
class RunStateStore
{
    /**
     * Initialize the transient state for a newly-created run.
     *
     * @param  array<string, mixed>  $state
     */
    public function initialize(AgentRun $run, array $state): void
    {
        $this->put($run, $state);
    }

    /**
     * Read a run's transient state, migrating legacy database state on access.
     *
     * @return array<string, mixed>
     */
    public function get(AgentRun $run): array
    {
        $encrypted = $this->cache()->get($this->key($run));

        if (is_string($encrypted)) {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        }

        $legacyState = $run->state;

        if (is_array($legacyState) && $legacyState !== []) {
            $this->put($run, $legacyState);

            return $legacyState;
        }

        return [];
    }

    /**
     * Replace a run's transient state and remove any legacy database copy.
     *
     * @param  array<string, mixed>  $state
     */
    public function put(AgentRun $run, array $state): void
    {
        $encrypted = Crypt::encryptString((string) json_encode($state, JSON_THROW_ON_ERROR));

        $this->cache()->put($this->key($run), $encrypted, $this->ttl($run));

        if ($run->state !== null) {
            $run->forceFill(['state' => null])->saveQuietly();
        }
    }

    /**
     * Retain raw client-tool arguments only for the lifetime of the paused run.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function putToolArguments(AgentRun $run, string $toolCallId, array $arguments): void
    {
        $state = $this->get($run);
        $toolArguments = $state['tool_arguments'] ?? [];
        $toolArguments = is_array($toolArguments) ? $toolArguments : [];
        $toolArguments[$toolCallId] = $arguments;
        $state['tool_arguments'] = $toolArguments;

        $this->put($run, $state);
    }

    /**
     * Read raw client-tool arguments from transient state when available.
     *
     * @return array<string, mixed>|null
     */
    public function toolArguments(AgentRun $run, string $toolCallId): ?array
    {
        $toolArguments = $this->get($run)['tool_arguments'] ?? null;

        if (! is_array($toolArguments) || ! array_key_exists($toolCallId, $toolArguments)) {
            return null;
        }

        $arguments = $toolArguments[$toolCallId];

        return is_array($arguments) ? $arguments : null;
    }

    /**
     * Remove raw client-tool arguments after the result has been accepted.
     */
    public function forgetToolArguments(AgentRun $run, string $toolCallId): void
    {
        $state = $this->get($run);
        $toolArguments = $state['tool_arguments'] ?? null;

        if (! is_array($toolArguments) || ! array_key_exists($toolCallId, $toolArguments)) {
            return;
        }

        unset($toolArguments[$toolCallId]);

        if ($toolArguments === []) {
            unset($state['tool_arguments']);
        } else {
            $state['tool_arguments'] = $toolArguments;
        }

        $this->put($run, $state);
    }

    /**
     * Remove all raw runtime state once a run reaches a terminal status.
     */
    public function forget(AgentRun $run): void
    {
        $this->cache()->forget($this->key($run));

        if ($run->state !== null) {
            $run->forceFill(['state' => null])->saveQuietly();
        }
    }

    /**
     * Build a cache key that cannot collide across tenants.
     */
    public function key(AgentRun $run): string
    {
        $run->loadMissing('application');

        return "maacc:runtime-state:team:{$run->application->team_id}:run:{$run->id}";
    }

    /**
     * Resolve the dedicated transient-state cache store.
     */
    private function cache(): Repository
    {
        $store = config('maacc.runtime.state_store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * Bound cache lifetime by both policy and the run's own expiry deadline.
     */
    private function ttl(AgentRun $run): int
    {
        $configured = max(1, (int) config('maacc.runtime.state_ttl_seconds', 300));

        if ($run->expires_at === null) {
            return $configured;
        }

        $remaining = max(1, (int) now()->diffInSeconds($run->expires_at, false));

        return min($configured, $remaining);
    }
}
