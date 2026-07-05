<?php

namespace App\Http\Resources\Maacc;

use App\Models\SsoConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an enterprise identity connection for the console. The client secret
 * is never exposed (only whether one is configured); the callback redirect URI is
 * surfaced so an admin can register it with the provider.
 *
 * @mixin SsoConnection
 */
class SsoConnectionResource extends JsonResource
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
            'provider' => $this->provider->value,
            'providerLabel' => $this->provider->label(),
            'authorizeUrl' => $this->authorize_url,
            'tokenUrl' => $this->token_url,
            'userinfoUrl' => $this->userinfo_url,
            'clientId' => $this->client_id,
            'secretConfigured' => $this->client_secret !== null && $this->client_secret !== '',
            'scopes' => $this->scopes,
            'emailClaim' => $this->email_claim,
            'nameClaim' => $this->name_claim,
            'groupsClaim' => $this->groups_claim,
            'defaultTeamRole' => $this->default_team_role->value,
            'groupRoleMappings' => $this->group_role_mappings ?? [],
            'autoProvision' => $this->auto_provision,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'redirectUri' => route('sso.callback', ['ssoConnection' => $this->slug]),
            'loginUrl' => route('sso.redirect', ['ssoConnection' => $this->slug]),
            'identityCount' => $this->whenCounted('identities'),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
