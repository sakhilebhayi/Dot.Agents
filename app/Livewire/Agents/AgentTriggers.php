<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\SaveTriggerAction;
use App\DTOs\Agents\SaveTriggerData;
use App\Models\AgentTrigger;
use App\Services\Triggers\TriggerFirer;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** What starts this agent or workflow. Sits on both of their pages. */
class AgentTriggers extends Component
{
    public const READ_ONLY_ACTIONS = [];

    #[Locked]
    public string $targetType;

    #[Locked]
    public int $targetId;

    public string $kind = AgentTrigger::SCHEDULE;

    public string $name = '';

    public string $instruction = '';

    /** @var array<string, mixed> */
    public array $settings = ['frequency' => 'daily', 'time' => '08:00', 'timezone' => 'Africa/Johannesburg', 'weekday' => 1, 'monthday' => 1, 'start_date' => '', 'end_date' => '', 'max_runs' => '', 'event' => 'knowledge.added'];

    public bool $adding = false;

    /** Shown once, straight after a webhook is made. */
    public ?string $newSecret = null;

    public ?string $newAddress = null;

    #[Computed]
    public function triggers()
    {
        return AgentTrigger::where('organization_id', TenantContext::id())->where('target_type', $this->targetType)->where('target_id', $this->targetId)->latest('id')->get();
    }

    public function save(SaveTriggerAction $action): void
    {
        $saved = $action->execute(new SaveTriggerData(
            actor: auth()->user(),
            organizationId: TenantContext::id(),
            targetType: $this->targetType,
            targetId: $this->targetId,
            kind: $this->kind,
            name: $this->name,
            instruction: $this->instruction ?: null,
            settings: collect($this->settings)->reject(fn ($value) => $value === '')->all(),
        ));

        if ($saved['secret'] !== null) {
            $this->newSecret = $saved['secret'];
            $this->newAddress = route('api.hooks', $saved['trigger']->uuid);
        }

        $this->reset('name', 'instruction', 'adding');
        unset($this->triggers);
    }

    public function toggle(int $id, SaveTriggerAction $action): void
    {
        $trigger = $this->trigger($id);

        $action->setActive(auth()->user(), $trigger, ! $trigger->is_active);
        unset($this->triggers);
    }

    public function fireNow(int $id, TriggerFirer $firer): void
    {
        abort_unless(auth()->user()->canInOrganization('agents.operate', TenantContext::id()), 403);

        $firer->fire($this->trigger($id), 'manual-'.now()->format('YmdHis'));
        unset($this->triggers);
    }

    public function remove(int $id, SaveTriggerAction $action): void
    {
        $action->remove(auth()->user(), $this->trigger($id));
        unset($this->triggers);
    }

    private function trigger(int $id): AgentTrigger
    {
        return AgentTrigger::where('organization_id', TenantContext::id())->where('target_type', $this->targetType)->where('target_id', $this->targetId)->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.agents.agent-triggers');
    }
}
