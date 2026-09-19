<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Knowledge Base</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Articles and documents your AI agents can reference.</p>
        </div>
        @if(! $showBaseForm)
        <button wire:click="$set('showBaseForm', true)"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Knowledge Base
        </button>
        @endif
    </div>

    @if(session('kb_success'))
    <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('kb_success') }}
    </div>
    @endif

    {{-- New Knowledge Base Form --}}
    @if($showBaseForm)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-brand-purple-pale dark:border-brand-purple-dark p-6 space-y-4">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">New Knowledge Base</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input wire:model="baseName" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="e.g. Product Documentation">
                @error('baseName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Access Level</label>
                <select wire:model="baseAccessLevel" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                    <option value="internal">Internal (agents only)</option>
                    <option value="restricted">Restricted (specific agents)</option>
                    <option value="public">Public</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                <input wire:model="baseDescription" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="What is this knowledge base for?">
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button wire:click="$set('showBaseForm', false)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl">Cancel</button>
            <button wire:click="saveBase" wire:loading.attr="disabled" class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple disabled:opacity-75 text-white text-sm font-medium rounded-xl transition-colors">Create</button>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Sidebar: KB list --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Knowledge Bases</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($this->knowledgeBases as $kb)
                    <button wire:click="selectBase({{ $kb->id }})"
                        class="w-full text-left px-4 py-3 hover:bg-brand-purple-pale dark:hover:bg-brand-purple-deeper/20 transition-colors {{ $activeBaseId === $kb->id ? 'bg-brand-purple-pale dark:bg-brand-purple-deeper/20 border-l-2 border-brand-purple-mid' : '' }}">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $kb->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $kb->articles_count }} article{{ $kb->articles_count !== 1 ? 's' : '' }}</p>
                    </button>
                    @empty
                    <div class="px-4 py-8 text-center text-xs text-gray-500">No knowledge bases yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Main: Articles --}}
        <div class="lg:col-span-3">
            @if($activeBaseId)
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $this->activeBase?->name }}</h3>
                    <button wire:click="$set('showArticleForm', true)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-purple-mid hover:bg-brand-purple text-white text-xs font-medium rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Article
                    </button>
                </div>

                {{-- Article Form --}}
                @if($showArticleForm)
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-brand-purple-pale dark:border-brand-purple-dark p-5 space-y-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $editingArticleId ? 'Edit Article' : 'New Article' }}</h4>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Title <span class="text-red-500">*</span></label>
                        <input wire:model="articleTitle" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                        @error('articleTitle') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Content <span class="text-red-500">*</span></label>
                        <textarea wire:model="articleContent" rows="6" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid font-mono" placeholder="Write your knowledge article here…"></textarea>
                        @error('articleContent') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Summary</label>
                            <input wire:model="articleSummary" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                            <input wire:model="articleCategory" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="e.g. Policy, FAQ">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button wire:click="$set('showArticleForm', false)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl">Cancel</button>
                        <button wire:click="saveArticle" wire:loading.attr="disabled" class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple disabled:opacity-75 text-white text-sm font-medium rounded-xl transition-colors">Save Article</button>
                    </div>
                </div>
                @endif

                {{-- Articles List --}}
                <div class="space-y-3">
                    @forelse($this->articles as $article)
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $article->title }}</p>
                                @if($article->summary)
                                    <p class="text-xs text-gray-500 mt-1">{{ $article->summary }}</p>
                                @endif
                                <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                                    @if($article->category)
                                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-full">{{ $article->category }}</span>
                                    @endif
                                    <span>{{ $article->created_at->format('d M Y') }}</span>
                                    <span>{{ $article->view_count }} views</span>
                                </div>
                            </div>
                            <div class="flex gap-1 flex-shrink-0">
                                <button wire:click="editArticle({{ $article->id }})" class="p-1.5 text-gray-400 hover:text-brand-purple-mid rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="deleteArticle({{ $article->id }})" wire:confirm="Delete this article?" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">No articles yet.</p>
                        <button wire:click="$set('showArticleForm', true)" class="text-sm text-brand-purple-mid hover:text-brand-purple font-medium">Add the first article →</button>
                    </div>
                    @endforelse
                </div>
            </div>
            @else
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                <div class="w-14 h-14 bg-brand-purple-pale dark:bg-brand-purple-deeper/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-brand-purple-mid dark:text-brand-purple-light" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Select a knowledge base or create a new one to get started.</p>
            </div>
            @endif
        </div>
    </div>
</div>
