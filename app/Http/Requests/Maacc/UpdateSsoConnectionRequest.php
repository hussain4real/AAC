<?php

namespace App\Http\Requests\Maacc;

use App\Enums\MaaccRole;
use App\Enums\SsoConnectionStatus;
use App\Enums\SsoProvider;
use App\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSsoConnectionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. The client secret is
     * write-only — leaving it blank preserves the stored one.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', Rule::enum(SsoProvider::class)],
            'authorize_url' => ['sometimes', 'url', 'max:2048'],
            'token_url' => ['sometimes', 'url', 'max:2048'],
            'userinfo_url' => ['sometimes', 'url', 'max:2048'],
            'client_id' => ['sometimes', 'string', 'max:512'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'scopes' => ['nullable', 'string', 'max:512'],
            'email_claim' => ['nullable', 'string', 'max:128'],
            'name_claim' => ['nullable', 'string', 'max:128'],
            'groups_claim' => ['nullable', 'string', 'max:128'],
            'default_team_role' => ['sometimes', Rule::enum(TeamRole::class)],
            'group_role_mappings' => ['nullable', 'array', 'max:50'],
            'group_role_mappings.*.group' => ['required', 'string', 'max:255'],
            'group_role_mappings.*.team_role' => ['required', Rule::enum(TeamRole::class)],
            'group_role_mappings.*.maacc_role' => ['nullable', Rule::enum(MaaccRole::class)],
            'group_role_mappings.*.project_slug' => ['nullable', 'string', 'max:255'],
            'auto_provision' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(SsoConnectionStatus::class)],
        ];
    }
}
