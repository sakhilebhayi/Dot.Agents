<?php

namespace App\Services\Tools;

use App\Models\AgentRun;

/** What a tool is told about the work it is doing. The business comes from the run, never from the model's arguments. */
final readonly class ToolContext
{
    public function __construct(public AgentRun $run, public string $idempotencyKey) {}

    public function organizationId(): int
    {
        return $this->run->organization_id;
    }
}
