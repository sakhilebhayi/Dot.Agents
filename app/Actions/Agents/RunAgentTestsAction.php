<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\RunTestsData;
use App\DTOs\Agents\StartRunData;
use App\Models\AgentDeployment;
use App\Models\AgentTestCase;
use App\Models\AgentTestResult;
use Illuminate\Support\Facades\Gate;

/** Runs every saved test against one version. Test runs change nothing, but they do call the model, so they cost money. */
class RunAgentTestsAction
{
    public function __construct(private readonly StartRunAction $runs) {}

    public function execute(AgentDeployment $agent, RunTestsData $data): int
    {
        Gate::forUser($data->actor)->authorize('update', $agent);

        $cases = AgentTestCase::where('agent_deployment_id', $agent->id)->get();

        foreach ($cases as $case) {
            $run = $this->runs->execute($agent, new StartRunData(
                input: $case->input,
                triggerType: $case->trigger_type,
                triggerRef: "case:{$case->id}",
                initiatedBy: $data->actor->id,
                isTest: true,
                versionId: $data->versionId,
                deferred: true,
            ));

            AgentTestResult::create([
                'organization_id' => $agent->organization_id,
                'agent_test_case_id' => $case->id,
                'config_version_id' => $data->versionId,
                'agent_run_id' => $run->id,
            ]);

            $this->runs->begin($run);
        }

        return $cases->count();
    }
}
