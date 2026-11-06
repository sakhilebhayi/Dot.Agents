<?php

namespace App\Services\Runs;

use App\Models\AgentRun;
use App\Models\AgentRunStep;

/**
 * Checked before every step, against the database, not against what the run remembers. A paused agent, a suspended
 * business, a cancelled run and a spent budget all stop here, between steps, never halfway through one.
 *
 * @phpstan-type Stop array{status: string, code: string, message: string}
 */
class RunGuard
{
    /** @return array{status: string, code: string, message: string}|null */
    public function check(AgentRun $run): ?array
    {
        if ($run->cancel_requested_at !== null) {
            return $this->stop(AgentRun::CANCELLED, 'cancelled', 'Cancelled by a person.');
        }

        $deployment = $run->deployment;

        if ($deployment === null) {
            return $this->stop(AgentRun::FAILED, 'agent_gone', 'The agent no longer exists.');
        }

        if ($deployment->organization?->status === 'suspended') {
            return $this->stop(AgentRun::STOPPED, 'business_suspended', 'This business is suspended.');
        }

        if (! $run->is_test && $deployment->status !== 'active') {
            return $this->stop(AgentRun::STOPPED, 'agent_stopped', "{$deployment->name} was {$deployment->status} while this was running.");
        }

        return $this->overBudget($run);
    }

    /** @return array{status: string, code: string, message: string}|null */
    private function overBudget(AgentRun $run): ?array
    {
        $limits = $run->limits;

        $over = match (true) {
            $run->steps_used >= (int) $limits['steps'] => ['limit_steps', "Stopped after {$limits['steps']} model turns, the limit set for this agent."],
            $run->tool_calls_used > (int) $limits['tool_calls'] => ['limit_tools', "Stopped after {$limits['tool_calls']} tool calls, the limit set for this agent."],
            $run->tokens_used >= (int) $limits['tokens'] => ['limit_tokens', "Stopped at {$limits['tokens']} tokens, the limit set for this agent."],
            (float) $run->cost >= (float) $limits['cost'] => ['limit_cost', 'Stopped at the spending limit set for this agent.'],
            $this->secondsWorked($run) >= (int) $limits['seconds'] => ['limit_time', 'Stopped at the time limit set for this agent.'],
            default => null,
        };

        return $over === null ? null : $this->stop(AgentRun::STOPPED, $over[0], $over[1]);
    }

    /** Time waiting for a person does not count against a run. */
    private function secondsWorked(AgentRun $run): int
    {
        $started = $run->started_at;
        if ($started === null) {
            return 0;
        }

        $waited = (int) AgentRunStep::where('agent_run_id', $run->id)->where('kind', AgentRunStep::APPROVAL)->sum('latency_ms') / 1000;

        return (int) $started->diffInSeconds(now()) - (int) $waited;
    }

    /** @return array{status: string, code: string, message: string} */
    private function stop(string $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message');
    }
}
