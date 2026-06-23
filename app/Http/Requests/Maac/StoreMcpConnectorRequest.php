<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use App\Enums\RemoteAuthType;
use App\Enums\Sensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMcpConnectorRequest extends FormRequest
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
            'server_url' => ['required', 'url', 'max:2048'],
            'auth_type' => ['required', Rule::enum(RemoteAuthType::class)],
            'auth_credential' => ['nullable', 'string', 'max:2048', 'required_if:auth_type,bearer,header'],
            'auth_header' => ['nullable', 'string', 'max:128', 'required_if:auth_type,header'],
            'sensitivity' => ['required', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'environments' => ['required', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ];
    }
}
