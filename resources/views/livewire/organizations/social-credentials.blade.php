<div class="space-y-6">

    <div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Social Media Connections</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Connect your social media accounts so Dot.Agents can manage customer engagement,
            lead generation, social conversations, and customer support.
        </p>
    </div>

    {{-- Primary CTA --}}
    <div class="bg-gradient-to-br from-brand-purple-pale to-yellow-50 dark:from-brand-purple-deeper/20 dark:to-yellow-900/10 rounded-2xl border border-brand-purple-pale dark:border-brand-purple-dark p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <p class="font-semibold text-gray-900 dark:text-white">Manage your social connections</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Connect Facebook, Instagram, LinkedIn, X, and TikTok. Configure AI goals and automation levels per platform.</p>
        </div>
        <div class="flex gap-3 flex-shrink-0">
            <a href="{{ route('social.accounts') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-800 transition">
                View Connections
            </a>
            <a href="{{ route('social.connect') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-bold rounded-xl transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Connect Account
            </a>
        </div>
    </div>

    {{-- Advanced credentials (collapsed) --}}
    <details class="group bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700">
        <summary class="flex items-center justify-between px-6 py-4 cursor-pointer select-none list-none">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Custom App Configuration</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Enterprise: use your own OAuth apps on each platform instead of Dot.Agents defaults.</p>
            </div>
            <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180 flex-shrink-0 ml-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </summary>

        <div class="border-t border-gray-100 dark:border-gray-800 px-6 pb-6 pt-4 space-y-4">

            <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-xs text-blue-800 dark:text-blue-300">
                Most users should connect accounts via the wizard above. Only configure custom apps if your organization requires its own registered developer application on a platform.
            </div>

            @foreach(\App\Livewire\Organizations\SocialCredentials::$platforms as $platform => $meta)
                @php $cred = $this->credentials->get($platform); @endphp

                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="{{ $meta['color'] }} w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                            {{ $meta['icon'] }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $meta['label'] }}</p>
                            @if($cred)
                                <p class="text-xs text-green-600 dark:text-green-400">Custom app configured &middot; updated {{ $cred->updated_at->diffForHumans() }}</p>
                            @else
                                <p class="text-xs text-gray-400">Using platform defaults</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($cred && $editing !== $platform)
                                <button wire:click="removePlatform('{{ $platform }}')"
                                        wire:confirm="Remove custom credentials for {{ $meta['label'] }}?"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium">Remove</button>
                            @endif
                            @if($editing === $platform)
                                <button wire:click="cancelEdit" class="px-3 py-1 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancel</button>
                            @else
                                <button wire:click="openEdit('{{ $platform }}')" class="px-3 py-1 text-xs font-medium rounded-lg bg-brand-purple-mid hover:bg-brand-purple text-white transition">
                                    {{ $cred ? 'Update' : 'Configure' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($editing === $platform)
                    <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-4">
                        <form wire:submit="savePlatform" class="space-y-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Set the redirect URI in your <a href="{{ $meta['docs'] }}" target="_blank" rel="noopener noreferrer" class="text-brand-purple-mid hover:underline">{{ $meta['label'] }} Developer Console</a> to:
                                <code class="font-mono bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs ml-1">{{ route('social.auth.callback', ['platform' => $platform]) }}</code>
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">App / Client ID *</label>
                                    <input wire:model="clientId" type="text" autocomplete="off" spellcheck="false"
                                           class="w-full text-xs font-mono rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid"
                                           placeholder="Client ID">
                                    @error('clientId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">App / Client Secret *</label>
                                    <input wire:model="clientSecret" type="password" autocomplete="new-password"
                                           class="w-full text-xs font-mono rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid"
                                           placeholder="Client Secret">
                                    @error('clientSecret') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <button type="submit" wire:loading.attr="disabled"
                                    class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-xs font-semibold rounded-lg transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="savePlatform">Save</span>
                                <span wire:loading wire:target="savePlatform">Saving...</span>
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
    </details>

</div>
