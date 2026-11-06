<?php

namespace App\Services\Insights;

use App\Models\AgentApproval;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentUsageEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every figure here is counted from the record of what ran. Test runs are left out. A rate with nothing to count is null,
 * shown as a dash, never a zero or a guess. DEFINITIONS is what the pages show beside each number.
 */
class RunMetrics
{
    public const DEFINITIONS = [
        'finished' => 'Work that ended, however it ended, in this period. Test runs are not counted.',
        'completed' => 'Finished work that ended with a result.',
        'failed' => 'Finished work that failed or was stopped. A person cancelling it does not count.',
        'rate' => 'Completed as a share of completed plus failed.',
        'duration' => 'Average time from starting to ending, including time spent waiting for a person.',
        'tool_failures' => 'Tool calls that failed, as a share of all tool calls that were made.',
        'approval_delay' => 'Average time from an approval being opened to a person answering it.',
        'retries' => 'Times a model call failed and was tried again.',
        'cost' => 'What runs cost at the prices on file on the day. Calls with no price on file are not included.',
        'wasted' => 'Spending on runs that ended without a result.',
    ];

    /** @return array<string, mixed> */
    public function summary(int $organizationId, CarbonInterface $since, ?int $deploymentId = null): array
    {
        $runs = $this->runs($organizationId, $since, $deploymentId);

        $completed = (clone $runs)->where('status', AgentRun::COMPLETED)->count();
        $failed = (clone $runs)->whereIn('status', [AgentRun::FAILED, AgentRun::STOPPED])->count();
        $ids = (clone $runs)->select('id');

        $effects = AgentEffect::whereIn('agent_run_id', $ids)->whereIn('status', [AgentEffect::DONE, AgentEffect::FAILED, AgentEffect::UNRESOLVED]);
        $tools = (clone $effects)->count();

        return [
            'finished' => (clone $runs)->count(),
            'completed' => $completed,
            'failed' => $failed,
            'rate' => $completed + $failed === 0 ? null : round(100 * $completed / ($completed + $failed), 1),
            'duration' => $this->averageSeconds((clone $runs)->whereNotNull('started_at')->get(['started_at', 'finished_at'])),
            'tool_calls' => $tools,
            'tool_failures' => $tools === 0 ? null : round(100 * (clone $effects)->where('status', '!=', AgentEffect::DONE)->count() / $tools, 1),
            'tokens' => (int) (clone $runs)->sum('tokens_used'),
            'cost' => (float) (clone $runs)->sum('cost'),
            'wasted' => (float) (clone $runs)->sum('wasted_cost'),
            'unpriced' => AgentUsageEvent::whereIn('agent_run_id', $ids)->whereNull('cost')->count(),
            'retries' => AgentRunStep::whereIn('agent_run_id', $ids)->where('kind', AgentRunStep::MODEL)->where('status', 'failed')->count(),
            'approval_delay' => $this->approvalDelay($organizationId, $since, $deploymentId),
        ];
    }

    /** @return list<array{day: string, completed: int, failed: int}> One row a day, with quiet days included. */
    public function daily(int $organizationId, CarbonInterface $since): array
    {
        $runs = $this->runs($organizationId, $since)->get(['finished_at', 'status']);
        $days = [];

        for ($day = $since->copy()->startOfDay(); $day <= now(); $day = $day->copy()->addDay()) {
            $today = $runs->filter(fn (AgentRun $run) => $run->finished_at->isSameDay($day));
            $days[] = ['day' => $day->format('j M'), 'completed' => $today->where('status', AgentRun::COMPLETED)->count(), 'failed' => $today->whereIn('status', [AgentRun::FAILED, AgentRun::STOPPED])->count()];
        }

        return $days;
    }

    /** @return Collection<int|string, mixed> Busiest first. */
    public function byAgent(int $organizationId, CarbonInterface $since): Collection
    {
        return $this->runs($organizationId, $since)->with('deployment:id,name,owner_id')->get(['agent_deployment_id', 'status', 'cost', 'tokens_used'])
            ->groupBy('agent_deployment_id')
            ->map(function (Collection $runs) {
                $done = $runs->where('status', AgentRun::COMPLETED)->count();
                $bad = $runs->whereIn('status', [AgentRun::FAILED, AgentRun::STOPPED])->count();

                return ['agent' => $runs->first()->deployment, 'runs' => $runs->count(), 'completed' => $done, 'failed' => $bad, 'rate' => $done + $bad === 0 ? null : round(100 * $done / ($done + $bad), 1), 'cost' => (float) $runs->sum('cost'), 'tokens' => (int) $runs->sum('tokens_used')];
            })->sortByDesc('runs')->values();
    }

    /** @return Collection<int, AgentUsageEvent> */
    public function byModel(int $organizationId, CarbonInterface $since): Collection
    {
        return AgentUsageEvent::where('organization_id', $organizationId)->where('created_at', '>=', $since)
            ->selectRaw('provider, model, count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(cost) as cost, sum(case when cost is null then 1 else 0 end) as unpriced')
            ->groupBy('provider', 'model')->orderByDesc('calls')->get();
    }

    /** @return Builder<AgentRun> */
    private function runs(int $organizationId, CarbonInterface $since, ?int $deploymentId = null): Builder
    {
        return AgentRun::where('organization_id', $organizationId)->where('is_test', false)
            ->whereIn('status', AgentRun::FINISHED)->where('finished_at', '>=', $since)
            ->when($deploymentId, fn ($query) => $query->where('agent_deployment_id', $deploymentId));
    }

    /** @param  Collection<int, AgentRun>  $runs */
    private function averageSeconds(Collection $runs): ?int
    {
        return $runs->isEmpty() ? null : (int) round($runs->avg(fn (AgentRun $run) => $run->started_at->diffInSeconds($run->finished_at)));
    }

    private function approvalDelay(int $organizationId, CarbonInterface $since, ?int $deploymentId): ?int
    {
        $answered = AgentApproval::where('organization_id', $organizationId)->whereNotNull('reviewed_at')->where('reviewed_at', '>=', $since)
            ->when($deploymentId, fn ($query) => $query->where('agent_deployment_id', $deploymentId))->get(['created_at', 'reviewed_at']);

        return $answered->isEmpty() ? null : (int) round($answered->avg(fn (AgentApproval $approval) => $approval->created_at->diffInSeconds($approval->reviewed_at)));
    }
}
