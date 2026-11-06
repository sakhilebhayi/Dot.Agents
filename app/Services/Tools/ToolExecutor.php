<?php

namespace App\Services\Tools;

use App\Contracts\AgentTool;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Services\AI\OutputModerationService;
use App\Services\Governance\AuditService;
use App\Services\Runs\DelegationReturn;
use App\Services\Runs\ToolJournal;
use App\Support\MarkedData;
use App\Support\RunTaint;
use Illuminate\Support\Str;
use Throwable;

/**
 * Carries out one approved effect. The row says `executing` before the tool runs, so a worker that dies here leaves a
 * record of what it was doing, and the sweeper marks it unknown rather than repeating it.
 */
class ToolExecutor
{
    private const MAX_RESULT = 20000;

    public function __construct(
        private readonly ToolJournal $journal,
        private readonly OutputModerationService $moderation,
        private readonly AuditService $audit,
        private readonly DelegationReturn $delegation,
    ) {}

    public function execute(AgentRun $run, AgentEffect $effect, AgentTool $tool): void
    {
        $definition = $tool->definition();

        $effect->update(['status' => AgentEffect::EXECUTING, 'attempts' => $effect->attempts + 1, 'executed_at' => now()]);
        $run->increment('tool_calls_used');

        try {
            $output = $this->outcome($run, $effect, $tool);
        } catch (ToolSuspended $suspended) {
            $effect->update(['status' => AgentEffect::WAITING, 'child_run_id' => $suspended->childRunId]);

            // A quick teammate may already be done, before anyone was waiting for it.
            $child = AgentRun::find($suspended->childRunId);

            if ($child?->isFinished()) {
                $this->delegation->back($child);
            }

            return;
        } catch (ToolFailure $failure) {
            $this->failed($run, $effect, $definition, $failure);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->failed($run, $effect, $definition, new ToolFailure('The tool stopped unexpectedly.', outcomeUnknown: ! $definition->safeToRepeat));

            return;
        }

        $this->succeeded($run, $effect, $definition, $output);
    }

    /** In a test nothing outside the platform is touched and nothing is changed: the call is written down instead. */
    private function outcome(AgentRun $run, AgentEffect $effect, AgentTool $tool): ToolOutput
    {
        $definition = $tool->definition();

        if ($run->is_test && ($run->limits['fail_tool'] ?? null) === $definition->key) {
            throw new ToolFailure('This tool was set to fail for the test.');
        }

        if ($run->is_test && ($definition->writesState || $definition->externalReach)) {
            return new ToolOutput('[Test run] Nothing was done. It would have run with: '.json_encode($effect->arguments, JSON_UNESCAPED_UNICODE));
        }

        return $tool->run(new ToolContext($run, $effect->idempotency_key), $effect->arguments);
    }

    private function succeeded(AgentRun $run, AgentEffect $effect, ToolDefinition $tool, ToolOutput $output): void
    {
        $content = Str::limit($this->moderation->redactSecrets($output->content), self::MAX_RESULT, ' [cut short]');
        $untrusted = $tool->untrustedOutput || $output->untrusted;

        // The run is marked before the model can see what came back.
        if ($untrusted) {
            RunTaint::add($run, RunTaint::UNTRUSTED);
        }

        if ($tool->readsSensitive) {
            RunTaint::add($run, RunTaint::SENSITIVE);
        }

        $effect->update(['status' => AgentEffect::DONE, 'result' => ['content' => $content]]);

        $signal = $untrusted && $this->audit->detectPromptInjection($content, $run->deployment);

        $this->journal->result($run, $effect, $tool->label, MarkedData::wrap($tool->key, $content), ['signal' => $signal, 'version' => $tool->version, 'sources' => $output->sources]);
        $this->audit->logAgentAction($run->deployment, 'tool_used', ['run' => $run->uuid, 'tool' => $tool->key, 'version' => $tool->version, 'effect' => $effect->id]);
    }

    private function failed(AgentRun $run, AgentEffect $effect, ToolDefinition $tool, ToolFailure $failure): void
    {
        if ($failure->outcomeUnknown && ! $tool->safeToRepeat) {
            $effect->update(['status' => AgentEffect::UNKNOWN, 'error_code' => 'outcome_unknown', 'error_message' => $failure->getMessage()]);

            return;
        }

        if ($failure->transient && $tool->safeToRepeat && $effect->attempts <= $tool->retries) {
            $effect->update(['status' => AgentEffect::APPROVED, 'error_code' => 'retrying', 'error_message' => $failure->getMessage()]);

            return;
        }

        $effect->update(['status' => AgentEffect::FAILED, 'error_code' => 'tool_failed', 'error_message' => $failure->getMessage()]);
        $this->journal->result($run, $effect, $tool->label, "This did not work: {$failure->getMessage()}", ['error' => true]);
        $this->audit->logAgentAction($run->deployment, 'tool_failed', ['run' => $run->uuid, 'tool' => $tool->key, 'effect' => $effect->id]);
    }
}
