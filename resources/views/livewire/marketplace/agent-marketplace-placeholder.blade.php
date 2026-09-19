<div class="min-h-screen p-6">
    {{-- Loading indicator --}}
    <div wire:loading class="fixed top-0 inset-x-0 h-0.5 bg-brand-purple-mid z-50 animate-pulse" role="status" aria-label="Loading"></div>

    {{-- ── Header skeleton ──────────────────────────────────────────── --}}
    <div class="mb-8 animate-pulse">
        <div class="h-8 w-64 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
        <div class="mt-2 h-4 w-96 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
    </div>

    {{-- ── Filter bar skeleton ──────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap gap-3 animate-pulse">
        <div class="h-10 w-56 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-10 w-36 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-10 w-36 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-10 w-36 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
    </div>

    {{-- ── Agent card grid skeleton ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach (range(1, 8) as $_)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm animate-pulse dark:border-gray-700 dark:bg-gray-800">
                {{-- Icon + title --}}
                <div class="mb-4 flex items-center gap-3">
                    <div class="h-12 w-12 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex-1">
                        <div class="h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
                        <div class="mt-1 h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
                {{-- Description --}}
                <div class="space-y-2">
                    <div class="h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-5/6 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-4/6 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
                {{-- Tags --}}
                <div class="mt-4 flex gap-2">
                    <div class="h-5 w-16 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-5 w-20 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                </div>
                {{-- Actions --}}
                <div class="mt-5 flex justify-between">
                    <div class="h-8 w-20 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-8 w-24 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
