<?php

declare(strict_types=1);

namespace App\DTOs\Agents;

readonly class ToggleFavouriteData
{
    public function __construct(public int $userId, public int $deploymentId) {}
}
