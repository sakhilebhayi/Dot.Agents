<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\AddKnowledgeSourceAction;
use App\DTOs\Organizations\AddKnowledgeSourceData;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class AddKnowledgeSource extends Component
{
    use WithFileUploads;

    public const READ_ONLY_ACTIONS = [];

    #[Locked]
    public int $baseId;

    public string $kind = 'upload';

    public string $title = '';

    public string $url = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public function save(AddKnowledgeSourceAction $action): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'file' => $this->kind === 'upload' ? 'required|file|max:20480' : 'nullable',
            'url' => $this->kind === 'website' ? 'required|url|max:2000' : 'nullable',
        ]);

        $action->execute(new AddKnowledgeSourceData(
            actor: auth()->user(),
            knowledgeBaseId: $this->baseId,
            title: $this->title,
            file: $this->kind === 'upload' ? $this->file : null,
            url: $this->kind === 'website' ? $this->url : null,
        ));

        $this->reset('title', 'url', 'file');
        $this->dispatch('knowledge-changed');
    }

    public function render()
    {
        return view('livewire.organizations.add-knowledge-source');
    }
}
