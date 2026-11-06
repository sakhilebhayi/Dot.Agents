<?php

namespace App\Services\Tools;

use App\Contracts\AgentTool;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Services\AI\OutputModerationService;
use App\Services\Governance\AuditService;
use App\Services\Runs\ToolJournal;
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
    ) {}

    public function execute(AgentRun $run, AgentEffect $effect, AgentTool $tool): void
    {
        $definition = $tool->definition();

        $effect->update(['status' => AgentEffect::EXECUTING, 'attempts' => $effect->attempts + 1, 'executed_at' => now()]);
        $run->increment('tool_calls_used');

        try {
            $output = $tool->run(new ToolContext($run, $effect->idempotency_key), $effect->arguments);
        } catch (ToolFailure $failure) {
            $this->failed($run, $effect, $definition, $failure);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->failed($run, $effect, $definition, new ToolFailure('The tool stopped unexpectedly.', outcomeUnknown: ! $definition->safeToRepeat));

            return;
        }

        $this->succeeded($run, $effect, $definition, $output->content);
    }

    private function succeeded(AgentRun $run, AgentEffect $effect, ToolDefinition $tool, string $content): void
    {
        $content = Str::limit($this->moderation->redactSecrets($content), self::MAX_RESULT, ' [cut short]');

        // The run is marked before the model can see what came back.
        if ($tool->untrustedOutput) {
            RunTaint::add($run, RunTaint::UNTRUSTED);
        }

        if ($tool->readsSensitive) {
            RunTaint::add($run, RunTaint::SENSITIVE);
        }

        $effect->update(['status' => AgentEffect::DONE, 'result' => ['content' => $content]]);

        $signal = $tool->untrustedOutput && $this->audit->detectPromptInjection($content, $run->deployment);

        $this->journal->result($run, $effect, $tool->label, $this->asData($tool->key, $content), ['signal' => $signal, 'version' => $tool->version]);
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

    /** Whatever a tool returned stays marked as data, and cannot close the marking itself. */
    private function asData(string $source, string $content): string
    {
        $safe = (string) preg_replace('/<(\/?)\s*data/i', '&lt;$1data', $content);

        return "<data source=\"{$source}\">\n{$safe}\n</data>";
    }
}
