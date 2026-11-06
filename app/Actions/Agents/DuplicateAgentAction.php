<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\CreateAgentData;
use App\Models\AgentDeployment;

/** A copy starts as a draft, on the same setup, and proves itself before it runs. */
class DuplicateAgentAction
{
    public function __construct(private readonly CreateAgentAction $create) {}

    public function execute(AgentDeployment $source, int $createdBy): AgentDeployment
    {
        $version = $source->draftVersion()->first() ?? $source->publishedVersion;

        return $this->create->execute(new CreateAgentData(
            organizationId: $source->organization_id,
            createdBy: $createdBy,
            name: $source->name.' copy',
            role: (string) $source->role,
            responsibility: $source->responsibility,
            departmentId: $source->department_id,
            config: $version?->settings() ?? [],
        ));
    }
}
