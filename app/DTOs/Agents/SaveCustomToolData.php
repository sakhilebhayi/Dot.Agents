<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

use App\Models\User;

readonly class SaveCustomToolData
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $inputSchema
     */
    public function __construct(
        public User $actor,
        public int $organizationId,
        public string $key,
        public string $label,
        public string $description,
        public string $method,
        public string $url,
        public array $headers,
        public array $inputSchema,
        public bool $readsSensitive = false,
    ) {}
}
