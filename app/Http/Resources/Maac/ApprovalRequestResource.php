<?php

namespace App\Http\Resources\Maac;

use App\Enums\ExecMode;
use App\Enums\RemoteAuthType;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use App\Support\Governance\ApprovalGate;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an ApprovalRequest to the console governance queue contract shape
 * (resources/js/maac/data.ts `ApprovalItem`), plus the queue bucket key,
 * decision metadata, and a type-specific 360° view of the subject so a reviewer
 * can judge the request without leaving the queue.
 *
 * @mixin ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'queue' => $this->type->queue(),
            'type' => $this->type->label(),
            'status' => $this->status->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'app' => $this->application_id !== null ? $this->application->name : 'Platform',
            'requestedBy' => $this->requested_label ?? '—',
            'sensitivity' => $this->sensitivity?->label(),
            'env' => $this->environment?->label(),
            'waiting' => $this->created_at?->diffForHumans(['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]) ?? '—',
            'decidedBy' => $this->decided_label,
            'decisionNote' => $this->decision_note,
            'subject' => $this->subjectDetails(),
            'blockers' => app(ApprovalGate::class)->blockers($this->resource),
        ];
    }

    /**
     * Build a type-specific detail view of the request's subject.
     *
     * @return array<string, mixed>|null
     */
    private function subjectDetails(): ?array
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof ToolContract => $this->toolDetails($subject),
            $subject instanceof Agent => $this->agentDetails($subject),
            $subject instanceof LlmProvider => $this->modelDetails($subject),
            $subject instanceof Credential => $this->credentialDetails($subject),
            default => null,
        };
    }

    /**
     * Detail view for a tool contract (schemas + execution metadata).
     *
     * @return array<string, mixed>
     */
    private function toolDetails(ToolContract $tool): array
    {
        return [
            'kind' => 'Tool contract',
            'fields' => [
                ['k' => 'Scope', 'v' => $tool->scope->label()],
                ['k' => 'Execution mode', 'v' => $tool->execution_mode->label()],
                ['k' => 'Sensitivity', 'v' => $tool->sensitivity->label()],
                ['k' => 'Version', 'v' => $tool->version],
                ['k' => 'Timeout', 'v' => $tool->timeout_seconds.'s'],
                ['k' => 'Max payload', 'v' => $tool->max_payload_kb.' KB'],
                ['k' => 'Requires approval', 'v' => $tool->requires_approval ? 'Yes' : 'No'],
                ...$this->egressFields($tool),
            ],
            'description' => $tool->description,
            'inputSchema' => $tool->input_schema,
            'outputSchema' => $tool->output_schema,
        ];
    }

    /**
     * Build the egress-review fields a reviewer needs for a server-side tool —
     * the remote HTTP endpoint/method/auth or the MCP connector + remote tool.
     * Credential material is never serialized, only the auth scheme.
     *
     * @return array<int, array{k: string, v: string}>
     */
    private function egressFields(ToolContract $tool): array
    {
        if ($tool->execution_mode === ExecMode::Http) {
            $config = $tool->httpConfig();
            $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];

            return $this->withRedaction($tool, [
                ['k' => 'HTTP method', 'v' => strtoupper((string) ($config['method'] ?? 'POST'))],
                ['k' => 'Endpoint', 'v' => (string) ($config['endpoint'] ?? '—')],
                ['k' => 'Auth', 'v' => (RemoteAuthType::tryFrom((string) ($auth['type'] ?? 'none')) ?? RemoteAuthType::None)->label()],
            ]);
        }

        if ($tool->execution_mode === ExecMode::Connector) {
            $tool->loadMissing('mcpConnector');
            $connector = $tool->mcpConnector;

            return $this->withRedaction($tool, [
                ['k' => 'Connector', 'v' => $connector->name ?? '—'],
                ['k' => 'Server URL', 'v' => $connector->server_url ?? '—'],
                ['k' => 'Remote tool', 'v' => $tool->mcp_tool_name ?? '—'],
                ['k' => 'Connector auth', 'v' => $connector !== null ? $connector->auth_type->label() : 'None'],
            ]);
        }

        return [];
    }

    /**
     * Append the redacted-field-paths review line when the tool defines any.
     *
     * @param  array<int, array{k: string, v: string}>  $fields
     * @return array<int, array{k: string, v: string}>
     */
    private function withRedaction(ToolContract $tool, array $fields): array
    {
        if ($tool->redactionPaths() !== []) {
            $fields[] = ['k' => 'Redacted fields', 'v' => implode(', ', $tool->redactionPaths())];
        }

        return $fields;
    }

    /**
     * Detail view for an agent publication (prompt + model + tools).
     *
     * @return array<string, mixed>
     */
    private function agentDetails(Agent $agent): array
    {
        $agent->loadMissing(['llmProvider', 'tools']);

        return [
            'kind' => 'Agent',
            'fields' => [
                ['k' => 'Model', 'v' => $agent->llmProvider->name],
                ['k' => 'Version', 'v' => $agent->version],
                ['k' => 'Status', 'v' => $agent->status->label()],
                ['k' => 'Sensitivity', 'v' => $agent->sensitivity->label()],
                ['k' => 'Temperature', 'v' => (string) $agent->temperature],
                ['k' => 'Max tokens', 'v' => (string) $agent->max_tokens],
            ],
            'description' => $agent->description,
            'systemPrompt' => $agent->system_prompt,
            'tools' => $agent->tools->pluck('name')->all(),
        ];
    }

    /**
     * Detail view for a model promotion (costs + current availability).
     *
     * @return array<string, mixed>
     */
    private function modelDetails(LlmProvider $model): array
    {
        return [
            'kind' => 'Model',
            'fields' => [
                ['k' => 'Provider', 'v' => $model->provider],
                ['k' => 'Model code', 'v' => $model->code],
                ['k' => 'Context window', 'v' => $model->context_window],
                ['k' => 'Input cost', 'v' => '$'.$model->input_cost.' / 1K'],
                ['k' => 'Output cost', 'v' => '$'.$model->output_cost.' / 1K'],
                ['k' => 'Status', 'v' => $model->status->label()],
                ['k' => 'Current environments', 'v' => implode(', ', array_map(ucfirst(...), $model->environments)) ?: '—'],
            ],
            'description' => $model->note,
        ];
    }

    /**
     * Detail view for a production credential change (status + usage history).
     *
     * @return array<string, mixed>
     */
    private function credentialDetails(Credential $credential): array
    {
        $credential->loadMissing('application');

        return [
            'kind' => 'Credential',
            'fields' => [
                ['k' => 'Application', 'v' => $credential->application->name],
                ['k' => 'Environment', 'v' => $credential->environment->label()],
                ['k' => 'Status', 'v' => $credential->status->label()],
                ['k' => 'Last used', 'v' => $credential->last_used_at?->diffForHumans() ?? 'Never'],
                ['k' => 'Last rotated', 'v' => $credential->rotated_at?->diffForHumans() ?? 'Never'],
            ],
            'description' => null,
        ];
    }
}
