<?php

namespace App\Actions\Organizations;

use App\Events\KnowledgeArticleSaved;
use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveKnowledgeArticleAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(Organization $organization, array $data, ?int $existingId = null): KnowledgeArticle
    {
        Gate::authorize('update', $organization);

        $payload = [
            'knowledge_base_id' => $data['knowledge_base_id'],
            'organization_id' => $organization->id,
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.time(),
            'content' => $data['content'],
            'summary' => $data['summary'] ?? null,
            'category' => $data['category'] ?? null,
            'is_published' => true,
        ];

        if ($existingId) {
            $article = KnowledgeArticle::findOrFail($existingId);
            abort_if($article->organization_id !== $organization->id, 403);
            $article->update($payload);
            $article = $article->fresh();
        } else {
            $article = KnowledgeArticle::create($payload);
        }

        $this->auditService->logUserAction(
            event: $existingId ? 'knowledge_article.updated' : 'knowledge_article.created',
            description: ($existingId ? 'Updated' : 'Created')." knowledge article '{$article->title}'",
            subject: $article,
        );

        event(new KnowledgeArticleSaved($article, ! $existingId));

        return $article;
    }
}
