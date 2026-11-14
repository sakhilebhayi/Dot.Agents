<?php

namespace App\Services\Insights;

use App\Models\AgentDeployment;
use App\Models\AgentTrigger;
use App\Models\UsageBudget;
use App\Support\OrgRoles;
use Illuminate\Support\Collection;

/** The panels of the command centre that are not about runs: this month's spend, the next schedules, and the agents list. */
class CommandCentre
{
    /** @return array{spent: float, limit: float|null, percent: float|null, warn: bool, stop: bool}|null */
    public function budget(int $organizationId, ?string $role): ?array
    {
        if (! OrgRoles::allows($role, 'usage.view')) {
            return null;
        }

        $spent = app(BudgetWatch::class)->spentThisMonth($organizationId);
        $limit = UsageBudget::where('organization_id', $organizationId)->first();
        $max = $limit ? (float) $limit->monthly_limit : null;
        $percent = $max !== null && $max > 0 ? min(100, round(100 * $spent / $max, 1)) : null;

        return [
            'spent' => $spent,
            'limit' => $max,
            'percent' => $percent,
            'warn' => $percent !== null && $percent >= (int) $limit->warn_at_percent,
            'stop' => (bool) $limit?->stop_at_limit,
        ];
    }

    /** @return Collection<int, AgentTrigger> The three that start soonest. */
    public function schedules(int $organizationId): Collection
    {
        return AgentTrigger::where('organization_id', $organizationId)->where('kind', AgentTrigger::SCHEDULE)->where('is_active', true)->whereNotNull('next_run_at')->orderBy('next_run_at')->limit(3)->get();
    }

    /**
     * The person's own agents first, then the busiest of the period, eight at most. One query adds role and state.
     *
     * @param  Collection<int, int>  $mine  Ids of the person's own agents, in the order to show them.
     * @param  Collection<int, array<string, mixed>>  $busy  RunMetrics::byAgent rows.
     * @param  Collection<int, int>  $starred
     * @return list<array{agent: AgentDeployment, runs: int|null, rate: float|null, starred: bool}>
     */
    public function roster(int $organizationId, Collection $mine, Collection $busy, Collection $starred): array
    {
        $busy = $busy->filter(fn ($row) => $row['agent'] !== null)->keyBy(fn ($row) => $row['agent']->id);
        $ids = $mine->merge($busy->keys())->unique()->take(8)->values();
        $known = AgentDeployment::where('organization_id', $organizationId)->whereIn('id', $ids)->get(['id', 'name', 'role', 'status'])->keyBy('id');

        return $ids->filter(fn ($id) => $known->has($id))->map(fn ($id) => [
            'agent' => $known[$id],
            'runs' => $busy[$id]['runs'] ?? null,
            'rate' => $busy[$id]['rate'] ?? null,
            'starred' => $starred->contains($id),
        ])->values()->all();
    }
}
