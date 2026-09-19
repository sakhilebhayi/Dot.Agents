<?php

namespace App\Policies;

use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;

class AgentDeploymentPolicy
{
    /** Only org members can view deployments. */
    public function view(User $user, AgentDeployment $deployment): bool
    {
        return $user->organizations()->where('organizations.id', $deployment->organization_id)->exists();
    }

    /** Any authenticated org member can view the list. */
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    /**
     * Org members can create deployments — subject to plan limit on max_agents.
     * Returns false (403) when the org is already at or above its plan's agent cap.
     */
    public function create(User $user, int $organizationId): bool
    {
        $isMember = $user->organizations()->where('organizations.id', $organizationId)->exists();
        if (! $isMember) {
            return false;
        }

        $org = Organization::find($organizationId);
        if (! $org) {
            return false;
        }

        // Resolve the plan limit from the subscription plan table. -1 is the
        // seeded convention for "unlimited" (see database/seeders/AgentPlatformSeeder.php).
        $plan = SubscriptionPlan::where('slug', $org->plan)->first();
        $maxAgents = $plan->max_agents ?? PHP_INT_MAX; // no limit if plan not found
        if ($maxAgents < 0) {
            return true;
        }

        // Count active + paused deployments (not decommissioned)
        $currentCount = AgentDeployment::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'paused'])
            ->count();

        return $currentCount < $maxAgents;
    }

    /** Only org owners/admins can update deployments. */
    public function update(User $user, AgentDeployment $deployment): bool
    {
        return $user->organizations()
            ->where('organizations.id', $deployment->organization_id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    /** Only org owners can delete (decommission) deployments. */
    public function delete(User $user, AgentDeployment $deployment): bool
    {
        return $user->organizations()
            ->where('organizations.id', $deployment->organization_id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /** Check the deployment belongs to the current org session. */
    public function chat(User $user, AgentDeployment $deployment): bool
    {
        return $this->view($user, $deployment) && $deployment->status === 'active';
    }
}
