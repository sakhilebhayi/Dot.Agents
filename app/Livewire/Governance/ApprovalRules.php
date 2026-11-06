<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\SaveApprovalRuleAction;
use App\DTOs\Governance\SaveApprovalRuleData;
use App\Models\ApprovalRule;
use App\Services\Tools\ToolCatalogue;
use App\Support\OrgRoles;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ApprovalRules extends Component
{
    public const READ_ONLY_ACTIONS = [];

    public string $name = '';

    /** Either "tool:key" or "category:name". */
    public string $covers = '';

    public string $approverRole = '';

    public ?int $expiresHours = null;

    #[Computed]
    public function rules()
    {
        return ApprovalRule::orderBy('name')->get();
    }

    /** @return array<string, array<string, string>> */
    #[Computed]
    public function choices(): array
    {
        $tools = collect(app(ToolCatalogue::class)->all(TenantContext::id()))->map(fn ($tool) => $tool->definition());

        return [
            'Kinds of tool' => $tools->pluck('category')->unique()->sort()->mapWithKeys(fn ($category) => ["category:{$category}" => $category])->all(),
            'One tool' => $tools->mapWithKeys(fn ($tool) => ["tool:{$tool->key}" => $tool->label])->all(),
        ];
    }

    /** @return array<string, string> */
    #[Computed]
    public function roles(): array
    {
        return array_intersect_key(OrgRoles::LABELS, array_flip([OrgRoles::OWNER, OrgRoles::ADMIN, OrgRoles::APPROVER]));
    }

    public function save(SaveApprovalRuleAction $action): void
    {
        $this->validate([
            'name' => 'required|string|max:120',
            'covers' => 'required|string',
            'approverRole' => 'nullable|in:'.implode(',', OrgRoles::ALL),
            'expiresHours' => 'nullable|integer|min:1|max:720',
        ], ['covers.required' => 'Choose what this rule covers.']);

        [$kind, $value] = explode(':', $this->covers, 2);

        $action->execute(new SaveApprovalRuleData(
            actor: auth()->user(),
            organizationId: TenantContext::id(),
            name: $this->name,
            toolKey: $kind === 'tool' ? $value : null,
            category: $kind === 'category' ? $value : null,
            approverRole: $this->approverRole ?: null,
            expiresHours: $this->expiresHours,
        ));

        $this->reset('name', 'covers', 'approverRole', 'expiresHours');
        unset($this->rules);
    }

    public function remove(int $id, SaveApprovalRuleAction $action): void
    {
        $action->remove(auth()->user(), ApprovalRule::findOrFail($id));

        unset($this->rules);
    }

    public function render()
    {
        return view('livewire.governance.approval-rules');
    }
}
