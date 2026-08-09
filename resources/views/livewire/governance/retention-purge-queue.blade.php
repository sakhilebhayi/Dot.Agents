<div>
    @if (session('success'))
        <div class="mb-4 rounded-2xl bg-green-50 dark:bg-green-900/30 p-4 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($this->proposals->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center text-sm text-gray-500 dark:text-gray-400">
            No retention purges awaiting review.
        </div>
    @endif

    @foreach ($this->proposals as $proposal)
        <div class="mb-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ class_basename($proposal->model_class) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $proposal->retention_summary }}</p>
                </div>
                <span class="rounded-full bg-amber-100 dark:bg-amber-900/30 px-3 py-1 text-xs font-medium text-amber-800 dark:text-amber-400">
                    {{ $proposal->eligible_count }} row(s) eligible
                </span>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <input type="text" wire:model="reviewerNotes" placeholder="Reviewer notes (optional)"
                    class="flex-1 rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500" />
                <button wire:click="approve({{ $proposal->id }})" wire:confirm="Permanently delete {{ $proposal->eligible_count }} {{ class_basename($proposal->model_class) }} row(s)?"
                    class="rounded-xl bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Approve Purge
                </button>
                <button wire:click="reject({{ $proposal->id }})"
                    class="rounded-xl bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Reject
                </button>
            </div>
        </div>
    @endforeach

    {{ $this->proposals->links() }}
</div>
