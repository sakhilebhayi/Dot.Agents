<?php

namespace App\Listeners;

use App\Events\ApiTokenRevoked;
use App\Events\ConnectionSettingsSaved;
use App\Events\DepartmentDeleted;
use App\Events\DepartmentSaved;
use App\Events\KnowledgeArticleDeleted;
use App\Events\KnowledgeArticleSaved;
use App\Events\KnowledgeBaseCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogOrganizationLifecycleEvents implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public function handleDepartmentSaved(DepartmentSaved $event): void
    {
        Log::info('organization.department_saved', [
            'organization_id' => $event->department->organization_id,
            'department_id' => $event->department->id,
            'department_name' => $event->department->name,
            'is_new' => $event->isNew,
        ]);
    }

    public function handleDepartmentDeleted(DepartmentDeleted $event): void
    {
        Log::info('organization.department_deleted', [
            'organization_id' => $event->organizationId,
            'department_name' => $event->departmentName,
        ]);
    }

    public function handleKnowledgeBaseCreated(KnowledgeBaseCreated $event): void
    {
        Log::info('organization.knowledge_base_created', [
            'organization_id' => $event->knowledgeBase->organization_id,
            'knowledge_base_id' => $event->knowledgeBase->id,
            'knowledge_base_name' => $event->knowledgeBase->name,
        ]);
    }

    public function handleKnowledgeArticleSaved(KnowledgeArticleSaved $event): void
    {
        Log::info('organization.knowledge_article_saved', [
            'organization_id' => $event->article->organization_id,
            'article_id' => $event->article->id,
            'article_title' => $event->article->title,
            'is_new' => $event->isNew,
        ]);
    }

    public function handleKnowledgeArticleDeleted(KnowledgeArticleDeleted $event): void
    {
        Log::info('organization.knowledge_article_deleted', [
            'organization_id' => $event->organizationId,
            'article_title' => $event->articleTitle,
        ]);
    }

    public function handleConnectionSettingsSaved(ConnectionSettingsSaved $event): void
    {
        Log::info('organization.connection_settings_saved', [
            'organization_id' => $event->settings->organization_id,
            'platform' => $event->settings->platform,
            'social_account_id' => $event->settings->social_account_id,
        ]);
    }

    public function handleApiTokenRevoked(ApiTokenRevoked $event): void
    {
        Log::info('organization.api_token_revoked', [
            'user_id' => $event->userId,
            'token_name' => $event->tokenName,
        ]);
    }
}
