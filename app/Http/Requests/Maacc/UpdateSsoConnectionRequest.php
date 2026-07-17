<?php

namespace App\Http\Requests\Maacc;

use App\Enums\MaaccRole;
use App\Enums\SsoProvider;
use App\Enums\TeamRole;
use App\Rules\PublicHttpsUrl;
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
            'provider' => ['sometimes', Rule::in([SsoProvider::Oidc->value])],
            'issuer' => ['sometimes', new PublicHttpsUrl, 'max:2048'],
            'authorize_url' => ['sometimes', new PublicHttpsUrl, 'max:2048'],
            'token_url' => ['sometimes', new PublicHttpsUrl, 'max:2048'],
            'userinfo_url' => ['sometimes', new PublicHttpsUrl, 'max:2048'],
            'jwks_url' => ['sometimes', new PublicHttpsUrl, 'max:2048'],
            'client_id' => ['sometimes', 'string', 'max:512'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'scopes' => ['nullable', 'string', 'max:512'],
            'email_claim' => ['nullable', 'string', 'max:128'],
            'name_claim' => ['nullable', 'string', 'max:128'],
            'groups_claim' => ['nullable', 'string', 'max:128'],
            'allowed_domains' => ['sometimes', 'array', 'max:50'],
            'allowed_domains.*' => ['required', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
            'default_team_role' => ['sometimes', Rule::in([TeamRole::Member->value, TeamRole::Admin->value])],
            'group_role_mappings' => ['nullable', 'array', 'max:50'],
            'group_role_mappings.*.group' => ['required', 'string', 'max:255'],
            'group_role_mappings.*.team_role' => ['required', Rule::in([TeamRole::Member->value, TeamRole::Admin->value])],
            'group_role_mappings.*.maacc_role' => ['nullable', Rule::enum(MaaccRole::class)],
            'group_role_mappings.*.project_slug' => ['nullable', 'string', 'max:255'],
            'auto_provision' => ['sometimes', 'boolean'],
        ];
    }
}
