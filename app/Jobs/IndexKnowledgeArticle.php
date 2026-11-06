<?php

namespace App\Jobs;

use App\Models\KnowledgeArticle;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexKnowledgeArticle implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 80;

    public function __construct(public readonly KnowledgeArticle $article)
    {
        $this->onQueue('agent-tasks');
    }

    public function handle(KnowledgeIndexer $indexer): void
    {
        $indexer->index($this->article);
    }
}
