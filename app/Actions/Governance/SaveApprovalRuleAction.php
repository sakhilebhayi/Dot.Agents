<?php

namespace App\Actions\Governance;

use App\DTOs\Governance\SaveApprovalRuleData;
use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Rules add a person to an action. They are the business's own policy, so only its administrators change them. */
class SaveApprovalRuleAction
{
    public function execute(SaveApprovalRuleData $data): ApprovalRule
    {
        $this->authorize($data->actor, $data->organizationId);

        return ApprovalRule::create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'tool_key' => $data->toolKey,
            'category' => $data->category,
            'approver_role' => $data->approverRole,
            'expires_hours' => $data->expiresHours,
            'created_by' => $data->actor->id,
        ]);
    }

    public function remove(User $actor, ApprovalRule $rule): void
    {
        $this->authorize($actor, $rule->organization_id);

        $rule->delete();
    }

    private function authorize(User $actor, int $organizationId): void
    {
        if (! $actor->canInOrganization('approvals.rules', $organizationId)) {
            throw new AuthorizationException('Only an administrator can change approval rules.');
        }
    }
}
