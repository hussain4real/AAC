<?php

namespace App\Actions\Maac;

use App\Models\AuditEvent;
use App\Models\GovernanceSetting;
use App\Models\User;
use Illuminate\Support\Arr;

class UpdateGovernanceSettings
{
    /**
     * Persist governance settings for a team and record an audit event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(GovernanceSetting $settings, array $attributes, User $editor): GovernanceSetting
    {
        $settings->fill($attributes);
        $changes = Arr::except($settings->getDirty(), ['updated_at', 'created_at']);
        $settings->save();

        AuditEvent::create([
            'team_id' => $settings->team_id,
            'actor_user_id' => $editor->getAuthIdentifier(),
            'actor_label' => $editor->name,
            'action' => 'governance_settings.updated',
            'auditable_type' => GovernanceSetting::class,
            'auditable_id' => (string) $settings->getKey(),
            'metadata' => $changes ?: null,
            'ip_address' => request()->ip(),
        ]);

        return $settings;
    }
}
