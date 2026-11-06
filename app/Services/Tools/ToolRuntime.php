<?php

namespace App\Services\Tools;

use App\Models\AgentApproval;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Services\Approvals\ApprovalDesk;
use App\Services\Governance\AuditService;
use App\Services\Runs\Journal;
use App\Services\Runs\ToolJournal;

/**
 * Between one model turn and the next, this works through what the model asked for, one effect per step. Effects are
 * planned from the journal, so a worker that dies after the model answered loses nothing.
 */
class ToolRuntime
{
    public const WORKED = 'worked';

    public const WAITING = 'waiting';

    public const NOTHING = 'nothing';

    public function __construct(
        private readonly ToolCatalogue $catalogue,
        private readonly ToolGate $gate,
        private readonly ToolExecutor $executor,
        private readonly Journal $journal,
        private readonly ToolJournal $toolJournal,
        private readonly AuditService $audit,
        private readonly ApprovalDesk $desk,
    ) {}

    /** @return list<array{name: string, description: string, schema: array<string, mixed>}> What the model is offered. */
    public function definitions(AgentRun $run): array
    {
        $offered = [];

        foreach ($this->grants($run) as $key => $grant) {
            $definition = $this->catalogue->find($run->organization_id, $key)?->definition();

            if ($definition !== null && $this->gate->refused($run, $definition, $grant) === null) {
                $offered[] = ['name' => $key, 'description' => $definition->description, 'schema' => $definition->schema];
            }
        }

        return $offered;
    }

    public function work(AgentRun $run): string
    {
        $this->planFromJournal($run);

        $effect = AgentEffect::where('agent_run_id', $run->id)
            ->whereIn('status', [AgentEffect::PLANNED, AgentEffect::APPROVED, AgentEffect::EXECUTING, AgentEffect::UNKNOWN])
            ->orderBy('id')->first();

        if ($effect === null) {
            return self::NOTHING;
        }

        return match ($effect->status) {
            AgentEffect::PLANNED => $this->decide($run, $effect),
            AgentEffect::APPROVED => $this->execute($run, $effect),
            default => $this->reconcile($run, $effect),
        };
    }

    private function decide(AgentRun $run, AgentEffect $effect): string
    {
        $definition = $this->catalogue->find($run->organization_id, $effect->tool_key)?->definition();
        $decision = $this->gate->decide($run, $definition, $this->grants($run)[$effect->tool_key] ?? null, $effect->arguments);

        return match ($decision->verdict) {
            GateDecision::ALLOW => $this->execute($run, $effect),
            GateDecision::ASK => $this->wait($run, $effect, $decision),
            default => $this->deny($run, $effect, $decision),
        };
    }

    private function execute(AgentRun $run, AgentEffect $effect): string
    {
        $tool = $this->catalogue->find($run->organization_id, $effect->tool_key);

        // Asked again at the last moment: an answer given days ago does not outlive the tool being taken away.
        if ($refused = $this->gate->refused($run, $tool?->definition(), $this->grants($run)[$effect->tool_key] ?? null)) {
            return $this->deny($run, $effect, $refused);
        }

        if (! $this->desk->matches($effect, $tool->definition())) {
            return $this->deny($run, $effect, GateDecision::deny('approval_mismatch', 'What was approved is not what was about to run.'));
        }

        $this->executor->execute($run, $effect, $tool);

        return self::WORKED;
    }

    private function wait(AgentRun $run, AgentEffect $effect, GateDecision $decision): string
    {
        $definition = $this->catalogue->find($run->organization_id, $effect->tool_key)->definition();
        $approval = AgentApproval::where('agent_effect_id', $effect->id)->where('status', 'pending')->first()
            ?? $this->desk->open($run, $effect, $definition, $decision);

        $this->journal->write($run, AgentRunStep::APPROVAL, 'Waiting for a person', [
            'tool_key' => $effect->tool_key,
            'status' => 'waiting',
            'payload' => ['effect' => $effect->id, 'approval' => $approval->uuid, 'code' => $decision->code, 'reason' => $decision->reason],
        ]);

        return self::WAITING;
    }

    private function deny(AgentRun $run, AgentEffect $effect, GateDecision $decision): string
    {
        $effect->update(['status' => AgentEffect::DENIED, 'error_code' => $decision->code, 'error_message' => $decision->reason]);

        $this->toolJournal->result($run, $effect, 'A tool call was refused', "Refused: {$decision->reason}", ['error' => true, 'code' => $decision->code]);
        $this->audit->logAgentAction($run->deployment, 'tool_denied', ['run' => $run->uuid, 'tool' => $effect->tool_key, 'code' => $decision->code]);

        return self::WORKED;
    }

    /**
     * An effect left mid-call. What can safely be asked again is asked again. Anything else is never repeated blind:
     * the model is told it is unknown, and the record stays for a person to check.
     */
    private function reconcile(AgentRun $run, AgentEffect $effect): string
    {
        $tool = $this->catalogue->find($run->organization_id, $effect->tool_key);

        if ($tool !== null && $tool->definition()->safeToRepeat && $effect->attempts <= $tool->definition()->retries + 1) {
            return $this->execute($run, $effect);
        }

        $effect->update(['status' => AgentEffect::UNRESOLVED, 'error_code' => 'outcome_unknown', 'error_message' => 'Whether this happened is not known.']);

        $this->toolJournal->result($run, $effect, 'A tool call has an unknown outcome', 'Whether this happened is not known, and it was not repeated. Do not try it again. Tell the person.', ['error' => true]);
        $this->audit->logAgentAction($run->deployment, 'tool_outcome_unknown', ['run' => $run->uuid, 'tool' => $effect->tool_key, 'effect' => $effect->id]);

        return self::WORKED;
    }

    private function planFromJournal(AgentRun $run): void
    {
        $turn = AgentRunStep::where('agent_run_id', $run->id)->where('kind', AgentRunStep::MODEL)->where('status', 'done')->orderByDesc('sequence')->first();

        foreach ($turn?->payload['message']['calls'] ?? [] as $call) {
            AgentEffect::firstOrCreate(
                ['idempotency_key' => "{$run->uuid}:{$turn->sequence}:{$call['id']}"],
                ['organization_id' => $run->organization_id, 'agent_run_id' => $run->id, 'call_id' => $call['id'], 'tool_key' => $call['name'], 'arguments' => $call['arguments']],
            );
        }
    }

    /** @return array<string, array{key: string, mode: string}> */
    private function grants(AgentRun $run): array
    {
        $settings = $run->configVersion->settings();
        $grants = collect($settings['tools'])->keyBy('key');

        // Choosing knowledge or long-term memory for an agent is how it is given these two.
        if ($settings['knowledge'] !== []) {
            $grants['knowledge_search'] = ['key' => 'knowledge_search', 'mode' => 'auto'];
        }

        if ($settings['memory']['long_term']) {
            $grants['remember_fact'] = ['key' => 'remember_fact', 'mode' => 'auto'];
        }

        return $grants->all();
    }
}
