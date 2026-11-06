<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

use App\Models\User;

readonly class SaveTestCaseData
{
    /** @param  array<string, mixed>  $checks */
    public function __construct(public User $actor, public string $name, public string $input, public string $triggerType, public array $checks) {}
}
