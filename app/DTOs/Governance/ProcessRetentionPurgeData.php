<?php

namespace App\DTOs\Governance;

readonly class ProcessRetentionPurgeData
{
    public function __construct(
        public int $proposalId,
        public string $decision,
        public ?string $reviewerNotes = null,
    ) {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException("Invalid decision: {$decision}");
        }
    }
}
