<?php

namespace App\Services\Insights;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrgRoles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * What a person meets when they arrive from InfoDot: a welcome, the one thing to do next, and one real run to look at.
 * The next thing depends on what is true right now: work held for this person comes first, then a business with no
 * agents, then the work itself. Every figure is counted here from the records.
 */
class Arrival
{
    /**
     * @param  AgentApproval|null  $first  The approval at the head of this business's queue, when there is one.
     * @return array{welcome: string, state: string, label: string, href: string, reason: string, headline: string, more: list<array{0: string, 1: string}>, role: string|null, pending: int, agents: int}
     */
    public function greet(User $user, int $organizationId, ?AgentApproval $first): array
    {
        $organization = Organization::find($organizationId);
        $role = $organization ? $user->roleInOrganization($organization->id) : null;
        $company = $organization?->name;

        $pending = AgentApproval::where('organization_id', $organizationId)->where('status', 'pending')->count();
        $agents = AgentDeployment::where('organization_id', $organizationId)->whereNotIn('status', ['archived', 'decommissioned'])->count();

        $reviews = OrgRoles::allows($role, 'approvals.review');
        $operates = OrgRoles::allows($role, 'agents.operate');
        $builds = OrgRoles::allows($role, 'agents.manage') && Gate::allows('create', [AgentDeployment::class, $organizationId]);
        $newAgent = ['New agent', route('agents.create')];
        $templates = ['Templates', route('marketplace')];
        $runs = ['Runs', route('runs.index')];
        $roster = ['Agents', route('agents.deployments')];

        $name = Str::of((string) $user->name)->trim()->before(' ')->toString();

        $held = $pending === 1 ? 'One action is' : number_format($pending).' actions are';
        $business = $company ?? 'This business';

        [$state, $label, $href, $reason, $more, $headline] = match (true) {
            $pending > 0 && $reviews => [
                'review',
                $pending === 1 ? 'Review 1 approval' : 'Review '.number_format($pending).' approvals',
                route('governance.approvals'),
                'First in the queue: '.Str::limit((string) $first?->title, 90).'.',
                [$roster, $runs],
                $held.' held until a person answers.',
            ],
            $agents === 0 && $builds => [
                'first-agent',
                'Set up your first agent',
                route('agents.create'),
                ($company ?? 'This business').' has no agents yet. Describe the job in your own words, or start from a template. It stays a draft until you publish it.',
                [$templates],
                $business.' has no agents yet.',
            ],
            $agents === 0 => [
                'no-agents',
                'See what agents can do',
                route('marketplace'),
                ($company ?? 'This business').' has no agents yet. An owner, administrator, agent manager or developer can set one up.',
                [],
                $business.' has no agents yet.',
            ],
            $operates => [
                'work',
                'Open your agents',
                route('agents.deployments'),
                ($pending > 0 ? ($pending === 1 ? 'One action is' : number_format($pending).' actions are').' waiting for an approver.' : 'Nothing is held for a person right now.').' Pick an agent to give it work or to see what it has done.',
                $builds ? [$newAgent, $templates, $runs] : [$templates, $runs],
                $pending > 0 ? $held.' waiting for an approver.' : 'Nothing is held for a person.',
            ],
            $reviews => [
                'desk',
                'Open the approval desk',
                route('governance.approvals'),
                'Nothing is waiting for approval right now. What agents ask to do arrives here.',
                [$roster, $runs],
                'Nothing is waiting for approval.',
            ],
            default => [
                'watch',
                'See what the agents are doing',
                route('runs.index'),
                'Your role reads the work and changes nothing. Every run is on the record, step by step.',
                [$roster, $templates],
                $pending > 0 ? $held.' waiting for an approver.' : 'Nothing is held for a person.',
            ],
        };

        return [
            'welcome' => 'Welcome, '.$name.'.'.($company ? ' You are in '.$company.($role ? ' as '.Str::lower(OrgRoles::LABELS[$role]) : '').'.' : ''),
            'state' => $state,
            'label' => $label,
            'href' => $href,
            'reason' => $reason,
            'headline' => $headline,
            'more' => $more,
            'role' => $role,
            'pending' => $pending,
            'agents' => $agents,
        ];
    }

    /**
     * The run held for the first approval in the queue when there is one, and the newest piece of real work otherwise.
     * Its stops are the last steps of its own journal.
     *
     * @return array{run: AgentRun, stops: list<array{label: string, detail: string, state: string}>, total: int}|null
     */
    public function featured(int $organizationId, ?AgentApproval $first): ?array
    {
        $held = $first?->agent_run_id;
        $runs = AgentRun::with('deployment:id,name')->where('organization_id', $organizationId)->where('is_test', false);
        $run = ($held ? (clone $runs)->find($held) : null) ?? $runs->latest('id')->first();

        if (! $run) {
            return null;
        }

        $steps = $run->steps()->get(['id', 'kind', 'status', 'title', 'created_at']);
        $last = $steps->last();

        $stops = $steps->take(-5)->map(fn ($step) => [
            'label' => $step->title,
            'detail' => $step->created_at->format('H:i'),
            'state' => match (true) {
                $step->status === 'failed' => 'failed',
                $step->is($last) && $run->status === AgentRun::WAITING_APPROVAL => 'held',
                $step->is($last) && in_array($run->status, [AgentRun::QUEUED, AgentRun::RUNNING, AgentRun::WAITING_AGENT], true) => 'current',
                default => 'done',
            },
        ])->values()->all();

        return ['run' => $run, 'stops' => $stops, 'total' => $steps->count()];
    }
}
