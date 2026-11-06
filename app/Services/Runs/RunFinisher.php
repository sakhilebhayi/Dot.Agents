<?php

namespace App\Services\Runs;

use App\Events\AgentTaskCompleted;
use App\Events\AgentTaskFailed;
use App\Jobs\AdvanceWorkflow;
use App\Models\AgentApproval;
use App\Models\AgentMessage;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentTask;
use App\Services\AI\OutputModerationService;
use App\Services\Governance\AuditService;
use Illuminate\Support\Str;

/** The last step of every run: what the person sees, what is kept, and the truth about how it ended. */
class RunFinisher
{
    public function __construct(
        private readonly Journal $journal,
        private readonly OutputModerationService $moderation,
        private readonly UsageLedger $ledger,
        private readonly AuditService $audit,
        private readonly OutputChannels $channels,
        private readonly DelegationReturn $delegation,
    ) {}

    public function complete(AgentRun $run, string $text, bool $cutOff = false): void
    {
        ['content' => $clean, 'scan' => $scan] = $this->moderation->scanAndRedact($this->channels->close($run, $text), ['run_id' => $run->id]);

        if ($scan['verdict'] === OutputModerationService::BLOCK) {
            $clean = $this->moderation->blockedResponse();
        }

        if ($run->configVersion->settings()['output']['format'] === 'json' && json_decode($clean) === null) {
            $this->fail($run, 'invalid_output', 'The agent was asked for structured data and did not produce it.');

            return;
        }

        $this->journal->write($run, AgentRunStep::SYSTEM, $cutOff ? 'Finished, but the answer hit the length limit' : 'Finished', [
            'payload' => ['moderation' => $scan['verdict'], 'flagged' => $scan['flagged']],
        ]);

        $this->close($run, AgentRun::COMPLETED, ['output' => $clean, 'error_code' => $cutOff ? 'output_cut_off' : null]);
        $this->reply($run, $clean, $scan);
        $this->settleTask($run, 'completed', $clean);
    }

    public function fail(AgentRun $run, string $code, string $message): void
    {
        $this->stop($run, AgentRun::FAILED, $code, $message);
    }

    public function stop(AgentRun $run, string $status, string $code, string $message): void
    {
        $this->journal->write($run, AgentRunStep::SYSTEM, $status === AgentRun::CANCELLED ? 'Cancelled' : 'Stopped', [
            'status' => 'failed', 'error_code' => $code, 'error_message' => $message,
        ]);

        $this->close($run, $status, ['error_code' => $code, 'error_message' => $message]);
        $this->ledger->markWasted($run);
        $this->reply($run, "I could not finish this. {$message}");
        $this->settleTask($run, $status === AgentRun::CANCELLED ? 'cancelled' : 'failed', $message);
    }

    /** @param  array<string, mixed>  $attributes */
    private function close(AgentRun $run, string $status, array $attributes): void
    {
        $run->update(['status' => $status, 'finished_at' => now(), ...$attributes]);

        $this->notifyWaiters($run);

        // Nothing is left asking for an answer that can no longer change anything.
        AgentApproval::where('agent_run_id', $run->id)->where('status', 'pending')->update(['status' => 'withdrawn']);

        if ($run->deployment !== null) {
            $run->deployment->update(['last_active_at' => now()]);
            $this->audit->logAgentAction($run->deployment, "run_{$status}", [
                'run' => $run->uuid, 'version' => $run->config_version_id, 'tokens' => $run->tokens_used, 'cost' => $run->cost, 'error_code' => $run->error_code,
            ]);
        }
    }

    /** Whoever was waiting on this run carries on: a supervisor, or the workflow it was a step of. */
    private function notifyWaiters(AgentRun $run): void
    {
        if ($run->parent_run_id !== null) {
            $this->delegation->back($run);
        }

        if ($run->workflow_execution_id !== null) {
            AdvanceWorkflow::dispatch($run->workflow_execution_id, $run->organization_id);
        }
    }

    /**
     * A chat gets its reply here, whichever way the run ended, so nobody is left watching a typing indicator.
     *
     * @param  array<string, mixed>|null  $scan
     */
    private function reply(AgentRun $run, string $text, ?array $scan = null): void
    {
        if ($run->agent_session_id === null) {
            return;
        }

        AgentMessage::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $run->organization_id,
            'session_id' => $run->agent_session_id,
            'role' => 'assistant',
            'content' => $text,
            'token_count' => $run->tokens_used,
            'cost' => $run->cost,
            'model_used' => $run->steps()->where('kind', AgentRunStep::MODEL)->latest('sequence')->value('model'),
            'flagged' => $scan['flagged'] ?? false,
            'flag_reason' => $scan['flag_reason'] ?? null,
            'metadata' => ['run' => $run->uuid, 'moderation_verdict' => $scan['verdict'] ?? null],
        ]);

        $run->session?->increment('message_count', 2);
        $run->session?->increment('token_count', $run->tokens_used);
    }

    private function settleTask(AgentRun $run, string $status, string $text): void
    {
        $task = $run->agent_task_id === null ? null : AgentTask::find($run->agent_task_id);

        if ($task === null) {
            return;
        }

        $task->update(['status' => $status, 'result_summary' => Str::limit($text, 1000), 'cost' => $run->cost, 'token_count' => $run->tokens_used, 'completed_at' => now()]);

        $status === 'completed' ? AgentTaskCompleted::dispatch($task) : AgentTaskFailed::dispatch($task, $text);
    }
}
