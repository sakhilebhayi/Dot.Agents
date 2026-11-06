<?php

namespace App\Services\Models;

use RuntimeException;

/**
 * A stand-in model for tests and local runs, which answers from a script. It is bound only when AI_PROVIDER=scripted
 * outside production, and every step it produces says so. It proves the engine, never a model.
 */
class ScriptedGateway implements ModelGateway
{
    /** @var list<ModelReply|ModelFailure> */
    private array $script = [];

    /** @var list<ModelRequest> */
    public array $requests = [];

    public function say(string $text, int $inputTokens = 100, int $outputTokens = 20): static
    {
        $this->script[] = new ModelReply($text, [], $inputTokens, $outputTokens, 0, 'scripted', 'scripted');

        return $this;
    }

    /** @param  array<string, mixed>  $arguments */
    public function call(string $tool, array $arguments = [], string $id = '', string $text = ''): static
    {
        $id = $id !== '' ? $id : 'call-'.(count($this->script) + 1);
        $this->script[] = new ModelReply($text, [['id' => $id, 'name' => $tool, 'arguments' => $arguments]], 100, 20, 0, 'scripted', 'scripted', 'tool_calls');

        return $this;
    }

    /** @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls */
    public function callMany(array $calls): static
    {
        $this->script[] = new ModelReply('', $calls, 100, 20, 0, 'scripted', 'scripted', 'tool_calls');

        return $this;
    }

    public function fail(string $kind = 'overloaded', string $message = 'Scripted failure'): static
    {
        $this->script[] = new ModelFailure($kind, $message);

        return $this;
    }

    public function turn(ModelRequest $request): ModelReply
    {
        $this->requests[] = $request;
        $next = array_shift($this->script);

        if ($next === null) {
            if (app()->runningUnitTests()) {
                throw new RuntimeException('The script ran out: the engine asked for one more turn than the test expected.');
            }

            return new ModelReply('This is the local test model. No real model was called.', [], 0, 0, 0, 'scripted', 'scripted');
        }

        if ($next instanceof ModelFailure) {
            throw $next;
        }

        return $next;
    }
}
