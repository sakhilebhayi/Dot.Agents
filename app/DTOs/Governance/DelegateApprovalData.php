<?php

declare(strict_types=1);

namespace App\DTOs\Governance;

use App\Models\User;

readonly class DelegateApprovalData
{
    public function __construct(public User $actor, public User $delegate, public ?string $note = null) {}
}
