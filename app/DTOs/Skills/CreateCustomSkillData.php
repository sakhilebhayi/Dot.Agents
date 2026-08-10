<?php

namespace App\DTOs\Skills;

readonly class CreateCustomSkillData
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public ?string $description,
        public string $webhookUrl,
        public array $webhookHeaders = [],
        public int $webhookTimeoutSeconds = 15,
    ) {}
}
