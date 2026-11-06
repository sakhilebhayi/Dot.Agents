<?php

namespace App\Livewire\Runs;

use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentTrigger;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RunCentre extends Component
{
    use WithPagination;

    public const READ_ONLY_ACTIONS = ['setTab'];

    private const TABS = [
        'running' => [AgentRun::QUEUED, AgentRun::RUNNING, AgentRun::WAITING_AGENT],
        'waiting' => [AgentRun::WAITING_APPROVAL],
        'failed' => [AgentRun::FAILED, AgentRun::STOPPED],
        'done' => [AgentRun::COMPLETED, AgentRun::CANCELLED],
    ];

    #[Url(except: 'running')]
    public string $tab = 'running';

    #[Url(as: 'agent', except: '')]
    public string $agent = '';

    #[Computed]
    public function runs()
    {
        return AgentRun::with('deployment:id,name')
            ->where('organization_id', TenantContext::id())->where('is_test', false)
            ->whereIn('status', self::TABS[$this->tab] ?? self::TABS['running'])
            ->when($this->agent, fn ($query) => $query->where('agent_deployment_id', $this->agent))
            ->latest('id')->paginate(25);
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        $counts = AgentRun::where('organization_id', TenantContext::id())->where('is_test', false)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(self::TABS)->map(fn ($statuses) => (int) $counts->only($statuses)->sum())->all();
    }

    #[Computed]
    public function schedules()
    {
        return AgentTrigger::where('organization_id', TenantContext::id())->where('kind', AgentTrigger::SCHEDULE)->where('is_active', true)->whereNotNull('next_run_at')->orderBy('next_run_at')->limit(20)->get();
    }

    #[Computed]
    public function paused()
    {
        return AgentDeployment::where('organization_id', TenantContext::id())->where('status', 'paused')->orderBy('name')->get(['id', 'name', 'role']);
    }

    #[Computed]
    public function agents()
    {
        return AgentDeployment::where('organization_id', TenantContext::id())->orderBy('name')->get(['id', 'name']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.runs.run-centre');
    }
}
