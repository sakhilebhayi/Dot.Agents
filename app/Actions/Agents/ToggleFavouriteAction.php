<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\ToggleFavouriteData;
use App\Models\User;

class ToggleFavouriteAction
{
    public function execute(ToggleFavouriteData $data): void
    {
        User::findOrFail($data->userId)->favouriteAgents()->toggle($data->deploymentId);
    }
}
