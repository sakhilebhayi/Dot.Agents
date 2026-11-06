<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->organizations()->where('organizations.id', $webhook->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->exists();
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->canInOrganization('tools.manage', $webhook->organization_id);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }

    public function restore(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }

    public function forceDelete(User $user, Webhook $webhook): bool
    {
        return $user->hasRole('platform_admin');
    }
}
