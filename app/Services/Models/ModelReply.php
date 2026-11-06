<?php

namespace App\Services\Models;

final readonly class ModelReply
{
    /** @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls */
    public function __construct(
        public string $text,
        public array $calls,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedTokens,
        public string $provider,
        public string $model,
        public string $finish = 'stop',
    ) {}
}
