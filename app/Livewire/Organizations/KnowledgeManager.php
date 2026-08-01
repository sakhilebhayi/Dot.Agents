<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\DeleteKnowledgeArticleAction;
use App\Actions\Organizations\SaveKnowledgeArticleAction;
use App\Actions\Organizations\SaveKnowledgeBaseAction;
use App\DTOs\Organizations\DeleteKnowledgeArticleData;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeBase;
use App\Models\Organization;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class KnowledgeManager extends Component
{
    public ?int $activeBaseId = null;

    public bool $showBaseForm = false;

    public bool $showArticleForm = false;

    public ?int $editingArticleId = null;

    // Knowledge base form
    #[Validate('required|string|max:255')]
    public string $baseName = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $baseDescription = null;

    #[Validate('required|in:internal,public,restricted')]
    public string $baseAccessLevel = 'internal';

    // Article form
    #[Validate('required|string|max:255')]
    public string $articleTitle = '';

    #[Validate('required|string')]
    public string $articleContent = '';

    #[Validate('nullable|string|max:500')]
    public ?string $articleSummary = null;

    #[Validate('nullable|string|max:100')]
    public ?string $articleCategory = null;

    #[Computed]
    public function organization(): Organization
    {
        $orgId = session('current_organization_id');
        abort_if(! $orgId, 403);

        return Organization::findOrFail($orgId);
    }

    #[Computed]
    public function knowledgeBases()
    {
        return KnowledgeBase::where('organization_id', $this->organization->id)
            ->withCount('articles')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function activeBase(): ?KnowledgeBase
    {
        if (! $this->activeBaseId) {
            return null;
        }

        // SECURITY: activeBaseId can be set via selectBase(), a method argument
        // that is not checksum-protected, so it must be scoped to the current org.
        return KnowledgeBase::where('organization_id', $this->organization->id)
            ->find($this->activeBaseId);
    }

    #[Computed]
    public function articles()
    {
        // Route through the org-scoped activeBase() computed rather than the raw
        // activeBaseId, so a tampered/foreign base id yields no results.
        if (! $this->activeBase) {
            return collect();
        }

        return KnowledgeArticle::where('knowledge_base_id', $this->activeBase->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function selectBase(int $id): void
    {
        $this->activeBaseId = $id;
        $this->showArticleForm = false;
        unset($this->articles, $this->activeBase);
    }

    public function saveBase(SaveKnowledgeBaseAction $action): void
    {
        $this->validateOnly('baseName');

        $action->execute($this->organization, [
            'name' => $this->baseName,
            'description' => $this->baseDescription,
            'access_level' => $this->baseAccessLevel,
        ]);

        $this->baseName = '';
        $this->baseDescription = null;
        $this->baseAccessLevel = 'internal';
        $this->showBaseForm = false;
        unset($this->knowledgeBases);
        session()->flash('kb_success', 'Knowledge base created.');
    }

    public function saveArticle(SaveKnowledgeArticleAction $action): void
    {
        $this->validate([
            'articleTitle' => 'required|string|max:255',
            'articleContent' => 'required|string',
        ]);

        $action->execute(
            $this->organization,
            [
                'knowledge_base_id' => $this->activeBaseId,
                'title' => $this->articleTitle,
                'content' => $this->articleContent,
                'summary' => $this->articleSummary,
                'category' => $this->articleCategory,
            ],
            $this->editingArticleId
        );

        session()->flash('kb_success', $this->editingArticleId ? 'Article updated.' : 'Article created.');
        $this->resetArticleForm();
        unset($this->articles);
    }

    public function editArticle(int $id): void
    {
        // SECURITY: $id is an unchecksummed method argument — scope the lookup to
        // the current org to prevent cross-org reads of article content.
        $a = KnowledgeArticle::where('organization_id', $this->organization->id)
            ->findOrFail($id);
        $this->editingArticleId = $id;
        $this->articleTitle = $a->title;
        $this->articleContent = $a->content;
        $this->articleSummary = $a->summary;
        $this->articleCategory = $a->category;
        $this->showArticleForm = true;
    }

    public function deleteArticle(int $id): void
    {
        app(DeleteKnowledgeArticleAction::class)->execute($this->organization, DeleteKnowledgeArticleData::fromId($id));
        unset($this->articles);
        session()->flash('kb_success', 'Article deleted.');
    }

    private function resetArticleForm(): void
    {
        $this->articleTitle = '';
        $this->articleContent = '';
        $this->articleSummary = null;
        $this->articleCategory = null;
        $this->editingArticleId = null;
        $this->showArticleForm = false;
    }

    public function render()
    {
        return view('livewire.organizations.knowledge-manager');
    }
}
