<?php

namespace App\Enums;

use App\Models\ApprovalRequest;
use Illuminate\Support\Str;

/**
 * The category of change a governance {@see ApprovalRequest} gates
 * before it may take effect: a sensitive tool contract, an agent publication, a
 * model environment promotion, or a production credential change.
 */
enum ApprovalType: string
{
    case ToolContract = 'tool_contract';
    case AgentPublication = 'agent_publication';
    case ModelAccess = 'model_access';
    case CredentialChange = 'credential_change';

    /**
     * Get the display label for the approval type (e.g. "Agent Publication").
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Get the console grouping key the approval queue is bucketed under.
     */
    public function queue(): string
    {
        return match ($this) {
            self::ToolContract => 'tools',
            self::AgentPublication => 'agents',
            self::ModelAccess => 'models',
            self::CredentialChange => 'data',
        };
    }

    /**
     * Get all approval types as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
