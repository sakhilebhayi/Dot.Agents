<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\PublishAgentVersionData;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Services\Agents\AgentConfigReview;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Makes a version the one that runs. Runs already under way finish on the version they started with. */
class PublishAgentVersionAction
{
    public function __construct(private readonly AuditService $audit, private readonly AgentConfigReview $review) {}

    public function execute(AgentConfigVersion $version, PublishAgentVersionData $data): AgentDeployment
    {
        $deployment = $version->deployment;
        Gate::authorize('update', $deployment);

        if ($version->state !== AgentConfigVersion::DRAFT) {
            throw new InvalidArgumentException('Only a draft can be published.');
        }

        if ($this->review->hasBlockers($deployment, $version->settings())) {
            throw ValidationException::withMessages(['publish' => 'Fix what the review marks before publishing.']);
        }

        DB::transaction(function () use ($version, $deployment, $data) {
            $deployment->versions()->where('state', AgentConfigVersion::PUBLISHED)->update(['state' => AgentConfigVersion::RETIRED]);

            $version->update([
                'state' => AgentConfigVersion::PUBLISHED,
                'published_by' => $data->publishedBy,
                'published_at' => now(),
                'note' => $data->note ?? $version->note,
            ]);

            // What the agent may do alone changes when a version is published, not while it is being edited.
            $deployment->update([
                'published_version_id' => $version->id,
                'autonomy_level' => $version->settings()['autonomy'],
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
