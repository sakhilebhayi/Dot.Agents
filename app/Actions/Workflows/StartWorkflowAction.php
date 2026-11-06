<?php

namespace App\Actions\Workflows;

use App\DTOs\Workflows\StartWorkflowData;
use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Services\Workflows\WorkflowConductor;
use App\Services\Workflows\WorkflowValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class StartWorkflowAction
{
    public function __construct(private readonly WorkflowConductor $conductor, private readonly WorkflowValidator $validator) {}

    public function execute(AgentWorkflow $workflow, StartWorkflowData $data): WorkflowExecution
    {
        if ($data->actor !== null && ! $data->actor->canInOrganization('agents.operate', $workflow->organization_id)) {
            throw new AuthorizationException('You cannot run workflows in this business.');
        }

        if ($problems = $this->validator->problems($workflow)) {
            throw ValidationException::withMessages(['workflow' => $problems[0]]);
        }

        return $this->conductor->start($workflow, $data->request, $data->actor?->id, $data->isTest, $data->idempotencyKey);
    }
}
