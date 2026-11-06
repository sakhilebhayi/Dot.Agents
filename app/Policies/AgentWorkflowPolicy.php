<?php

namespace App\Policies;

use App\Models\AgentWorkflow;
use App\Models\User;

class AgentWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, AgentWorkflow $agentWorkflow): bool
    {
        return $user->organizations()->where('organizations.id', $agentWorkflow->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return auth()->check();
    }

    public function update(User $user, AgentWorkflow $agentWorkflow): bool
    {
        return $user->canInOrganization('workflows.manage', $agentWorkflow->organization_id);
    }

    public function delete(User $user, AgentWorkflow $agentWorkflow): bool
    {
        return $user->canInOrganization('workflows.manage', $agentWorkflow->organization_id);
    }
}
