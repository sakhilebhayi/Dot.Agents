<?php

namespace App\Actions\Organizations;

use App\DTOs\Organizations\GovernMemoryData;
use App\Models\AgentMemory;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;

/** What a person may do to a memory. Each change is audited, without copying the memory itself into the log. */
class GovernMemoryAction
{
    public function __construct(private readonly AuditService $audit) {}

    public function accept(AgentMemory $memory, GovernMemoryData $data): void
    {
        $this->allow($memory, $data);

        $memory->update(['status' => AgentMemory::ACTIVE, 'is_verified' => true]);
        $this->record('memory.accepted', 'Accepted', $memory);
    }

    public function correct(AgentMemory $memory, GovernMemoryData $data): void
    {
        $this->allow($memory, $data);

        $memory->update(['content' => (string) $data->correction, 'is_verified' => true]);
        $this->record('memory.corrected', 'Corrected', $memory);
    }

    public function remove(AgentMemory $memory, GovernMemoryData $data): void
    {
        $this->allow($memory, $data);

        $memory->forceDelete();
        $this->record('memory.removed', 'Removed', $memory);
    }

    private function allow(AgentMemory $memory, GovernMemoryData $data): void
    {
        if (! $data->actor->canInOrganization('memory.govern', $memory->organization_id)) {
            throw new AuthorizationException('You cannot change memory in this business.');
        }
    }

    private function record(string $event, string $verb, AgentMemory $memory): void
    {
        $this->audit->logUserAction(event: $event, description: "{$verb} a memory of {$memory->deployment?->name}", subject: $memory, metadata: ['memory' => $memory->uuid, 'scope' => $memory->scope]);
    }
}
