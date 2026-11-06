<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

use App\Models\User;

readonly class SaveAgentTeamData
{
    /** @param  array<int, string>  $members  agent id to what that agent is responsible for */
    public function __construct(
        public User $actor,
        public int $organizationId,
        public string $name,
        public ?string $purpose,
        public int $supervisorId,
        public array $members,
    ) {}
}
