<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

readonly class StartRunData
{
    public function __construct(
        public string $input,
        public string $triggerType = 'manual',
        public ?string $triggerRef = null,
        public ?int $initiatedBy = null,
        public ?int $sessionId = null,
        public ?int $taskId = null,
        public ?int $workflowExecutionId = null,
        public ?int $parentRunId = null,
        public ?string $idempotencyKey = null,
        public bool $isTest = false,
        public ?int $versionId = null,
    ) {}
}
