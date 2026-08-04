<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\DecommissionDeploymentAction;
use App\Actions\Agents\PauseDeploymentAction;
use App\Actions\Agents\ResumeDeploymentAction;
use App\Models\AgentDeployment;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DeploymentManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $confirmingPause = null;

    public ?int $confirmingDecommission = null;

    #[Computed]
    public function deployments()
    {
        // No explicit organization_id filter needed: AgentDeployment's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentDeployment::with(['agent.agentDepartment', 'latestScorecard'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhereHas('agent', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at')
            ->paginate(12);
    }

    public function pauseDeployment(int $id): void
    {
        try {
            $deployment = AgentDeployment::findOrFail($id);
            app(PauseDeploymentAction::class)->execute($deployment);
            $this->confirmingPause = null;
            session()->flash('status', 'Deployment paused.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to pause this deployment.');
        }
    }

    public function resumeDeployment(int $id): void
    {
        try {
            $deployment = AgentDeployment::findOrFail($id);
            app(ResumeDeploymentAction::class)->execute($deployment);
            session()->flash('status', 'Deployment resumed.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to resume this deployment.');
        }
    }

    public function decommissionDeployment(int $id): void
    {
        try {
            $deployment = AgentDeployment::findOrFail($id);
            app(DecommissionDeploymentAction::class)->execute($deployment);
            $this->confirmingDecommission = null;
            session()->flash('status', 'Deployment decommissioned.');
        } catch (AuthorizationException) {
            session()->flash('error', 'Only the organization owner can decommission deployments.');
        }
    }

    public function render()
    {
        return view('livewire.agents.deployment-manager', [
            'deployments' => $this->deployments,
        ]);
    }
}
