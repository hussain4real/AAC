<?php

namespace App\Actions\Maac;

use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Models\Team;
use App\Models\ToolContract;
use App\Support\Sdk\ContractVersionRecorder;
use App\Support\Slug;
use App\Support\Tools\ToolConfigInput;

class CreateToolContract
{
    public function __construct(private readonly ContractVersionRecorder $versions) {}

    /**
     * Create a MAAC tool contract and snapshot its initial version.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): ToolContract
    {
        $data = ToolConfigInput::normalize($data);

        $requiresApproval = $data['requires_approval'] ?? false;
        $implementationStatus = $data['execution_mode'] === ExecMode::Client->value
            ? ImplStatus::Required->value
            : ImplStatus::Ready->value;

        $contract = ToolContract::create([
            ...$data,
            'team_id' => $team->id,
            'slug' => Slug::unique('tool_contracts', (string) $data['name']),
            // A server-side egress tool that needs approval starts inactive so the
            // runtime gate blocks it until it is granted (which flips it to Active).
            'status' => $this->initialStatus($data, $requiresApproval),
            'implementation_status' => $implementationStatus,
            'version' => $data['version'] ?? '1.0.0',
            'requires_approval' => $requiresApproval,
        ]);

        $this->versions->recordInitial($contract);

        return $contract;
    }

    /**
     * Resolve the initial status: server-side egress tools that require approval
     * start as Draft, everything else is Active.
     *
     * @param  array<string, mixed>  $data
     */
    private function initialStatus(array $data, bool $requiresApproval): string
    {
        $serverSideEgress = in_array($data['execution_mode'] ?? null, [ExecMode::Http->value, ExecMode::Connector->value, ExecMode::Knowledge->value, ExecMode::Db->value], true);

        return $requiresApproval && $serverSideEgress ? 'Draft' : 'Active';
    }
}
