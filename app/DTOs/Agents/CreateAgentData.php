<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

readonly class CreateAgentData
{
    /** @param  array<string, mixed>  $config */
    public function __construct(
        public int $organizationId,
        public int $createdBy,
        public string $name,
        public string $role,
        public ?string $responsibility = null,
        public ?int $departmentId = null,
        public array $config = [],
        public ?int $templateId = null,
    ) {}
}
