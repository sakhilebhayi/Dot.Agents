<?php

namespace App\Services\Evals;

use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentTestCase;
use App\Models\AgentTestResult;

/** Marks a finished test run against what the test case asked for. Every check is a fact about the run, not a judgement. */
class TestMarker
{
    public function runEnded(AgentRun $run): void
    {
        $result = AgentTestResult::where('agent_run_id', $run->id)->first();

        if ($result === null) {
            return;
        }

        $checks = $this->checks($result->testCase, $run);

        $result->update([
            'status' => collect($checks)->every('passed') ? AgentTestResult::PASSED : AgentTestResult::FAILED,
            'checks' => $checks,
            'measures' => [
                'seconds' => $run->started_at?->diffInSeconds($run->finished_at ?? now()),
                'steps' => $run->steps_used,
                'tools' => $run->tool_calls_used,
                'tokens' => $run->tokens_used,
                'cost' => (float) $run->cost,
            ],
        ]);
    }

    /** @return list<array{name: string, passed: bool}> */
    private function checks(AgentTestCase $case, AgentRun $run): array
    {
        $output = mb_strtolower((string) $run->output);
        $used = AgentEffect::where('agent_run_id', $run->id)->pluck('status', 'tool_key');
        $wanted = $case->checks;

        return [
            ['name' => 'It finished the task', 'passed' => $run->status === AgentRun::COMPLETED],
            ...array_map(fn ($text) => ['name' => "It says \"{$text}\"", 'passed' => str_contains($output, mb_strtolower($text))], (array) ($wanted['contains'] ?? [])),
            ...array_map(fn ($text) => ['name' => "It does not say \"{$text}\"", 'passed' => ! str_contains($output, mb_strtolower($text))], (array) ($wanted['not_contains'] ?? [])),
            ...array_map(fn ($tool) => ['name' => "It used {$tool}", 'passed' => ($used[$tool] ?? null) === AgentEffect::DONE], (array) ($wanted['uses_tools'] ?? [])),
            ...array_map(fn ($tool) => ['name' => "It did not use {$tool}", 'passed' => ! $used->has($tool)], (array) ($wanted['avoids_tools'] ?? [])),
            ...(filled($wanted['cites'] ?? null) ? [['name' => "It used the document {$wanted['cites']}", 'passed' => $this->cited($run, $wanted['cites'])]] : []),
            ['name' => 'It stayed inside its permissions', 'passed' => ! AgentEffect::where('agent_run_id', $run->id)->where('status', AgentEffect::DENIED)->exists()],
        ];
    }

    private function cited(AgentRun $run, string $title): bool
    {
        return AgentRunStep::where('agent_run_id', $run->id)->where('kind', AgentRunStep::TOOL)->get()
            ->contains(fn (AgentRunStep $step) => collect($step->payload['sources'] ?? [])->contains(fn ($source) => mb_strtolower($source['title']) === mb_strtolower($title)));
    }
}
