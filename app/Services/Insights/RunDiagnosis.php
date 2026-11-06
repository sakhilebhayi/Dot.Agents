<?php

namespace App\Services\Insights;

use App\Models\AgentRun;
use App\Models\AgentRunStep;

/** Says what went wrong with a run in words a business owner can act on. Only known causes are named. */
class RunDiagnosis
{
    private const KNOWN = [
        'no_provider' => ['No AI provider is set up on this platform.', false, 'A platform administrator needs to add a provider key.'],
        'provider_key' => ['The AI provider refused the key on this platform.', false, 'A platform administrator needs to check the provider key.'],
        'rate_limited' => ['The AI provider asked us to slow down.', true, 'Try again in a few minutes.'],
        'overloaded' => ['The AI provider was overloaded.', true, 'Try again in a few minutes.'],
        'timeout' => ['The AI provider could not be reached.', true, 'Try again in a few minutes.'],
        'provider_error' => ['The AI provider gave an answer that could not be read.', true, 'Try again. If it keeps happening, tell a platform administrator.'],
        'invalid_request' => ['The AI provider refused this request.', false, 'Check the agent\'s setup and the model it uses.'],
        'invalid_output' => ['The agent was asked for structured data and did not produce it.', true, 'Try again, or make the instructions clearer about the format.'],
        'limit_steps' => ['It reached the limit on model turns for one run.', true, 'Raise the limit in the agent\'s setup, or give it a smaller job.'],
        'limit_tools' => ['It reached the limit on tool calls for one run.', true, 'Raise the limit in the agent\'s setup, or give it a smaller job.'],
        'limit_tokens' => ['It reached the limit on tokens for one run.', true, 'Raise the limit in the agent\'s setup, or give it less to read.'],
        'limit_cost' => ['It reached the spending limit for one run.', true, 'Raise the limit in the agent\'s setup.'],
        'limit_time' => ['It reached the time limit for one run.', true, 'Raise the limit in the agent\'s setup, or give it a smaller job.'],
        'agent_stopped' => ['The agent was paused or changed while this was running.', true, 'Resume the agent, then try again.'],
        'business_suspended' => ['This business is suspended.', false, 'Contact whoever manages this account.'],
        'agent_gone' => ['The agent no longer exists.', false, 'Nothing can be done with this run.'],
        'engine_error' => ['Something went wrong inside the platform.', true, 'Try again. If it keeps happening, tell a platform administrator.'],
    ];

    /** @return array{what: string, where: string, recoverable: bool, action: string}|null */
    public function for(AgentRun $run): ?array
    {
        if (! in_array($run->status, [AgentRun::FAILED, AgentRun::STOPPED], true)) {
            return null;
        }

        [$what, $recoverable, $action] = self::KNOWN[$run->error_code] ?? [(string) $run->error_message, true, 'Read the record below, then try again.'];

        $last = AgentRunStep::where('agent_run_id', $run->id)->where('status', 'failed')->orderBy('sequence')->first()
            ?? AgentRunStep::where('agent_run_id', $run->id)->orderByDesc('sequence')->first();

        return ['what' => $what, 'where' => $last === null ? 'Before it began.' : "At step {$last->sequence}: {$last->title}.", 'recoverable' => $recoverable, 'action' => $action];
    }
}
