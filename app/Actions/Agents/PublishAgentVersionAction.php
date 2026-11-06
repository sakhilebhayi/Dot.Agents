<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\PublishAgentVersionData;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Makes a version the one that runs. Runs already under way finish on the version they started with. */
class PublishAgentVersionAction
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(AgentConfigVersion $version, PublishAgentVersionData $data): AgentDeployment
    {
        $deployment = $version->deployment;
        Gate::authorize('update', $deployment);

        if ($version->state !== AgentConfigVersion::DRAFT) {
            throw new InvalidArgumentException('Only a draft can be published.');
        }

        DB::transaction(function () use ($version, $deployment, $data) {
            $deployment->versions()->where('state', AgentConfigVersion::PUBLISHED)->update(['state' => AgentConfigVersion::RETIRED]);

            $version->update([
                'state' => AgentConfigVersion::PUBLISHED,
                'published_by' => $data->publishedBy,
                'published_at' => now(),
                'note' => $data->note ?? $version->note,
            ]);

            $deployment->update([
                'published_version_id' => $version->id,
                'status' => in_array($deployment->status, ['draft', 'testing'], true) ? 'active' : $deployment->status,
                'deployed_at' => $deployment->deployed_at ?? now(),
            ]);
        });

        $this->audit->logUserAction(
            event: 'agent.version_published',
            description: "Published version {$version->number} of {$deployment->name}",
            data: ['version' => $version->number],
            subject: $deployment,
        );

        return $deployment->fresh();
    }
}
