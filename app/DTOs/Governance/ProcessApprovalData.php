<?php

namespace App\DTOs\Governance;

readonly class ProcessApprovalData
{
    public function __construct(
        public int $approvalId,
        public string $decision,
        public ?string $reviewerNotes = null,
        /** @var array<string, mixed>|null What the reviewer changed the values to before approving. */
        public ?array $editedArguments = null,
    ) {
        if (! in_array($decision, ['approved', 'rejected', 'changes_requested', 'escalated'], true)) {
            throw new \InvalidArgumentException("Invalid decision: {$decision}");
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            approvalId: (int) $data['approval_id'],
            decision: $data['decision'],
            reviewerNotes: $data['reviewer_notes'] ?? null,
        );
    }
}
