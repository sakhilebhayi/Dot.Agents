<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

readonly class PublishAgentVersionData
{
    public function __construct(
        public int $publishedBy,
        public ?string $note = null,
    ) {}
}
