<div class="space-y-6">
    {{-- Flash --}}
    @if (session('status'))
        <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Workflows</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Orchestrate multi-agent pipelines for complex tasks.</p>
        </div>
        <button wire:click="openCreateModal"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-xl bg-[#3d2ea0] hover:bg-[#5b48c8] text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Workflow
        </button>
    </div>

    {{-- List --}}
    @if ($workflows->isEmpty())
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-16 text-center">
            <div class="w-14 h-14 rounded-2xl bg-brand-purple-pale dark:bg-brand-purple-deeper/20 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#3d2ea0] dark:text-brand-purple-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">No workflows yet</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-5 max-w-xs mx-auto">Build your first workflow to automate multi-agent processes.</p>
            <button wire:click="openCreateModal"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-xl bg-[#3d2ea0] hover:bg-[#5b48c8] text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Workflow
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($workflows as $wf)
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 flex flex-col gap-3 hover:border-brand-purple-light dark:hover:border-brand-purple transition-colors">
                    {{-- Status + Trigger --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $wf->name }}</h3>
                            @if ($wf->description)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ $wf->description }}</p>
                            @endif
                        </div>
                        @php
                            $statusClasses = match($wf->status) {
                                'active'   => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
                                'paused'   => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                                'archived' => 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400',
                                default    => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400', // draft
                            };
                        @endphp
                        <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClasses }}">
                            {{ ucfirst($wf->status) }}
                        </span>
                    </div>

                    {{-- Meta --}}
                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            {{ ucfirst($wf->trigger_type) }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                            </svg>
                            {{ $wf->nodes_count }} {{ Str::plural('node', $wf->nodes_count) }}
                        </span>
                        <span class="ml-auto">{{ $wf->updated_at->diffForHumans() }}</span>
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                        <a href="{{ route('workflows.builder', $wf) }}"
                            class="flex-1 py-1.5 text-xs font-medium text-center bg-[#3d2ea0] hover:bg-[#5b48c8] text-white rounded-lg transition-colors">
                            Open Builder
                        </a>
                        <button
                            wire:click="deleteWorkflow({{ $wf->id }})"
                            wire:confirm="Delete '{{ addslashes($wf->name) }}'? This cannot be undone."
                            class="p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            title="Delete workflow">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Create modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
             x-data x-trap.noscroll="true">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-md"
                 @click.outside="$wire.showCreateModal = false">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">New Workflow</h3>
                    <button wire:click="$set('showCreateModal', false)"
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form wire:submit="createWorkflow" class="p-6 space-y-4">
                    <div>
                        <label for="wf-name" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                        <input id="wf-name"
                            wire:model="newName"
                            type="text"
                            placeholder="e.g. Lead Qualification Pipeline"
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-purple-light focus:border-transparent"
                            autofocus>
                        @error('newName') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="wf-desc" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                        <textarea id="wf-desc"
                            wire:model="newDescription"
                            rows="2"
                            placeholder="Optional — what does this workflow do?"
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-purple-light focus:border-transparent resize-none"></textarea>
                        @error('newDescription') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="wf-trigger" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Trigger Type</label>
                        <select id="wf-trigger"
                            wire:model="newTrigger"
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-purple-light focus:border-transparent">
                            <option value="manual">Manual</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="event">Event</option>
                            <option value="webhook">Webhook</option>
                        </select>
                        @error('newTrigger') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button"
                            wire:click="$set('showCreateModal', false)"
                            class="flex-1 py-2.5 text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2.5 text-sm font-medium rounded-xl bg-[#3d2ea0] hover:bg-[#5b48c8] text-white transition-colors disabled:opacity-60">
                            <span wire:loading.remove>Create & Open Builder</span>
                            <span wire:loading>Creating…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
