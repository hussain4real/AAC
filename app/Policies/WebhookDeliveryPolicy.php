<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookDelivery;

/**
 * Webhook deliveries are replayed by Platform Admins of the owning team.
 */
class WebhookDeliveryPolicy
{
    /**
     * Determine whether the user can replay the delivery.
     */
    public function replay(User $user, WebhookDelivery $delivery): bool
    {
        return $user->isMaaccPlatformAdmin($delivery->endpoint->application->team);
    }
}
