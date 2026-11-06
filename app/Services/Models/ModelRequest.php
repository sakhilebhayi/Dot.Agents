<?php

namespace App\Services\Models;

final readonly class ModelRequest
{
    /**
     * @param  list<array<string, mixed>>  $messages  In order: {role: user, text}, {role: assistant, text, calls}, {role: tool, results}.
     * @param  list<array{name: string, description: string, schema: array<string, mixed>}>  $tools
     */
    public function __construct(
        public string $model,
        public string $system,
        public array $messages,
        public array $tools = [],
        public int $maxTokens = 4096,
    ) {}
}
