<x-sig.page title="Knowledge" sub="What agents may read. An agent sees a source only when its setup names it.">
    <x-slot:actions>
        <button type="button" class="btn" wire:click="$toggle('showBaseForm')">New collection</button>
    </x-slot:actions>

    @if(session('kb_success'))
        <div class="notice clear" role="status">{{ session('kb_success') }}</div>
    @endif

    @if($showBaseForm)
        <form class="card" wire:submit="saveBase" style="margin-bottom: var(--gap)">
            <div class="card-head"><h2>New collection</h2></div>
            <div class="card-body rule-form">
                <label class="field">
                    <span>Name</span>
                    <input class="input" wire:model="baseName">
                    @error('baseName')<p class="error">{{ $message }}</p>@enderror
                </label>
                <label class="field">
                    <span>What it holds</span>
                    <input class="input" wire:model="baseDescription">
                </label>
                <button type="submit" class="btn primary">Create</button>
            </div>
        </form>
    @endif

    <div class="desk">
        <section class="card" aria-label="Collections">
            <div class="card-head"><h2>Collections</h2></div>
            @forelse($this->knowledgeBases as $base)
                <button type="button" class="row desk-row" wire:key="base-{{ $base->id }}" wire:click="selectBase({{ $base->id }})" @if($activeBaseId === $base->id) aria-current="true" @endif>
                    <span class="row-main">
                        <b>{{ $base->name }}</b>
                        <span>{{ $base->description ?: 'No description' }}</span>
                    </span>
                    <span class="row-end num">{{ $base->articles_count }}</span>
                </button>
            @empty
                <div class="empty"><b>No collections yet.</b> A collection groups the documents an agent may read.</div>
            @endforelse
        </section>

        <div class="grid">
            @if($this->activeBase)
                <section class="card" aria-label="Sources">
                    <div class="card-head">
                        <h2>{{ $this->activeBase->name }}</h2>
                        <button type="button" class="btn small" wire:click="$toggle('showArticleForm')">Write a note</button>
                    </div>

                    @forelse($this->articles as $article)
                        <div class="row" wire:key="article-{{ $article->id }}">
                            <div class="row-main">
                                <b>{{ $article->title }}</b>
                                <span>{{ $article->index_status === 'failed' ? $article->index_error : ($article->file_name ?? $article->source_url ?? 'Written here') }}</span>
                            </div>
                            <span class="chip {{ ['indexed' => 'clear', 'failed' => 'stop'][$article->index_status] ?? 'running' }}">{{ ['indexed' => 'Ready', 'failed' => 'Could not read'][$article->index_status] ?? 'Reading' }}</span>
                            @if($article->source_type === 'website')
                                <button type="button" class="btn quiet small" wire:click="reindex({{ $article->id }})">Read again</button>
                            @elseif($article->source_type === 'manual' || $article->source_type === null)
                                <button type="button" class="btn quiet small" wire:click="editArticle({{ $article->id }})">Edit</button>
                            @endif
                            <button type="button" class="btn quiet small" wire:click="deleteArticle({{ $article->id }})" wire:confirm="Remove {{ $article->title }}? Agents will stop finding it.">Remove</button>
                        </div>
                    @empty
                        <div class="empty"><b>Nothing here yet.</b> Add a file or a website below.</div>
                    @endforelse
                </section>

                @if($showArticleForm)
                    <form class="card" wire:submit="saveArticle">
                        <div class="card-head"><h2>{{ $editingArticleId ? 'Edit note' : 'Write a note' }}</h2></div>
                        <div class="card-body">
                            <label class="field">
                                <span>Title</span>
                                <input class="input" wire:model="articleTitle">
                                @error('articleTitle')<p class="error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span>Text</span>
                                <textarea class="textarea" rows="8" wire:model="articleContent"></textarea>
                                @error('articleContent')<p class="error">{{ $message }}</p>@enderror
                            </label>
                            <button type="submit" class="btn primary">Save</button>
                        </div>
                    </form>
                @endif

                <livewire:organizations.add-knowledge-source :base-id="$this->activeBase->id" :key="'source-'.$this->activeBase->id" />
            @else
                <div class="card desk-idle"><div class="empty">Choose a collection to see its sources.</div></div>
            @endif
        </div>
    </div>
</x-sig.page>
