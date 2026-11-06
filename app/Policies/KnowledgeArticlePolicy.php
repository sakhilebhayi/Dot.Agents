<?php

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KnowledgeArticlePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->organizations()->where('organizations.id', session('current_organization_id'))->exists();
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $article->organization_id === (int) session('current_organization_id');
    }

    public function create(User $user): bool
    {
        $orgId = (int) session('current_organization_id');

        return $user->canInOrganization('knowledge.contribute', $orgId);
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        $orgId = (int) session('current_organization_id');

        if ($article->organization_id !== $orgId) {
            return false;
        }

        // Authors can edit their own articles; admins can edit any
        if ($article->created_by === $user->id) {
            return true;
        }

        return $user->canInOrganization('knowledge.manage', $orgId);
    }

    public function delete(User $user, KnowledgeArticle $article): bool
    {
        $orgId = (int) session('current_organization_id');

        return $article->organization_id === $orgId
            && $user->canInOrganization('knowledge.manage', $orgId);
    }
}
