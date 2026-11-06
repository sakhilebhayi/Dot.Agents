<?php

namespace App\Policies;

use App\Models\MemoryEmbedding;
use App\Models\User;

class MemoryEmbeddingPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, MemoryEmbedding $embedding): bool
    {
        return $user->organizations()->where('organizations.id', $embedding->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return false; // Created only by the Enterprise Memory Cortex system
    }

    public function update(User $user, MemoryEmbedding $embedding): bool
    {
        return false; // Embeddings are system-managed
    }

    public function delete(User $user, MemoryEmbedding $embedding): bool
    {
        return $user->canInOrganization('memory.govern', $embedding->organization_id);
    }
}
