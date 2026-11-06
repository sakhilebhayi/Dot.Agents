<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\ChangeAgentStageData;
use App\Models\AgentDeployment;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Testing and Staging are stages before going live. Neither takes real work. Going live is publishing. */
class ChangeAgentStageAction
{
    private const ALLOWED = ['draft' => ['testing'], 'testing' => ['staging', 'draft'], 'staging' => ['testing']];

    public function __construct(private readonly AuditService $audit) {}

    public function execute(AgentDeployment $agent, ChangeAgentStageData $data): void
    {
        Gate::forUser($data->actor)->authorize('update', $agent);

        if (! in_array($data->stage, self::ALLOWED[$agent->status] ?? [], true)) {
            throw ValidationException::withMessages(['stage' => "A {$agent->status} agent cannot move to {$data->stage}."]);
        }

        $agent->update(['status' => $data->stage]);
        $this->audit->logUserAction(event: 'agent.stage_changed', description: "Moved {$agent->name} to {$data->stage}", subject: $agent);
    }
}
