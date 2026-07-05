<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

/**
 * Webhook endpoints are managed by Platform Admins of the owning team.
 */
class WebhookEndpointPolicy
{
    /**
     * Determine whether the user can register webhook endpoints.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null && $user->isMaaccPlatformAdmin($user->currentTeam);
    }

    /**
     * Determine whether the user can update the endpoint.
     */
    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->isMaaccPlatformAdmin($endpoint->application->team);
    }

    /**
     * Determine whether the user can delete the endpoint.
     */
    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->isMaaccPlatformAdmin($endpoint->application->team);
    }

    /**
     * Determine whether the user can rotate the endpoint's signing secret.
     */
    public function rotate(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->isMaaccPlatformAdmin($endpoint->application->team);
    }
}
