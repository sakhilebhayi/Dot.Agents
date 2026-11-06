<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\SaveTriggerData;
use App\Models\AgentDeployment;
use App\Models\AgentTrigger;
use App\Models\AgentWorkflow;
use App\Models\User;
use App\Services\Governance\AuditService;
use App\Services\Triggers\ScheduleClock;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/** Sets up what starts work. The target has to be in the business, and a webhook's secret is shown once and never again. */
class SaveTriggerAction
{
    public function __construct(private readonly AuditService $audit) {}

    /** @return array{trigger: AgentTrigger, secret: ?string} */
    public function execute(SaveTriggerData $data): array
    {
        $this->allow($data->actor, $data->organizationId);
        $this->assertTarget($data);
        $this->validate($data);

        $secret = $data->kind === AgentTrigger::WEBHOOK ? Str::random(48) : null;

        $trigger = AgentTrigger::create([
            'organization_id' => $data->organizationId,
            'target_type' => $data->targetType,
            'target_id' => $data->targetId,
            'kind' => $data->kind,
            'name' => $data->name,
            'instruction' => $data->instruction,
            'settings' => $data->settings,
            'secret' => $secret,
            'next_run_at' => $data->kind === AgentTrigger::SCHEDULE ? ScheduleClock::next($data->settings) : null,
            'created_by' => $data->actor->id,
        ]);

        $this->audit->logUserAction(event: 'trigger.created', description: "Set up the trigger {$trigger->name}", subject: $trigger);

        return ['trigger' => $trigger, 'secret' => $secret];
    }

    public function setActive(User $actor, AgentTrigger $trigger, bool $active): void
    {
        $this->allow($actor, $trigger->organization_id);

        $trigger->update([
            'is_active' => $active,
            'next_run_at' => $active && $trigger->kind === AgentTrigger::SCHEDULE ? ScheduleClock::next($trigger->settings) : null,
        ]);
    }

    public function remove(User $actor, AgentTrigger $trigger): void
    {
        $this->allow($actor, $trigger->organization_id);

        $trigger->delete();
        $this->audit->logUserAction(event: 'trigger.removed', description: "Removed the trigger {$trigger->name}", subject: $trigger);
    }

    private function allow(User $actor, int $organizationId): void
    {
        if (! $actor->canInOrganization('agents.manage', $organizationId)) {
            throw new AuthorizationException('You cannot change what starts work in this business.');
        }
    }

    private function assertTarget(SaveTriggerData $data): void
    {
        $model = $data->targetType === AgentTrigger::WORKFLOW ? AgentWorkflow::class : AgentDeployment::class;

        if (! $model::where('organization_id', $data->organizationId)->whereKey($data->targetId)->exists()) {
            throw ValidationException::withMessages(['target' => 'That is not in this business.']);
        }
    }

    private function validate(SaveTriggerData $data): void
    {
        Validator::make(['name' => $data->name, 'kind' => $data->kind, ...$data->settings], [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in([AgentTrigger::SCHEDULE, AgentTrigger::WEBHOOK, AgentTrigger::EVENT])],
            ...match ($data->kind) {
                AgentTrigger::SCHEDULE => [
                    'frequency' => ['required', Rule::in(array_keys(ScheduleClock::FREQUENCIES))],
                    'time' => ['required', 'date_format:H:i'],
                    'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
                    'weekday' => ['nullable', 'integer', 'between:0,6'],
                    'monthday' => ['nullable', 'integer', 'between:1,31'],
                    'start_date' => [$data->settings['frequency'] === 'once' ? 'required' : 'nullable', 'date'],
                    'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                    'max_runs' => ['nullable', 'integer', 'min:1'],
                ],
                AgentTrigger::EVENT => ['event' => ['required', Rule::in(array_keys(AgentTrigger::EVENTS))]],
                default => [],
            },
        ])->validate();
    }
}
