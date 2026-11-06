<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

use App\Models\User;

readonly class SaveBusinessTemplateData
{
    public function __construct(public User $actor, public string $name, public ?string $summary = null) {}
}
