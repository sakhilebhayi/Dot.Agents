<?php

declare(strict_types=1);

namespace App\DTOs\Organizations;

use App\Models\User;

readonly class GovernMemoryData
{
    public function __construct(public User $actor, public ?string $correction = null) {}
}
