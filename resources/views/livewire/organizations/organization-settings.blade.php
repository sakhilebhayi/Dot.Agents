<div class="max-w-3xl space-y-6">
    {{-- Header --}}
    <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Organization Settings</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your organization profile and preferences.</p>
    </div>

    {{-- Tab nav --}}
    <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
        <button wire:click="$set('tab','general')"
                class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'general' ? 'border-brand-purple-mid text-brand-purple-mid dark:text-brand-purple-light' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
            General
        </button>
        <button wire:click="$set('tab','social')"
                class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'social' ? 'border-brand-purple-mid text-brand-purple-mid dark:text-brand-purple-light' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
            Social Platform Credentials
        </button>
    </div>

    @if($saved)
    <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Settings saved successfully.
    </div>
    @endif

    @if($tab === 'general')

    <form wire:submit="save" class="space-y-6">
        {{-- Identity --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Identity</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Organization Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid" placeholder="Acme Corp">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Domain</label>
                    <input wire:model="domain" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid" placeholder="acme.com">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Industry</label>
                    <select wire:model="industry" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                        <option value="">Select industry</option>
                        @foreach(['Technology','Finance','Healthcare','Retail','Manufacturing','Education','Legal','Marketing','Real Estate','Other'] as $ind)
                            <option value="{{ $ind }}">{{ $ind }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Company Size</label>
                    <select wire:model="size" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                        <option value="">Select size</option>
                        <option value="1-10">1–10 employees</option>
                        <option value="11-50">11–50 employees</option>
                        <option value="51-200">51–200 employees</option>
                        <option value="201-500">201–500 employees</option>
                        <option value="501+">500+ employees</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Locale --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Locale & Region</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Country</label>
                    <input wire:model="country" type="text" maxlength="2" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="ZA">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Timezone</label>
                    <input wire:model="timezone" type="text" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid" placeholder="Africa/Johannesburg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Currency</label>
                    <select wire:model="currency" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                        <option value="USD">USD – US Dollar</option>
                        <option value="EUR">EUR – Euro</option>
                        <option value="GBP">GBP – British Pound</option>
                        <option value="ZAR">ZAR – South African Rand</option>
                        <option value="AUD">AUD – Australian Dollar</option>
                        <option value="CAD">CAD – Canadian Dollar</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition-colors" wire:loading.attr="disabled">
                <span wire:loading.remove>Save Settings</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </form>

    @elseif($tab === 'social')
        @livewire('organizations.social-credentials')
    @endif

</div>

