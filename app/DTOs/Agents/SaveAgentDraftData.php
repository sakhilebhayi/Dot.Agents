<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

readonly class SaveAgentDraftData
{
    /** @param  array<string, mixed>  $config */
    public function __construct(
        public int $savedBy,
        public array $config,
        public ?string $name = null,
        public ?string $role = null,
        public ?string $responsibility = null,
        public ?int $departmentId = null,
        public ?int $ownerId = null,
        public ?int $autonomyLevel = null,
    ) {}
}
