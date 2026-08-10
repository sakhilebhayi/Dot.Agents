<?php

namespace App\Livewire\Organizations;

use App\Actions\Skills\CreateCustomSkillAction;
use App\DTOs\Skills\CreateCustomSkillData;
use App\Livewire\Concerns\ResolvesCurrentOrganization;
use App\Models\AgentSkill;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CustomSkillManager extends Component
{
    use AuthorizesRequests, ResolvesCurrentOrganization;

    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $description = null;

    #[Validate('required|url|max:2048')]
    public string $webhookUrl = '';

    #[Validate('nullable|integer|min:1|max:120')]
    public int $webhookTimeoutSeconds = 15;

    #[Computed]
    public function customSkills()
    {
        // Scoped to this org's own custom skills only -- never the shared
        // platform catalog and never another org's rows, the same class of
        // check the 0.1.2 security pass added to ApprovalQueue/KnowledgeManager.
        return AgentSkill::where('organization_id', $this->requireCurrentOrganizationId())
            ->latest()
            ->get();
    }

    public function create(): void
    {
        $this->validate();

        app(CreateCustomSkillAction::class)->execute(new CreateCustomSkillData(
            organizationId: $this->requireCurrentOrganizationId(),
            name: $this->name,
            description: $this->description,
            webhookUrl: $this->webhookUrl,
            webhookTimeoutSeconds: $this->webhookTimeoutSeconds ?: 15,
        ));

        $this->reset(['name', 'description', 'webhookUrl']);
        $this->webhookTimeoutSeconds = 15;
        $this->showForm = false;
        unset($this->customSkills);
    }

    public function toggleActive(int $skillId): void
    {
        $skill = AgentSkill::where('organization_id', $this->requireCurrentOrganizationId())
            ->findOrFail($skillId);

        $this->authorize('update', $skill);
        $skill->update(['is_active' => ! $skill->is_active]);

        unset($this->customSkills);
    }

    public function delete(int $skillId): void
    {
        $skill = AgentSkill::where('organization_id', $this->requireCurrentOrganizationId())
            ->findOrFail($skillId);

        $this->authorize('delete', $skill);
        $skill->delete();

        unset($this->customSkills);
    }

    public function render()
    {
        return view('livewire.organizations.custom-skill-manager');
    }
}
