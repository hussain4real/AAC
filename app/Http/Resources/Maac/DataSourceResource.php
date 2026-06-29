<?php

namespace App\Http\Resources\Maac;

use App\Models\DataSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a governed read-only data source for the console: its connection
 * type, lifecycle status, sensitivity, the approved query surface, result/
 * freshness caps, and whether a vault credential is bound. The connection name,
 * connection string, and credential material are never exposed — only that a
 * credential is vault-managed — so the console can manage the source safely.
 *
 * @mixin DataSource
 */
class DataSourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->id,
            'id' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'connectionType' => $this->connection_type->value,
            'connectionTypeLabel' => $this->connection_type->label(),
            'driver' => $this->driver,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'sensitivity' => $this->sensitivity->label(),
            'requiresApproval' => $this->requires_approval,
            'environments' => array_map(ucfirst(...), $this->environments),
            'allowedRelations' => $this->allowedRelations(),
            'maxRows' => $this->max_rows,
            'statementTimeoutMs' => $this->statement_timeout_ms,
            'maxResultKb' => $this->max_result_kb,
            'credentialManaged' => $this->vault_secret_id !== null,
            'stalenessThresholdMinutes' => $this->staleness_threshold_minutes,
            'dataRefreshed' => $this->data_refreshed_at?->diffForHumans(),
            'toolCount' => $this->whenCounted('tools'),
            'owner' => $this->application_id === null ? 'Platform' : $this->whenLoaded('application', fn () => $this->application?->name),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
