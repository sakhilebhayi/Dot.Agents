<?php

namespace App\Actions\Workflows;

use App\DTOs\Workflows\ControlExecutionData;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Services\Workflows\WorkflowConductor;
use Illuminate\Auth\Access\AuthorizationException;

/** A person stepping in on work under way. */
class ControlWorkflowExecutionAction
{
    public function __construct(private readonly WorkflowConductor $conductor) {}

    public function cancel(WorkflowExecution $execution, ControlExecutionData $data): void
    {
        $this->allow($execution, $data);

        $this->conductor->cancel($execution);
    }

    public function retry(WorkflowExecution $execution, WorkflowStep $step, ControlExecutionData $data): void
    {
        $this->allow($execution, $data);

        $this->conductor->retry($execution, $step);
    }

    private function allow(WorkflowExecution $execution, ControlExecutionData $data): void
    {
        if (! $data->actor->canInOrganization('agents.operate', $execution->organization_id)) {
            throw new AuthorizationException('You cannot change this work.');
        }
    }
}
