<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Custom Skills</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Wire your own API or automation into an agent's skill pipeline — no code shipped to this platform.</p>
        </div>
        @if(! $showForm)
        <button wire:click="$set('showForm', true)"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Custom Skill
        </button>
        @endif
    </div>

    {{-- New Custom Skill Form --}}
    @if($showForm)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-brand-purple-pale dark:border-brand-purple-dark p-6 space-y-4">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">New Custom Skill</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input wire:model="name" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="e.g. Inventory Lookup">
                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Timeout (seconds)</label>
                <input wire:model="webhookTimeoutSeconds" type="number" min="1" max="120" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                @error('webhookTimeoutSeconds') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Webhook URL <span class="text-red-500">*</span></label>
                <input wire:model="webhookUrl" type="url" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="https://your-api.example.com/skills/inventory-lookup">
                @error('webhookUrl') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-400">Called with <code>{ skill_key, input, context }</code> as JSON; expects <code>{ status, output, confidence, findings, recommendations }</code> back.</p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                <input wire:model="description" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="What does this skill do?">
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button wire:click="$set('showForm', false)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl">Cancel</button>
            <button wire:click="create" wire:loading.attr="disabled" class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple disabled:opacity-75 text-white text-sm font-medium rounded-xl transition-colors">Create</button>
        </div>
    </div>
    @endif

    {{-- List --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Your Custom Skills</p>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($this->customSkills as $skill)
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $skill->name }}</p>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $skill->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                                {{ $skill->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $skill->webhook_url }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="toggleActive({{ $skill->id }})" class="text-xs font-medium text-brand-purple-mid hover:text-brand-purple-dark">
                            {{ $skill->is_active ? 'Disable' : 'Enable' }}
                        </button>
                        <button wire:click="delete({{ $skill->id }})" wire:confirm="Delete this custom skill? Deployments using it will stop being able to invoke it." class="text-xs font-medium text-red-600 hover:text-red-800">
                            Delete
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400">
                    No custom skills yet. Create one to wire your own endpoint into an agent's skill pipeline.
                </div>
            @endforelse
        </div>
    </div>
</div>
