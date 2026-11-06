<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\SaveAgentDraftData;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Services\Agents\AgentConfig;
use App\Support\TenantRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Changes go into a draft. A live agent keeps running on its published version until a person publishes this one. */
class SaveAgentDraftAction
{
    public function execute(AgentDeployment $deployment, SaveAgentDraftData $data): AgentConfigVersion
    {
        Gate::authorize('update', $deployment);

        $config = AgentConfig::normalise($data->config);
        Validator::make($config, AgentConfig::rules())->validate();
        Validator::make(['department_id' => $data->departmentId], ['department_id' => ['nullable', 'integer', TenantRules::exists('departments')]])->validate();

        if ($data->ownerId !== null) {
            abort_unless($deployment->organization->users()->where('users.id', $data->ownerId)->exists(), 422, 'The owner has to belong to this business.');
        }

        return DB::transaction(function () use ($deployment, $data, $config) {
            $deployment->update(array_filter([
                'name' => $data->name,
                'role' => $data->role,
                'responsibility' => $data->responsibility,
                'department_id' => $data->departmentId,
                'owner_id' => $data->ownerId,
            ], fn ($value) => $value !== null));

            $draft = $deployment->draftVersion()->first();

            if ($draft) {
                $draft->update(['config' => $config, 'created_by' => $data->savedBy]);

                return $draft;
            }

            return AgentConfigVersion::create([
                'organization_id' => $deployment->organization_id,
                'agent_deployment_id' => $deployment->id,
                'number' => (int) $deployment->versions()->max('number') + 1,
                'state' => AgentConfigVersion::DRAFT,
                'config' => $config,
                'created_by' => $data->savedBy,
            ]);
        });
    }
}
