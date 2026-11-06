<?php

namespace App\Services\Runs;

use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;

/** Writes what a tool call came to, in the shape the model is shown it next turn. */
class ToolJournal
{
    public function __construct(private readonly Journal $journal) {}

    /** @param  array<string, mixed>  $extra */
    public function result(AgentRun $run, AgentEffect $effect, string $label, string $content, array $extra = []): void
    {
        $this->journal->write($run, AgentRunStep::TOOL, $label, [
            'tool_key' => $effect->tool_key,
            'attempt' => $effect->attempts,
            'status' => isset($extra['error']) ? 'failed' : 'done',
            'error_code' => $effect->error_code,
            'error_message' => $effect->error_message,
            'payload' => [
                'effect' => $effect->id,
                'message' => ['role' => 'tool', 'results' => [[
                    'id' => $effect->call_id,
                    'name' => $effect->tool_key,
                    'arguments' => $effect->arguments,
                    'content' => $content,
                ]]],
                ...$extra,
            ],
        ]);
    }
}
