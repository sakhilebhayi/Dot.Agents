<?php

namespace App\Livewire\Agents;

use App\Models\AgentDeployment;
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

    public function render()
    {
        return view('livewire.agents.deployment-overview');
    }
}
