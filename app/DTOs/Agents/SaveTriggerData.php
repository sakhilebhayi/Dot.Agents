<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

use App\Models\User;

readonly class SaveTriggerData
{
    /** @param  array<string, mixed>  $settings */
    public function __construct(
        public User $actor,
        public int $organizationId,
        public string $targetType,
        public int $targetId,
        public string $kind,
        public string $name,
        public ?string $instruction,
        public array $settings,
    ) {}
}
