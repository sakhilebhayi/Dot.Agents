<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Departments</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Organize your AI agents across business departments.</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Department
        </button>
    </div>

    @if(session('dept_success'))
    <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('dept_success') }}
    </div>
    @endif

    {{-- Create / Edit Form --}}
    @if($showForm)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-brand-purple-pale dark:border-brand-purple-dark p-6 space-y-4">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $editingId ? 'Edit Department' : 'New Department' }}</h3>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name <span class="text-red-500">*</span></label>
                    <input wire:model="formName" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="e.g. Marketing">
                    @error('formName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Type</label>
                    <select wire:model="formType" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                        <option value="">Select type</option>
                        <option value="business">Business</option>
                        <option value="technical">Technical</option>
                        <option value="operations">Operations</option>
                        <option value="support">Support</option>
                        <option value="finance">Finance</option>
                        <option value="hr">HR</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Department Head</label>
                    <input wire:model="formHeadName" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="Jane Smith">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                    <input wire:model="formDescription" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="Brief description…">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple disabled:opacity-75 text-white text-sm font-medium rounded-xl transition-colors">
                    {{ $editingId ? 'Update' : 'Create' }} Department
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Delete Confirmation --}}
    @if($deletingId)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-red-200 dark:border-red-800 p-5 flex items-center justify-between gap-4">
        <p class="text-sm text-gray-700 dark:text-gray-300">Are you sure you want to delete this department?</p>
        <div class="flex gap-2">
            <button wire:click="deleteDepartment" wire:loading.attr="disabled" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-75 text-white text-sm font-medium rounded-xl">Delete</button>
            <button wire:click="$set('deletingId', null)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Departments Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($this->departments as $dept)
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-purple-mid to-brand-purple-dark flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    {{ strtoupper(substr($dept->name, 0, 2)) }}
                </div>
                <div class="flex gap-1">
                    <button wire:click="openEdit({{ $dept->id }})" class="p-1.5 text-gray-400 hover:text-brand-purple-mid rounded-lg transition-colors" title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button wire:click="confirmDelete({{ $dept->id }})" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-1">{{ $dept->name }}</h4>
            @if($dept->description)
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 line-clamp-2">{{ $dept->description }}</p>
            @endif
            <div class="flex items-center gap-3 text-xs text-gray-500 mt-auto">
                @if($dept->head_name)
                    <span>Lead: {{ $dept->head_name }}</span>
                    <span>&bull;</span>
                @endif
                <span>{{ $dept->deployments_count }} agent{{ $dept->deployments_count !== 1 ? 's' : '' }}</span>
                @if($dept->type)
                    <span class="ml-auto px-2 py-0.5 bg-brand-purple-pale dark:bg-brand-purple-deeper/30 text-brand-purple dark:text-brand-purple-light rounded-full capitalize">{{ $dept->type }}</span>
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
            <div class="w-14 h-14 bg-brand-purple-pale dark:bg-brand-purple-deeper/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-brand-purple-mid dark:text-brand-purple-light" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No departments yet.</p>
            <button wire:click="openCreate" class="text-sm text-brand-purple-mid hover:text-brand-purple font-medium">Create your first department →</button>
        </div>
        @endforelse
    </div>
</div>
