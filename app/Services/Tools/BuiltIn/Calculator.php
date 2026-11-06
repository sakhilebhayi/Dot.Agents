<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolFailure;
use App\Services\Tools\ToolOutput;

/** Sums a model should not do in its head. A small parser, because a sum written by a model is never evaluated as code. */
class Calculator implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'calculator',
            label: 'Do a sum',
            category: 'Productivity',
            description: 'Works out an arithmetic expression using + - * / % and brackets. Use it for every figure you report.',
            accesses: 'Nothing. It only works with the numbers it is given.',
            schema: ['type' => 'object', 'properties' => ['expression' => ['type' => 'string', 'maxLength' => 200]], 'required' => ['expression']],
            timeoutSeconds: 1,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        preg_match_all('/\d+(?:\.\d+)?|[-+*\/%()]/', $arguments['expression'], $matches);

        if (implode('', $matches[0]) !== (string) preg_replace('/\s+/', '', $arguments['expression'])) {
            throw new ToolFailure('Only numbers and + - * / % ( ) can be used.');
        }

        $tokens = $matches[0];
        $value = $this->sum($tokens);

        if ($tokens !== []) {
            throw new ToolFailure('That expression could not be read.');
        }

        return new ToolOutput((string) round($value, 10));
    }

    /** @param  list<string>  $tokens */
    private function sum(array &$tokens): float
    {
        $value = $this->product($tokens);

        while (in_array($tokens[0] ?? null, ['+', '-'], true)) {
            $value = array_shift($tokens) === '+' ? $value + $this->product($tokens) : $value - $this->product($tokens);
        }

        return $value;
    }

    /** @param  list<string>  $tokens */
    private function product(array &$tokens): float
    {
        $value = $this->term($tokens);

        while (in_array($tokens[0] ?? null, ['*', '/', '%'], true)) {
            $operator = array_shift($tokens);
            $next = $this->term($tokens);

            if ($operator !== '*' && $next == 0) {
                throw new ToolFailure('Division by zero.');
            }

            $value = match ($operator) {
                '*' => $value * $next,
                '/' => $value / $next,
                default => fmod($value, $next),
            };
        }

        return $value;
    }

    /** @param  list<string>  $tokens */
    private function term(array &$tokens): float
    {
        $token = array_shift($tokens);

        if ($token === '-') {
            return -$this->term($tokens);
        }

        if ($token === '(') {
            $value = $this->sum($tokens);

            if (array_shift($tokens) !== ')') {
                throw new ToolFailure('A bracket is not closed.');
            }

            return $value;
        }

        if (! is_numeric($token)) {
            throw new ToolFailure('That expression could not be read.');
        }

        return (float) $token;
    }
}
