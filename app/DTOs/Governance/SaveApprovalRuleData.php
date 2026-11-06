<?php

declare(strict_types=1);

namespace App\DTOs\Governance;

use App\Models\User;

readonly class SaveApprovalRuleData
{
    public function __construct(
        public User $actor,
        public int $organizationId,
        public string $name,
        public ?string $toolKey,
        public ?string $category,
        public ?string $approverRole,
        public ?int $expiresHours,
    ) {}
}
