<?php

namespace App\Http\Requests\Maac;

use App\Enums\DbConnectionType;
use App\Enums\Environment;
use App\Enums\Sensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataSourceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()->value('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'application_id' => ['nullable', 'uuid', Rule::exists('applications', 'id')->where('team_id', $teamId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'connection_type' => ['required', Rule::enum(DbConnectionType::class)],
            // Only an approved, ops-provisioned read-only connection name may be
            // referenced — never MAAC's own operational database.
            'connection' => ['required', 'string', Rule::in((array) config('maac.runtime.db.allowed_connections', []))],
            'vault_secret_id' => ['nullable', 'uuid', Rule::exists('vault_secrets', 'id')->where('team_id', $teamId)],
            'sensitivity' => ['required', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'environments' => ['required', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            'allowed_relations' => ['required', 'array', 'min:1'],
            'allowed_relations.*' => ['string', 'max:128'],
            'max_rows' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'statement_timeout_ms' => ['sometimes', 'integer', 'min:100', 'max:120000'],
            'max_result_kb' => ['sometimes', 'integer', 'min:1', 'max:10240'],
            'staleness_threshold_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ];
    }
}
