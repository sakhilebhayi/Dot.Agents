<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\DuplicateAgentAction;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\KnowledgeBase;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DeploymentOverview extends Component
{
    public int $deploymentId;

    public function mount(int $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
    }

    #[Computed]
    public function deployment(): AgentDeployment
    {
        return AgentDeployment::with(['agent.agentDepartment', 'department', 'deployedBy', 'latestScorecard'])
            ->findOrFail($this->deploymentId);
    }

    #[Computed]
    public function enabledSkills()
    {
        return $this->deployment->enabledSkills()->get();
    }

    #[Computed]
    public function recentTasks()
    {
        return $this->deployment->tasks()->latest()->limit(10)->get();
    }

    #[Computed]
    public function pendingApprovalsCount(): int
    {
        return $this->deployment->pendingApprovals()->count();
    }

    #[Computed]
    public function scorecard()
    {
        return $this->deployment->latestScorecard;
    }

    #[Computed]
    public function recentRuns()
    {
        return AgentRun::where('agent_deployment_id', $this->deploymentId)->latest('id')->limit(8)->get();
    }

    #[Computed]
    public function knowledge()
    {
        $version = $this->deployment->publishedVersion ?? $this->deployment->draftVersion()->first();

        return KnowledgeBase::whereIn('id', $version?->settings()['knowledge'] ?? [])->orderBy('name')->get();
    }

    public function duplicate(DuplicateAgentAction $action): void
    {
        $copy = $action->execute($this->deployment, auth()->id());

        $this->redirectRoute('agents.setup', $copy);
    }

    public function render()
    {
        return view('livewire.agents.deployment-overview');
    }
}
