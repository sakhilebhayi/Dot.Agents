<?php

namespace App\Policies;

use App\Models\AgentSkillAssignment;
use App\Models\User;

class AgentSkillAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, AgentSkillAssignment $assignment): bool
    {
        return $user->organizations()->where('organizations.id', $assignment->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return auth()->check();
    }

    public function update(User $user, AgentSkillAssignment $assignment): bool
    {
        return $user->canInOrganization('tools.manage', $assignment->organization_id);
    }

    public function delete(User $user, AgentSkillAssignment $assignment): bool
    {
        return $user->canInOrganization('tools.manage', $assignment->organization_id);
    }
}
