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
        public int $taint = 0,
        /** @var array{name: string, arguments: array<string, mixed>}|null A tool step: one call, through every gate, and no model. */
        public ?array $directCall = null,
        /** Created but not started, so the caller can record it first. See StartRunAction::begin(). */
        public bool $deferred = false,
        /** For a test: this tool fails, to see how the agent copes. */
        public ?string $failTool = null,
    ) {}
}
