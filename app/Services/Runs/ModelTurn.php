<?php

namespace App\Services\Runs;

use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Services\Models\ModelCatalog;
use App\Services\Models\ModelFailure;
use App\Services\Models\ModelGateway;
use App\Services\Models\ModelReply;
use App\Services\Models\ModelRequest;
use App\Services\Models\ScriptedGateway;
use App\Services\Tools\ToolRuntime;

/** One conversation turn with the model, written to the journal and the ledger before anything acts on it. */
class ModelTurn
{
    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly ModelCatalog $catalog,
        private readonly PromptAssembler $prompt,
        private readonly ToolRuntime $tools,
        private readonly Journal $journal,
        private readonly UsageLedger $ledger,
    ) {}

    /** @throws ModelFailure */
    public function take(AgentRun $run): ModelReply
    {
        $config = $run->configVersion->settings();
        $model = $this->model($run, $config['model']);
        $started = microtime(true);

        $reply = $this->gateway->turn(new ModelRequest(
            model: $model,
            system: $this->prompt->system($run),
            messages: $this->prompt->messages($run),
            tools: $this->tools->definitions($run),
            maxTokens: (int) config('agents.model.max_output_tokens'),
        ));

        $step = $this->journal->write($run, AgentRunStep::MODEL, $reply->calls === [] ? 'Wrote an answer' : 'Decided what to do next', [
            'provider' => $reply->provider,
            'model' => $reply->model,
            'tokens_in' => $reply->inputTokens,
            'tokens_out' => $reply->outputTokens,
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'attempt' => $run->attempt + 1,
            'payload' => ['message' => ['role' => 'assistant', 'text' => $reply->text, 'calls' => $reply->calls], 'finish' => $reply->finish],
        ]);

        $usage = $this->ledger->record($run, $step, $reply);
        $step->update(['cost' => $usage->cost ?? 0]);
        $run->increment('steps_used');

        return $reply;
    }

    /**
     * The model a step uses. A fallback stays inside the provider the business chose, and only applies after a
     * transient failure, which the run records.
     *
     * @param  array{model: ?string, fallback: bool}  $wanted
     */
    private function model(AgentRun $run, array $wanted): string
    {
        $model = $this->catalog->resolve($wanted['model']);

        if ($model === null) {
            if ($this->gateway instanceof ScriptedGateway) {
                return 'scripted';
            }

            throw new ModelFailure('no_provider', 'No AI provider is set up on this platform yet.');
        }

        if ($run->attempt > 0 && $wanted['fallback'] && ($fallback = $this->catalog->fallbackFor($model))) {
            return $fallback;
        }

        return $model;
    }
}
