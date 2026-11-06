<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\SaveAgentTeamAction;
use App\DTOs\Agents\SaveAgentTeamData;
use App\Models\AgentDeployment;
use App\Models\AgentTeam;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AgentTeams extends Component
{
    public const READ_ONLY_ACTIONS = [];

    public string $name = '';

    public string $purpose = '';

    public ?int $supervisorId = null;

    /** @var array<int, array<string, mixed>> */
    public array $members = [];

    #[Computed]
    public function teams()
    {
        return AgentTeam::with(['supervisor', 'members.deployment'])->where('organization_id', TenantContext::id())->orderBy('name')->get();
    }

    #[Computed]
    public function agents()
    {
        return AgentDeployment::where('organization_id', TenantContext::id())->whereIn('status', ['active', 'draft'])->orderBy('name')->get(['id', 'name', 'role']);
    }

    public function save(SaveAgentTeamAction $action): void
    {
        $this->validate(['name' => 'required|string|max:120', 'purpose' => 'nullable|string|max:500', 'supervisorId' => 'required|integer']);

        $action->execute(new SaveAgentTeamData(
            actor: auth()->user(),
            organizationId: TenantContext::id(),
            name: $this->name,
            purpose: $this->purpose ?: null,
            supervisorId: $this->supervisorId,
            members: collect($this->members)->filter(fn ($member) => $member['on'] ?? false)->map(fn ($member) => (string) ($member['responsibility'] ?? ''))->all(),
        ));

        $this->reset('name', 'purpose', 'supervisorId', 'members');
        unset($this->teams);
    }

    public function remove(int $id, SaveAgentTeamAction $action): void
    {
        $action->remove(auth()->user(), AgentTeam::where('organization_id', TenantContext::id())->findOrFail($id));

        unset($this->teams);
    }

    public function render()
    {
        return view('livewire.agents.agent-teams');
    }
}
