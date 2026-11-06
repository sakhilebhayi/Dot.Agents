<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\SaveTestCaseData;
use App\Models\AgentDeployment;
use App\Models\AgentTestCase;
use Illuminate\Support\Facades\Gate;

class SaveTestCaseAction
{
    public function execute(AgentDeployment $agent, SaveTestCaseData $data): AgentTestCase
    {
        Gate::forUser($data->actor)->authorize('update', $agent);

        return AgentTestCase::create([
            'organization_id' => $agent->organization_id,
            'agent_deployment_id' => $agent->id,
            'name' => $data->name,
            'input' => $data->input,
            'trigger_type' => $data->triggerType,
            'checks' => $data->checks,
            'created_by' => $data->actor->id,
        ]);
    }

    public function remove(AgentDeployment $agent, AgentTestCase $case, SaveTestCaseData $data): void
    {
        Gate::forUser($data->actor)->authorize('update', $agent);

        $case->delete();
    }
}
