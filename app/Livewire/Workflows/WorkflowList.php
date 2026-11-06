<?php

namespace App\Livewire\Workflows;

use App\Actions\Workflows\CreateWorkflowAction;
use App\Actions\Workflows\DeleteWorkflowAction;
use App\Actions\Workflows\SaveWorkflowAction;
use App\DTOs\Workflows\CreateWorkflowData;
use App\DTOs\Workflows\SaveWorkflowData;
use App\Livewire\Concerns\ResolvesCurrentOrganization;
use App\Models\AgentWorkflow;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class WorkflowList extends Component
{
    use ResolvesCurrentOrganization;

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

        // See ResolvesCurrentOrganization: session org context can be null
        // for this request even though the user is authenticated.
        $organization = $this->requireCurrentOrganization();

        $workflow = app(CreateWorkflowAction::class)->execute($organization, new CreateWorkflowData(
            name: $this->newName,
            triggerType: $this->newTrigger,
            description: $this->newDescription ?: null,
        ));

        $this->showCreateModal = false;
        $this->redirect(route('workflows.builder', $workflow), navigate: true);
    }

    /** @return array<string, array<string, mixed>> */
    #[Computed]
    public function templates(): array
    {
        return config('workflow_templates');
    }

    public function useTemplate(string $key, SaveWorkflowAction $save): void
    {
        $template = config("workflow_templates.{$key}") ?? abort(404);

        $workflow = app(CreateWorkflowAction::class)->execute($this->requireCurrentOrganization(), new CreateWorkflowData(
            name: $template['name'],
            triggerType: 'manual',
            description: $template['summary'],
        ));

        $ids = collect($template['steps'])->mapWithKeys(fn ($step) => [$step['id'] => (string) Str::uuid()]);

        $save->execute($workflow, new SaveWorkflowData(
            $workflow->id,
            auth()->id(),
            collect($template['steps'])->map(fn ($step) => [
                'id' => $ids[$step['id']],
                'agent_key' => $step['kind'],
                'label' => $step['label'],
                'x' => $step['x'],
                'y' => $step['y'],
                'config' => ['kind' => $step['kind'], 'instruction' => $step['instruction'] ?? ''],
            ])->all(),
            collect($template['lines'])->map(fn ($line) => ['id' => (string) Str::uuid(), 'from' => $ids[$line[0]], 'to' => $ids[$line[1]], 'condition' => $line[2] ?? null])->all(),
        ));

        $this->redirect(route('workflows.builder', $workflow));
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
