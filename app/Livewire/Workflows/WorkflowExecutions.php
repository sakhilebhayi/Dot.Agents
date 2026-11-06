<?php

namespace App\Livewire\Workflows;

use App\Actions\Workflows\ControlWorkflowExecutionAction;
use App\DTOs\Workflows\ControlExecutionData;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** @property-read ?WorkflowExecution $execution */
class WorkflowExecutions extends Component
{
    public const READ_ONLY_ACTIONS = ['open', 'openStep', 'refresh'];

    #[Locked]
    public int $workflowId;

    public ?int $openId = null;

    public ?int $openStepId = null;

    #[Computed]
    public function executions()
    {
        return WorkflowExecution::where('workflow_id', $this->workflowId)->latest('id')->limit(10)->get();
    }

    #[Computed]
    public function execution(): ?WorkflowExecution
    {
        return $this->openId === null ? null : WorkflowExecution::where('workflow_id', $this->workflowId)->find($this->openId);
    }

    #[Computed]
    public function steps()
    {
        return $this->execution?->steps()->with('run')->orderBy('id')->get() ?? collect();
    }

    #[On('execution-started')]
    public function open(int $executionId): void
    {
        $this->openId = $executionId;
        $this->openStepId = null;
        $this->refresh();
    }

    public function openStep(int $id): void
    {
        $this->openStepId = $this->openStepId === $id ? null : $id;
    }

    public function cancel(ControlWorkflowExecutionAction $action): void
    {
        $action->cancel($this->execution, new ControlExecutionData(auth()->user()));
        $this->refresh();
    }

    public function retry(int $stepId, ControlWorkflowExecutionAction $action): void
    {
        $action->retry($this->execution, WorkflowStep::where('workflow_execution_id', $this->openId)->findOrFail($stepId), new ControlExecutionData(auth()->user()));
        $this->refresh();
    }

    public function refresh(): void
    {
        unset($this->executions, $this->execution, $this->steps);
    }

    public function render()
    {
        return view('livewire.workflows.workflow-executions');
    }
}
