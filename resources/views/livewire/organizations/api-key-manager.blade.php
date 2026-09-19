<div class="max-w-3xl space-y-6">

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">API Keys</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Manage API keys for programmatic access to your organization's Dot.Agents resources.
            Keys are scoped to your account and inherit your permissions.
        </p>
    </div>

    {{-- New token banner (shown once, immediately after creation) --}}
    @if ($plainTextToken)
    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-300 dark:border-yellow-700 rounded-2xl space-y-3">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 110 14A7 7 0 0112 5z"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">Copy your token now — it won't be shown again.</p>
                <code class="mt-2 block w-full break-all text-xs bg-white dark:bg-gray-900 border border-yellow-200 dark:border-yellow-700 rounded-lg px-3 py-2 text-gray-900 dark:text-gray-100 font-mono select-all">{{ $plainTextToken }}</code>
            </div>
        </div>
        <div class="flex justify-end">
            <button wire:click="dismissToken" type="button"
                class="text-xs text-yellow-700 dark:text-yellow-400 hover:underline">
                I've copied my token
            </button>
        </div>
    </div>
    @endif

    {{-- Create new key --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Create a new API key</h3>

        <form wire:submit="createToken" class="flex gap-3 items-start">
            <div class="flex-1">
                <input wire:model="newKeyName"
                    type="text"
                    placeholder="e.g. CI/CD Pipeline, n8n Integration"
                    class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid"
                    aria-label="API key name">
                @error('newKeyName')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="px-4 py-2 text-sm font-semibold rounded-xl bg-brand-purple-mid text-white hover:bg-brand-purple transition whitespace-nowrap"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-60 cursor-wait">
                <span wire:loading.remove wire:target="createToken">Generate Key</span>
                <span wire:loading wire:target="createToken">Generating…</span>
            </button>
        </form>
    </div>

    {{-- Existing keys --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
        <div class="px-5 py-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Active keys</h3>
        </div>

        @forelse ($this->tokens as $token)
        <div class="px-5 py-4 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $token['name'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                    Created {{ $token['created_at'] }}
                    @if ($token['last_used_at'])
                        · Last used {{ $token['last_used_at'] }}
                    @else
                        · Never used
                    @endif
                </p>
            </div>

            <button wire:click="revokeToken({{ $token['id'] }})"
                wire:confirm="Revoke this API key? Any integrations using it will stop working immediately."
                type="button"
                class="shrink-0 text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition"
                aria-label="Revoke key {{ $token['name'] }}">
                Revoke
            </button>
        </div>
        @empty
        <div class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
            No API keys yet. Generate one above to get started.
        </div>
        @endforelse
    </div>

</div>
