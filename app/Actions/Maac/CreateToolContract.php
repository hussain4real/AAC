<?php

namespace App\Actions\Maac;

use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Models\Team;
use App\Models\ToolContract;
use App\Support\Slug;

class CreateToolContract
{
    /**
     * Create a MAAC tool contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): ToolContract
    {
        $implementationStatus = $data['execution_mode'] === ExecMode::Client->value
            ? ImplStatus::Required->value
            : ImplStatus::Ready->value;

        return ToolContract::create([
            ...$data,
            'team_id' => $team->id,
            'slug' => Slug::unique('tool_contracts', (string) $data['name']),
            'status' => 'Active',
            'implementation_status' => $implementationStatus,
            'version' => $data['version'] ?? '1.0.0',
            'requires_approval' => $data['requires_approval'] ?? false,
        ]);
    }
}
