<?php

namespace App\Services\Runs;

use App\Jobs\RunAgentStep;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Services\Models\ModelFailure;
use App\Services\Tools\ToolRuntime;
use Throwable;

/**
 * The engine. It takes the lease, does exactly one thing, writes it down and lets go. Nothing is held in memory between
 * steps, so a worker can die anywhere and the sweeper carries on from the journal.
 */
class RunStepper
{
    public const CONTINUE = 'continue';

    public const LATER = 'later';

    public const IDLE = 'idle';

    /** How long a run told to come back later waits. */
    private int $wait = 0;

    public function __construct(
        private readonly RunLease $lease,
        private readonly RunGuard $guard,
        private readonly ModelTurn $turn,
        private readonly ToolRuntime $tools,
        private readonly RunFinisher $finisher,
        private readonly Journal $journal,
    ) {}

    public function advance(int $runId, bool $dispatchNext = true): string
    {
        $owner = $this->lease->take($runId);

        if ($owner === null) {
            return self::IDLE;
        }

        $run = AgentRun::findOrFail($runId);
        $next = self::IDLE;

        try {
            $next = $this->step($run);
        } catch (Throwable $e) {
            report($e);
            $this->finisher->fail($run, 'engine_error', 'Something went wrong inside the platform. The run stopped safely.');
        } finally {
            $this->lease->give($runId, $owner);
        }

        // Only ever queued once the lease is back, so the next step can take it.
        if ($next === self::LATER) {
            RunAgentStep::dispatch($run->id, $run->organization_id)->delay(now()->addSeconds($this->wait));
        }

        if ($next === self::CONTINUE && $dispatchNext) {
            RunAgentStep::dispatch($run->id, $run->organization_id);
        }

        return $next;
    }

    private function step(AgentRun $run): string
    {
        if ($stop = $this->guard->check($run)) {
            $this->finisher->stop($run, $stop['status'], $stop['code'], $stop['message']);

            return self::IDLE;
        }

        if ($run->status === AgentRun::QUEUED) {
            if ($this->atCapacity($run)) {
                $this->wait = 30;

                return self::LATER;
            }

            $run->update(['status' => AgentRun::RUNNING, 'started_at' => now()]);
            $this->journal->write($run, AgentRunStep::SYSTEM, 'Started from '.str_replace('_', ' ', $run->trigger_type), [
                'payload' => ['version' => $run->configVersion->number],
            ]);
        }

        // Finish what the last turn asked for before asking the model anything else.
        return match ($this->tools->work($run)) {
            ToolRuntime::WORKED => self::CONTINUE,
            ToolRuntime::WAITING => $this->hold($run, AgentRun::WAITING_APPROVAL),
            ToolRuntime::DELEGATED => $this->hold($run, AgentRun::WAITING_AGENT),
            default => $run->trigger_type === 'workflow_tool' ? $this->finishToolStep($run) : $this->askModel($run),
        };
    }

    private function askModel(AgentRun $run): string
    {
        try {
            $reply = $this->turn->take($run);
        } catch (ModelFailure $failure) {
            return $this->modelFailed($run, $failure);
        }

        $run->update(['attempt' => 0]);

        if ($reply->calls !== []) {
            return self::CONTINUE;
        }

        $this->finisher->complete($run, $reply->text, cutOff: $reply->finish === 'length');

        return self::IDLE;
    }

    private function hold(AgentRun $run, string $status): string
    {
        $run->update(['status' => $status]);

        return self::IDLE;
    }

    /** A tool step has no model. Its result is the tool's own, or its failure. */
    private function finishToolStep(AgentRun $run): string
    {
        $effect = $run->effects()->latest('id')->first();

        $effect?->status === AgentEffect::DONE
            ? $this->finisher->complete($run, (string) ($effect->result['content'] ?? ''))
            : $this->finisher->fail($run, $effect->error_code ?? 'tool_failed', $effect->error_message ?? 'The tool did not run.');

        return self::IDLE;
    }

    private function modelFailed(AgentRun $run, ModelFailure $failure): string
    {
        $this->journal->write($run, AgentRunStep::MODEL, 'The model did not answer', [
            'status' => 'failed',
            'error_code' => $failure->kind,
            'error_message' => $failure->getMessage(),
            'attempt' => $run->attempt + 1,
        ]);

        if ($failure->isTransient() && $run->attempt < (int) $run->limits['retries']) {
            $run->increment('attempt');
            $this->wait = 15 * $run->attempt;

            return self::LATER;
        }

        $this->finisher->fail($run, $failure->kind, $failure->getMessage());

        return self::IDLE;
    }

    private function atCapacity(AgentRun $run): bool
    {
        $limit = (int) $run->limits['concurrency'];

        return AgentRun::where('agent_deployment_id', $run->agent_deployment_id)->where('status', AgentRun::RUNNING)->count() >= $limit;
    }
}
