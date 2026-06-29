<?php

namespace App\Http\Requests\Maac;

use App\Enums\DataSourceStatus;
use App\Enums\DbConnectionType;
use App\Enums\Environment;
use App\Enums\Sensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDataSourceRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'connection_type' => ['sometimes', Rule::enum(DbConnectionType::class)],
            'connection' => ['sometimes', 'string', Rule::in((array) config('maac.runtime.db.allowed_connections', []))],
            'vault_secret_id' => ['nullable', 'uuid', Rule::exists('vault_secrets', 'id')->where('team_id', $teamId)],
            'status' => ['sometimes', Rule::enum(DataSourceStatus::class)],
            'sensitivity' => ['sometimes', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'environments' => ['sometimes', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            'allowed_relations' => ['sometimes', 'array', 'min:1'],
            'allowed_relations.*' => ['string', 'max:128'],
            'max_rows' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'statement_timeout_ms' => ['sometimes', 'integer', 'min:100', 'max:120000'],
            'max_result_kb' => ['sometimes', 'integer', 'min:1', 'max:10240'],
            'staleness_threshold_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ];
    }
}
