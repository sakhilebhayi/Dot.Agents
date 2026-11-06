<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Models\User;

readonly class SaveUsageBudgetData
{
    public function __construct(public User $actor, public float $monthlyLimit, public int $warnAtPercent, public bool $stopAtLimit) {}
}
