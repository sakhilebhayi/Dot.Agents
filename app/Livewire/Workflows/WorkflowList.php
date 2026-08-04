<?php

namespace App\Livewire\Workflows;

use App\Actions\Workflows\CreateWorkflowAction;
use App\Actions\Workflows\DeleteWorkflowAction;
use App\DTOs\Workflows\CreateWorkflowData;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class WorkflowList extends Component
{
    public bool $showCreateModal = false;

    #[Validate('required|string|min:2|max:100')]
    public string $newName = '';

    #[Validate('nullable|string|max:300')]
    public string $newDescription = '';

    #[Validate('required|in:manual,scheduled,event,webhook')]
    public string $newTrigger = 'manual';

    #[Computed]
    public function workflows()
    {
        // No explicit organization_id filter needed: AgentWorkflow's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentWorkflow::withCount(['nodes'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->reset('newName', 'newDescription', 'newTrigger');
        $this->newTrigger = 'manual';
        $this->showCreateModal = true;
    }

    public function createWorkflow(): void
    {
        $this->validate();

        $organization = Organization::findOrFail(session('current_organization_id'));

        $workflow = app(CreateWorkflowAction::class)->execute($organization, new CreateWorkflowData(
            name: $this->newName,
            triggerType: $this->newTrigger,
            description: $this->newDescription ?: null,
        ));

        $this->showCreateModal = false;
        $this->redirect(route('workflows.builder', $workflow), navigate: true);
    }

    public function deleteWorkflow(int $id): void
    {
        $workflow = AgentWorkflow::where('organization_id', session('current_organization_id'))
            ->findOrFail($id);

        app(DeleteWorkflowAction::class)->execute($workflow);

        unset($this->workflows);
        session()->flash('status', "Workflow \"{$workflow->name}\" deleted.");
    }

    public function render()
    {
        return view('livewire.workflows.workflow-list', [
            'workflows' => $this->workflows,
        ]);
    }
}
