@php
    use App\Livewire\Social\ConnectPlatformWizard;
    $goals    = ConnectPlatformWizard::goals();
    $features = ConnectPlatformWizard::aiFeatures();
    $autonomy = ConnectPlatformWizard::autonomyLevels();
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Social Media Connections</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Connect your social media accounts so Dot.Agents can manage customer engagement,
                lead generation, conversations, and customer support.
            </p>
        </div>
        <a href="{{ route('social.connect') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-bold rounded-xl transition shadow-sm self-start sm:self-auto flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Connect Account
        </a>
    </div>

    @if(session('success'))
        <div role="alert" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($this->platformStatus as $platform => $info)
        <div class="bg-white dark:bg-gray-900 rounded-2xl border-2 transition
            {{ $info['connected'] ? 'border-green-200 dark:border-green-800' : 'border-gray-200 dark:border-gray-700' }}
            {{ $managing === $platform ? 'ring-2 ring-brand-purple-light' : '' }}">

            <div class="p-5">
                <div class="flex items-center gap-3">
                    <div class="{{ $info['color'] }} w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                        {{ $info['icon'] }}
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ $info['label'] }}</p>
                        @if($info['connected'])
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
                                <span class="text-xs text-green-600 dark:text-green-400 font-medium">Connected</span>
                            </div>
                        @else
                            <span class="text-xs text-gray-400 dark:text-gray-500">Not connected</span>
                        @endif
                    </div>
                </div>

                @if($info['connected'])
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-center">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $info['page_count'] }}</p>
                            <p class="text-xs text-gray-500">Pages</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-center">
                            <p class="text-xs font-medium text-gray-500 mb-1">Last Sync</p>
                            <p class="text-xs font-semibold text-gray-900 dark:text-white">
                                {{ $info['last_synced'] ? $info['last_synced']->diffForHumans() : 'Never' }}
                            </p>
                        </div>
                    </div>

                    @if($info['settings'] && $info['settings']->goals)
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach(array_slice($info['settings']->goals, 0, 3) as $goalKey)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-brand-purple-pale dark:bg-brand-purple-deeper/30 text-brand-purple dark:text-brand-purple-light">
                                    {{ $goals[$goalKey]['icon'] ?? '' }} {{ $goals[$goalKey]['label'] ?? $goalKey }}
                                </span>
                            @endforeach
                            @if(count($info['settings']->goals) > 3)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800 text-gray-500">+{{ count($info['settings']->goals) - 3 }} more</span>
                            @endif
                        </div>
                    @endif

                    @if($info['settings'])
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                            Level {{ $info['settings']->autonomy_level }}:
                            <span class="font-medium text-gray-600 dark:text-gray-300">{{ $autonomy[$info['settings']->autonomy_level]['label'] ?? '' }}</span>
                        </p>
                    @endif

                    <div class="mt-4 flex items-center gap-2">
                        @if($managing === $platform)
                            <button wire:click="closeManage"
                                    class="flex-1 px-3 py-2 text-xs font-semibold rounded-xl bg-brand-purple-mid border border-brand-purple-mid text-white text-center">Done</button>
                        @else
                            <button wire:click="openManage('{{ $platform }}')"
                                    class="flex-1 px-3 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-brand-purple-light hover:text-brand-purple-mid text-center transition">Manage Settings</button>
                        @endif
                        <button wire:click="disconnect('{{ $platform }}')"
                                wire:confirm="Disconnect {{ $info['short'] }}? Your agent will lose access to this account."
                                class="px-3 py-2 text-xs font-medium rounded-xl border border-red-200 dark:border-red-800 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                            Disconnect
                        </button>
                    </div>

                @else
                    <div class="mt-6">
                        <a href="{{ route('social.connect') }}"
                           class="block w-full text-center px-4 py-2.5 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-500 dark:text-gray-400 hover:border-brand-purple-light hover:text-brand-purple-mid transition">
                            + Connect {{ $info['short'] }}
                        </a>
                    </div>
                @endif
            </div>

            @if($managing === $platform && $info['connected'])
            <div class="border-t border-gray-100 dark:border-gray-800 p-5 space-y-5 bg-gray-50 dark:bg-gray-800/40 rounded-b-2xl">

                @if($settingsSaved)
                    <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400 font-medium">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Settings saved!
                    </div>
                @endif

                <div>
                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">Business Goals</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($goals as $key => $goal)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="editGoals" value="{{ $key }}"
                                       class="rounded border-gray-300 dark:border-gray-600 text-brand-purple-mid focus:ring-brand-purple-light">
                                <span class="text-xs text-gray-700 dark:text-gray-300">{{ $goal['icon'] }} {{ $goal['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">AI Capabilities</p>
                    <div class="space-y-2">
                        @foreach($features as $key => $feature)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="editFeatures" value="{{ $key }}"
                                       class="rounded border-gray-300 dark:border-gray-600 text-brand-purple-mid focus:ring-brand-purple-light">
                                <span class="text-xs text-gray-700 dark:text-gray-300">{{ $feature['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">Autonomy Level</p>
                    <select wire:model.number="editAutonomy"
                            class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid">
                        @foreach($autonomy as $level => $levelInfo)
                            <option value="{{ $level }}">Level {{ $level }}: {{ $levelInfo['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <button wire:click="saveSettings" wire:loading.attr="disabled"
                        class="w-full py-2.5 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-semibold rounded-xl transition disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveSettings">Save Settings</span>
                    <span wire:loading wire:target="saveSettings">Saving...</span>
                </button>
            </div>
            @endif

        </div>
        @endforeach
    </div>

</div>
