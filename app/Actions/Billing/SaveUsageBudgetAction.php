<?php

namespace App\Actions\Billing;

use App\DTOs\Billing\SaveUsageBudgetData;
use App\Models\UsageBudget;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;

class SaveUsageBudgetAction
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(int $organizationId, SaveUsageBudgetData $data): UsageBudget
    {
        if (! $data->actor->canInOrganization('billing.manage', $organizationId)) {
            throw new AuthorizationException('You cannot change this business\'s budget.');
        }

        $budget = UsageBudget::updateOrCreate(['organization_id' => $organizationId], [
            'monthly_limit' => $data->monthlyLimit,
            'warn_at_percent' => $data->warnAtPercent,
            'stop_at_limit' => $data->stopAtLimit,
            'updated_by' => $data->actor->id,
        ]);

        $this->audit->logUserAction(event: 'usage_budget.saved', description: 'Changed the monthly budget for agents', subject: $budget);

        return $budget;
    }
}
