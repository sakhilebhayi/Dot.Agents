<?php

declare(strict_types=1);

namespace App\DTOs\Workflows;

use App\Models\User;

readonly class StartWorkflowData
{
    public function __construct(
        public string $request,
        public ?User $actor = null,
        public bool $isTest = false,
        public ?string $idempotencyKey = null,
    ) {}
}
