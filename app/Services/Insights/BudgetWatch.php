<?php

namespace App\Services\Insights;

use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\UsageBudget;
use App\Notifications\BudgetNotification;
use Illuminate\Support\Facades\Notification;

/** Holds a business to the limit it set, from what its runs actually cost this month. */
class BudgetWatch
{
    public function spentThisMonth(int $organizationId): float
    {
        return (float) AgentRun::where('organization_id', $organizationId)->where('created_at', '>=', now()->startOfMonth())->sum('cost');
    }

    public function hasRunOut(int $organizationId): bool
    {
        $budget = UsageBudget::where('organization_id', $organizationId)->first();

        return $budget !== null && $budget->stop_at_limit && $this->spentThisMonth($organizationId) >= (float) $budget->monthly_limit;
    }

    /** Tells the people who pay once a month, the first time spending passes the point they chose. */
    public function check(int $organizationId): void
    {
        $budget = UsageBudget::where('organization_id', $organizationId)->first();

        if ($budget === null || $budget->warned_for === now()->format('Y-m')) {
            return;
        }

        $spent = $this->spentThisMonth($organizationId);

        if ($spent < (float) $budget->monthly_limit * $budget->warn_at_percent / 100) {
            return;
        }

        $budget->update(['warned_for' => now()->format('Y-m')]);

        $payers = Organization::findOrFail($organizationId)->users()->get()->filter(fn ($user) => $user->canInOrganization('billing.manage', $organizationId));

        Notification::send($payers, new BudgetNotification($spent, (float) $budget->monthly_limit));
    }
}
