<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\DTOs\Organizations\DeleteKnowledgeArticleData;
use App\Events\KnowledgeArticleDeleted;
use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class DeleteKnowledgeArticleAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(Organization $organization, DeleteKnowledgeArticleData $data): void
    {
        Gate::authorize('update', $organization);

        $article = KnowledgeArticle::where('organization_id', $organization->id)
            ->findOrFail($data->articleId);

        $title = $article->title;
        $article->delete();

        $this->auditService->logUserAction(
            event: 'knowledge_article.deleted',
            description: "Deleted knowledge article '{$title}'",
            subject: $organization,
        );

        event(new KnowledgeArticleDeleted($organization->id, $title));
    }
}
