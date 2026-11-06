<?php

namespace App\Services\Triggers;

use App\Actions\Agents\StartRunAction;
use App\Actions\Workflows\StartWorkflowAction;
use App\DTOs\Agents\StartRunData;
use App\DTOs\Workflows\StartWorkflowData;
use App\Exceptions\RunRefused;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentTrigger;
use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Support\MarkedData;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Turns a trigger into work. Every fire has a key, so a repeated event or a retried delivery starts nothing twice. */
class TriggerFirer
{
    public function __construct(private readonly StartRunAction $runs, private readonly StartWorkflowAction $workflows) {}

    public function fire(AgentTrigger $trigger, string $key, ?string $content = null, bool $tainted = true): AgentRun|WorkflowExecution|null
    {
        $request = $this->request($trigger, $content);
        $idempotency = "trigger:{$trigger->id}:{$key}";

        try {
            $work = $trigger->target_type === AgentTrigger::WORKFLOW
                ? $this->workflows->execute(AgentWorkflow::findOrFail($trigger->target_id), new StartWorkflowData($request, idempotencyKey: $idempotency))
                : $this->runs->execute(AgentDeployment::findOrFail($trigger->target_id), new StartRunData(
                    $request, $trigger->kind, $trigger->uuid, $trigger->created_by, idempotencyKey: $idempotency, taint: $content !== null && $tainted ? 1 : 0,
                ));
        } catch (RunRefused|ValidationException|HttpException $e) {
            $trigger->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

            return null;
        }

        $trigger->update(['last_run_at' => now(), 'run_count' => $trigger->run_count + 1, 'last_error' => null]);

        return $work;
    }

    private function request(AgentTrigger $trigger, ?string $content): string
    {
        $instruction = trim((string) $trigger->instruction) ?: "Carry out your work for the trigger \"{$trigger->name}\".";

        return $content === null ? $instruction : $instruction."\n\nWhat arrived:\n".MarkedData::wrap('trigger', $content);
    }
}
