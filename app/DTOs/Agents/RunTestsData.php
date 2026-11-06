<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

use App\Models\User;

readonly class RunTestsData
{
    public function __construct(public User $actor, public int $versionId) {}
}
