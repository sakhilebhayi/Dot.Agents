<?php

declare(strict_types=1);

namespace App\DTOs\Workflows;

use App\Models\User;

readonly class ControlExecutionData
{
    public function __construct(public User $actor) {}
}
