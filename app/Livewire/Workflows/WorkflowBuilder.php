<?php

namespace App\Livewire\Workflows;

use App\Actions\Workflows\SaveWorkflowAction;
use App\Actions\Workflows\StartWorkflowAction;
use App\Actions\Workflows\UpdateWorkflowStatusAction;
use App\DTOs\Workflows\SaveWorkflowData;
use App\DTOs\Workflows\StartWorkflowData;
use App\Livewire\Concerns\ManagesWorkflowCanvas;
use App\Models\AgentDeployment;
use App\Models\AgentWorkflow;
use App\Services\Tools\ToolCatalogue;
use App\Services\Workflows\EdgeCondition;
use App\Services\Workflows\WorkflowValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** The workflow diagram. The diagram itself lives in the browser; this keeps it, checks it and runs it. */
class WorkflowBuilder extends Component
{
    use ManagesWorkflowCanvas;

    /** Actions a read-only (viewer) role may still call; every other action is refused for it. */
    public const READ_ONLY_ACTIONS = [];

    public AgentWorkflow $workflow;

    /** Raw node array — synced from Alpine via save() */
    public array $nodes = [];

    /** Raw connection array — synced from Alpine via save() */
    public array $connections = [];

    /** Validated flash state */
    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(int $workflowId): void
    {
        $this->workflow = AgentWorkflow::where('organization_id', session('current_organization_id'))
            ->findOrFail($workflowId);

        $this->loadGraph();
    }

    /**
     * Persist the current canvas state to the database.
     */
    public function save(): void
    {
        $this->persist();

        $this->flashMessage = 'Saved.';
        $this->flashType = 'success';

    }

    /** Publishing saves the diagram, and a workflow that cannot work is not published. */
    public function publish(): void
    {
        if (empty($this->nodes)) {
            $this->flashMessage = 'Add at least one step before publishing.';
            $this->flashType = 'warning';

            return;
        }

        $this->persist();

        if ($problems = $this->problems()) {
            $this->flashMessage = $problems[0];
            $this->flashType = 'warning';

            return;
        }

        app(UpdateWorkflowStatusAction::class)->publish($this->workflow);
        $this->workflow->refresh();

        $this->flashMessage = 'Published. New work uses this diagram.';
        $this->flashType = 'success';
    }

    /**
     * Unpublish — revert status back to draft.
     */
    public function unpublish(): void
    {
        app(UpdateWorkflowStatusAction::class)->unpublish($this->workflow);
        $this->workflow->refresh();

        $this->flashMessage = 'Workflow reverted to draft.';
        $this->flashType = 'success';
    }

    /** Saves the diagram and runs it on what the person asked for. */
    public function runWith(string $request): void
    {
        $this->persist();

        try {
            $execution = app(StartWorkflowAction::class)->execute($this->workflow, new StartWorkflowData(trim($request) ?: 'Run this workflow.', auth()->user()));
        } catch (ValidationException $e) {
            $this->flashMessage = $e->validator->errors()->first();
            $this->flashType = 'warning';

            return;
        }

        $this->flashMessage = null;
        $this->dispatch('execution-started', executionId: $execution->id);
    }

    /** @return list<string> */
    #[Computed]
    public function problems(): array
    {
        return app(WorkflowValidator::class)->problems($this->workflow);
    }

    /** @return array<string, mixed> Everything the diagram offers. */
    #[Computed]
    public function options(): array
    {
        return [
            'agents' => AgentDeployment::where('organization_id', $this->workflow->organization_id)->whereIn('status', ['active', 'draft'])->orderBy('name')->get(['id', 'name', 'role', 'status'])->all(),
            'tools' => collect(app(ToolCatalogue::class)->all($this->workflow->organization_id))
                ->map(fn ($tool) => $tool->definition())
                ->reject(fn ($tool) => in_array($tool->key, ['knowledge_search', 'remember_fact', 'delegate_task'], true))
                ->map(fn ($tool) => ['key' => $tool->key, 'label' => $tool->label, 'fields' => array_keys((array) ($tool->schema['properties'] ?? []))])->values()->all(),
            'operators' => EdgeCondition::OPERATORS,
        ];
    }

    private function persist(): void
    {
        app(SaveWorkflowAction::class)->execute(
            $this->workflow,
            new SaveWorkflowData($this->workflow->id, Auth::id(), $this->nodes, $this->connections)
        );

        unset($this->problems);
    }

    public function render()
    {
        return view('livewire.workflows.workflow-builder');
    }
}
