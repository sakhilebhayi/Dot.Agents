<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\SaveAgentTeamData;
use App\Models\AgentDeployment;
use App\Models\AgentTeam;
use App\Models\User;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A team is a supervisor and the agents it may hand work to. Everyone on it has to belong to the business. */
class SaveAgentTeamAction
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(SaveAgentTeamData $data): AgentTeam
    {
        $this->allow($data->actor, $data->organizationId);

        $ids = array_keys($data->members);
        $known = AgentDeployment::where('organization_id', $data->organizationId)->whereIn('id', [...$ids, $data->supervisorId])->pluck('id')->all();

        if (! in_array($data->supervisorId, $known, true) || array_diff($ids, $known) !== []) {
            throw ValidationException::withMessages(['members' => 'Every agent on a team has to belong to this business.']);
        }

        if ($ids === [] || in_array($data->supervisorId, $ids, true)) {
            throw ValidationException::withMessages(['members' => 'Choose at least one teammate other than the supervisor.']);
        }

        $team = DB::transaction(function () use ($data) {
            $team = AgentTeam::create(['organization_id' => $data->organizationId, 'name' => $data->name, 'purpose' => $data->purpose, 'supervisor_deployment_id' => $data->supervisorId, 'created_by' => $data->actor->id]);

            foreach ($data->members as $id => $responsibility) {
                $team->members()->create(['organization_id' => $data->organizationId, 'agent_deployment_id' => $id, 'responsibility' => $responsibility]);
            }

            return $team;
        });

        $this->audit->logUserAction(event: 'agent_team.created', description: "Created the team {$team->name}", subject: $team);

        return $team;
    }

    public function remove(User $actor, AgentTeam $team): void
    {
        $this->allow($actor, $team->organization_id);

        $team->delete();
    }

    private function allow(User $actor, int $organizationId): void
    {
        if (! $actor->canInOrganization('agents.manage', $organizationId)) {
            throw new AuthorizationException('You cannot change teams in this business.');
        }
    }
}
