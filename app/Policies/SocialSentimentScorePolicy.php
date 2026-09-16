<?php

namespace App\Policies;

use App\Models\SocialSentimentScore;
use App\Models\User;

class SocialSentimentScorePolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, SocialSentimentScore $score): bool
    {
        return $user->organizations()->where('organizations.id', $score->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return false; // Created only by Sentiment Analysis agent
    }

    public function update(User $user, SocialSentimentScore $score): bool
    {
        return false; // System-managed
    }

    public function markHandled(User $user, SocialSentimentScore $score): bool
    {
        return $user->organizations()
            ->where('organizations.id', $score->organization_id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function delete(User $user, SocialSentimentScore $score): bool
    {
        return $user->hasRole('super-admin');
    }
}
