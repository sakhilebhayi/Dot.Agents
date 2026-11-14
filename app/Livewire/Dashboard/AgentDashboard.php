<?php

namespace App\Livewire\Dashboard;

use App\Actions\Agents\ToggleFavouriteAction;
use App\DTOs\Agents\ToggleFavouriteData;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\WorkflowExecution;
use App\Services\Insights\Arrival;
use App\Services\Insights\CommandCentre;
use App\Services\Insights\RunMetrics;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The first page, and where a person arrives from InfoDot. It welcomes them, offers the one thing to do next, and then
 * answers what needs a person, what is under way, and how the work has gone.
 *
 * @property-read Collection<int, AgentApproval> $approvals
 * @property-read Collection<int, AgentRun> $failures
 * @property-read array<string, mixed> $arrival
 * @property-read \Illuminate\Support\Collection<int, array<string, mixed>> $agents
 * @property-read Collection<int, AgentDeployment> $mine
 * @property-read \Illuminate\Support\Collection<int, int> $starredIds
 */
class AgentDashboard extends Component
{
    /** Actions a read-only (viewer) role may still call; every other action is refused for it. */
    public const READ_ONLY_ACTIONS = ['setTimeframe', 'toggleFavourite'];

    public string $timeframe = '7d';

    /**
     * Who has arrived, and the one thing to do next.
     *
     * @return array{welcome: string, state: string, label: string, href: string, reason: string, more: list<array{0: string, 1: string}>, pending: int, agents: int}
     */
    #[Computed]
    public function arrival(): array
    {
        return app(Arrival::class)->greet(auth()->user(), $this->organizationId(), $this->approvals->first());
    }

    /**
     * The one real run drawn on the first page.
     *
     * @return array{run: AgentRun, stops: list<array{label: string, detail: string, state: string}>, total: int}|null
     */
    #[Computed]
    public function featured(): ?array
    {
        return app(Arrival::class)->featured($this->organizationId(), $this->approvals->first());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return app(RunMetrics::class)->summary($this->organizationId(), $this->since());
    }

    /** @return list<array{day: string, completed: int, failed: int}> */
    #[Computed]
    public function daily(): array
    {
        return app(RunMetrics::class)->daily($this->organizationId(), now()->subDays(13));
    }

    #[Computed]
    public function agents()
    {
        return app(RunMetrics::class)->byAgent($this->organizationId(), $this->since())->take(8);
    }

    #[Computed]
    public function approvals()
    {
        return AgentApproval::where('organization_id', $this->organizationId())->where('status', 'pending')
            ->orderByRaw("CASE risk_level WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")->orderBy('created_at')->limit(5)->get();
    }

    #[Computed]
    public function failures()
    {
        return AgentRun::with('deployment:id,name')->where('organization_id', $this->organizationId())->where('is_test', false)
            ->whereIn('status', [AgentRun::FAILED, AgentRun::STOPPED])->where('finished_at', '>=', now()->subDay())->latest('id')->limit(5)->get();
    }

    #[Computed]
    public function running()
    {
        return AgentRun::with('deployment:id,name')->where('organization_id', $this->organizationId())->where('is_test', false)
            ->whereIn('status', [AgentRun::QUEUED, AgentRun::RUNNING, AgentRun::WAITING_AGENT])->latest('id')->limit(5)->get();
    }

    #[Computed]
    public function workflows()
    {
        return WorkflowExecution::with('workflow:id,name')->where('organization_id', $this->organizationId())->whereIn('status', WorkflowExecution::OPEN)->latest('id')->limit(5)->get();
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    #[Computed]
    public function starredIds()
    {
        /** @var \Illuminate\Support\Collection<int, int> $ids */
        $ids = auth()->user()->favouriteAgents()->allRelatedIds();

        return $ids;
    }

    /** The agents this person keeps coming back to: starred first, then the ones they used last. */
    #[Computed]
    public function mine()
    {
        $starred = $this->starredIds;
        $recent = AgentRun::where('organization_id', $this->organizationId())->where('initiated_by', auth()->id())->where('is_test', false)->latest('id')->limit(30)->pluck('agent_deployment_id')->unique();
        $owned = AgentDeployment::where('organization_id', $this->organizationId())->where('owner_id', auth()->id())->pluck('id');

        $ids = $starred->merge($recent)->merge($owned)->unique()->take(6)->values();

        return AgentDeployment::where('organization_id', $this->organizationId())->whereIn('id', $ids)->get(['id', 'name', 'role', 'status'])
            ->sortBy(fn (AgentDeployment $agent) => $ids->search($agent->id))->values()
            ->each(fn (AgentDeployment $agent) => $agent->setAttribute('starred', $starred->contains($agent->id)));
    }

    /**
     * This month's spending against the business's own limit. Only roles that may read usage see it; everyone else gets null.
     *
     * @return array{spent: float, limit: float|null, percent: float|null, warn: bool, stop: bool}|null
     */
    #[Computed]
    public function budget(): ?array
    {
        return app(CommandCentre::class)->budget($this->organizationId(), $this->arrival['role']);
    }

    /** The next schedules to start. Read only: nothing here starts, pauses or edits one. */
    #[Computed]
    public function schedules()
    {
        return app(CommandCentre::class)->schedules($this->organizationId());
    }

    /**
     * One list of agents: the ones this person keeps coming back to first, then the busiest in the period.
     *
     * @return list<array{agent: AgentDeployment, runs: int|null, rate: float|null, starred: bool}>
     */
    #[Computed]
    public function roster(): array
    {
        return app(CommandCentre::class)->roster($this->organizationId(), $this->mine->pluck('id'), $this->agents, $this->starredIds);
    }

    /** The number of rows under "Needs you". */
    #[Computed]
    public function needsYou(): int
    {
        return $this->approvals->count() + $this->failures->count();
    }

    public function setTimeframe(string $timeframe): void
    {
        $this->timeframe = in_array($timeframe, ['24h', '7d', '30d'], true) ? $timeframe : '7d';
        unset($this->summary, $this->agents, $this->roster);
    }

    public function toggleFavourite(int $id, ToggleFavouriteAction $action): void
    {
        $agent = AgentDeployment::where('organization_id', $this->organizationId())->findOrFail($id);

        $action->execute(new ToggleFavouriteData(auth()->id(), $agent->id));
        unset($this->starredIds, $this->mine, $this->roster);
    }

    private function organizationId(): int
    {
        return (int) TenantContext::id();
    }

    private function since()
    {
        return match ($this->timeframe) {
            '24h' => now()->subDay(),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };
    }

    public function render()
    {
        return view('livewire.dashboard.agent-dashboard');
    }
}
