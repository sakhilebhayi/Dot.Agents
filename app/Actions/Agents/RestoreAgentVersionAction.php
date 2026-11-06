<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\PublishAgentVersionData;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Rolling back writes history forward: the old setup comes back as a new version, and nothing is rewritten. */
class RestoreAgentVersionAction
{
    public function __construct(private readonly PublishAgentVersionAction $publish) {}

    public function execute(AgentConfigVersion $version, PublishAgentVersionData $data): AgentDeployment
    {
        $deployment = $version->deployment;
        Gate::authorize('update', $deployment);

        return DB::transaction(function () use ($version, $deployment, $data) {
            $deployment->versions()->where('state', AgentConfigVersion::DRAFT)->delete();

            $copy = AgentConfigVersion::create([
                'organization_id' => $deployment->organization_id,
                'agent_deployment_id' => $deployment->id,
                'number' => (int) $deployment->versions()->max('number') + 1,
                'state' => AgentConfigVersion::DRAFT,
                'config' => $version->settings(),
                'note' => "Restored from version {$version->number}",
                'created_by' => $data->publishedBy,
            ]);

            return $this->publish->execute($copy, $data);
        });
    }
}
