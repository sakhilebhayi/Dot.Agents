@php
    $platforms     = \App\Livewire\Social\ConnectPlatformWizard::platforms();
    $goals         = \App\Livewire\Social\ConnectPlatformWizard::goals();
    $aiFeatures    = \App\Livewire\Social\ConnectPlatformWizard::aiFeatures();
    $perms         = \App\Livewire\Social\ConnectPlatformWizard::permissionsList();
    $autonomy      = \App\Livewire\Social\ConnectPlatformWizard::autonomyLevels();
    $platformAccess= \App\Livewire\Social\ConnectPlatformWizard::platformAccess();
    $meta          = $platforms[$selectedPlatform] ?? null;
    $steps         = [1 => 'Platform', 2 => 'Authorize', 3 => 'Goals', 4 => 'Capabilities', 5 => 'Review'];
@endphp

<div class="max-w-3xl mx-auto">

    {{-- Progress bar --}}
    <nav aria-label="Setup progress" class="mb-8">
        <ol class="flex items-center gap-0">
            @foreach($steps as $n => $label)
                <li class="flex-1 flex items-center {{ $loop->last ? '' : 'after:flex-1 after:h-px after:mx-2 after:bg-gray-200 dark:after:bg-gray-700' }}">
                    <button @if($n < $step) wire:click="jumpToStep({{ $n }})" @endif
                            class="flex items-center gap-2 group {{ $n < $step ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($n >= $step) disabled @endif>
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 transition
                            {{ $n < $step  ? 'bg-brand-purple-mid text-white' : '' }}
                            {{ $n === $step ? 'bg-brand-purple-pale dark:bg-brand-purple-deeper/40 text-brand-purple dark:text-brand-purple-light ring-2 ring-brand-purple-mid' : '' }}
                            {{ $n > $step  ? 'bg-gray-100 dark:bg-gray-800 text-gray-400' : '' }}">
                            @if($n < $step)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                {{ $n }}
                            @endif
                        </span>
                        <span class="hidden sm:block text-xs font-medium
                            {{ $n === $step ? 'text-brand-purple dark:text-brand-purple-light' : 'text-gray-400 dark:text-gray-500' }}">
                            {{ $label }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- ── STEP 1: Choose Platform ──────────────────────────────────────────── --}}
    @if($step === 1)
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Which platform would you like to connect?</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Your AI agent will manage customer engagement on the platform you select.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            @foreach($platforms as $platform => $meta)
                <button wire:click="selectPlatform('{{ $platform }}')"
                        class="relative flex flex-col items-center gap-3 p-6 rounded-2xl border-2 transition-all
                            {{ in_array($platform, $this->connectedPlatforms) ? 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/10' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-brand-purple-light dark:hover:border-brand-purple-light hover:shadow-md' }}">
                    <div class="{{ $meta['color'] }} w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-sm">
                        {{ $meta['icon'] }}
                    </div>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white text-center">{{ $meta['label'] }}</span>
                    @if(in_array($platform, $this->connectedPlatforms))
                        <span class="absolute top-3 right-3 flex items-center gap-1 text-xs text-green-600 dark:text-green-400 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>Connected
                        </span>
                    @endif
                </button>
            @endforeach

            {{-- WhatsApp — coming soon --}}
            <div class="flex flex-col items-center gap-3 p-6 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed">
                <div class="bg-green-500 w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-sm">W</div>
                <span class="text-sm font-semibold text-gray-900 dark:text-white text-center">WhatsApp Business</span>
                <span class="text-xs text-gray-400 font-medium">Coming Soon</span>
            </div>
        </div>
    </div>
    @endif

    {{-- ── STEP 2: Authorize ───────────────────────────────────────────────── --}}
    @if($step === 2)
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <div class="{{ $meta['color'] }} w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0">
                {{ $meta['icon'] }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Authorize {{ $meta['short'] }}</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Review what Dot.Agents will be able to do on your behalf.</p>
            </div>
        </div>

        {{-- What we'll access --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Dot.Agents will request permission to:</h3>
            <ul class="space-y-2">
                @foreach($platformAccess[$selectedPlatform] ?? [] as $access)
                    <li class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ $access }}
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Connection mode --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">How would you like to connect?</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button wire:click="$set('connectionMode','quick')" type="button"
                        class="flex flex-col gap-2 p-4 rounded-xl border-2 text-left transition
                            {{ $connectionMode === 'quick' ? 'border-brand-purple-light bg-brand-purple-pale dark:bg-brand-purple-deeper/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 {{ $connectionMode === 'quick' ? 'text-brand-purple-mid' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Quick Connect</span>
                        <span class="ml-auto px-1.5 py-0.5 rounded text-xs font-medium bg-brand-purple-pale dark:bg-brand-purple-deeper/40 text-brand-purple dark:text-brand-purple-light">Recommended</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Use Dot.Agents' OAuth app. Connect in seconds, no configuration needed.</p>
                </button>

                <button wire:click="$set('connectionMode','advanced')" type="button"
                        class="flex flex-col gap-2 p-4 rounded-xl border-2 text-left transition
                            {{ $connectionMode === 'advanced' ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 {{ $connectionMode === 'advanced' ? 'text-yellow-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Use Your Own App</span>
                        <span class="ml-auto px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-500">Enterprise</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Connect using your organization's own developer app on {{ $meta['short'] }}.</p>
                </button>
            </div>

            {{-- Advanced credentials form --}}
            @if($connectionMode === 'advanced')
                <div class="border-t border-gray-100 dark:border-gray-800 pt-4 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Create an app at the <a href="{{ [\App\Livewire\Organizations\SocialCredentials::$platforms[$selectedPlatform]['docs'] ?? '#'] | join('') }}" target="_blank" rel="noopener noreferrer" class="text-brand-purple-mid hover:underline">{{ $meta['short'] }} Developer Console</a>
                        and set the redirect URI to:
                        <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded ml-1">{{ route('social.auth.callback', ['platform' => $selectedPlatform]) }}</code>
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">App / Client ID *</label>
                            <input wire:model="advClientId" type="text" autocomplete="off" spellcheck="false"
                                   class="w-full text-sm font-mono rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid"
                                   placeholder="App ID or Client ID">
                            @error('advClientId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">App / Client Secret *</label>
                            <input wire:model="advClientSecret" type="password" autocomplete="new-password"
                                   class="w-full text-sm font-mono rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid focus:border-brand-purple-mid"
                                   placeholder="••••••••••••••••">
                            @error('advClientSecret') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── STEP 3: Business Goals ───────────────────────────────────────────── --}}
    @if($step === 3)
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">What are you trying to achieve?</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Your AI agent will optimise its behaviour around your business goals.</p>
        </div>

        @error('selectedGoals')
            <div class="flex items-center gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-400 text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ $message }}
            </div>
        @enderror

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($goals as $key => $goal)
                <label class="cursor-pointer">
                    <input type="checkbox" wire:model="selectedGoals" value="{{ $key }}" class="sr-only peer">
                    <div class="flex items-start gap-4 p-4 rounded-2xl border-2 transition-all bg-white dark:bg-gray-900
                        peer-checked:border-brand-purple-light peer-checked:bg-brand-purple-pale dark:peer-checked:bg-brand-purple-deeper/20
                        border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600">
                        <span class="text-2xl flex-shrink-0 mt-0.5">{{ $goal['icon'] }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ $goal['label'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $goal['desc'] }}</p>
                        </div>
                        <div class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition
                                    peer-checked:bg-brand-purple-mid peer-checked:border-brand-purple-mid border-gray-300 dark:border-gray-600 mt-0.5">
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── STEP 4: AI Capabilities ──────────────────────────────────────────── --}}
    @if($step === 4)
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Configure AI Capabilities</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Choose what your agent can do and how much autonomy it has.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Left: Features + Permissions --}}
            <div class="space-y-5">

                {{-- AI Features --}}
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Enable AI Features</h3>
                    @foreach($aiFeatures as $key => $feature)
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <input type="checkbox" wire:model="enabledFeatures" value="{{ $key }}"
                                   class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-brand-purple-mid focus:ring-brand-purple-light">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-brand-purple dark:group-hover:text-brand-purple-light transition">{{ $feature['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $feature['desc'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Permissions --}}
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">What can the agent do?</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500">High-risk actions require explicit approval by default.</p>
                    @foreach($perms as $key => $perm)
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="enabledPermissions" value="{{ $key }}"
                                   class="rounded border-gray-300 dark:border-gray-600 text-brand-purple-mid focus:ring-brand-purple-light">
                            <span class="text-sm flex-1 text-gray-800 dark:text-gray-200">{{ $perm['icon'] }} {{ $perm['label'] }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                {{ $perm['risk'] === 'low'    ? 'bg-green-100  dark:bg-green-900/30  text-green-700  dark:text-green-400' : '' }}
                                {{ $perm['risk'] === 'medium' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400' : '' }}
                                {{ $perm['risk'] === 'high'   ? 'bg-red-100    dark:bg-red-900/30    text-red-700    dark:text-red-400' : '' }}">
                                {{ ucfirst($perm['risk']) }} risk
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Right: Autonomy Level --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Autonomy Level</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">How much should the agent act on its own?</p>
                <div class="space-y-3">
                    @foreach($autonomy as $level => $info)
                        <label class="cursor-pointer block">
                            <input type="radio" wire:model.number="autonomyLevel" value="{{ $level }}" class="sr-only peer">
                            <div class="flex items-start gap-3 p-3 rounded-xl border-2 transition
                                peer-checked:border-brand-purple-light peer-checked:bg-brand-purple-pale dark:peer-checked:bg-brand-purple-deeper/20
                                border-gray-200 dark:border-gray-700 hover:border-gray-300">
                                <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5 transition
                                    peer-checked:bg-brand-purple-mid peer-checked:border-brand-purple-mid peer-checked:text-white
                                    border-gray-300 dark:border-gray-600 text-gray-500">{{ $level }}</span>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $info['label'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $info['desc'] }}</p>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── STEP 5: Review & Activate ────────────────────────────────────────── --}}
    @if($step === 5)
    @php
        $platformMeta  = $platforms[$selectedPlatform];
        $autonomyInfo  = $autonomy[$autonomyLevel];
    @endphp
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Review & Activate</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Your agent is ready to go. Review the summary below and activate.</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">

            {{-- Platform --}}
            <div class="flex items-center gap-4 px-6 py-4">
                <div class="{{ $platformMeta['color'] }} w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0">
                    {{ $platformMeta['icon'] }}
                </div>
                <div class="flex-1">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Platform</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5">{{ $platformMeta['label'] }}</p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full
                    {{ $connectionMode === 'quick' ? 'bg-brand-purple-pale dark:bg-brand-purple-deeper/30 text-brand-purple dark:text-brand-purple-light' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400' }}">
                    {{ $connectionMode === 'quick' ? 'Quick Connect' : 'Custom App' }}
                </span>
            </div>

            {{-- Goals --}}
            <div class="px-6 py-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Business Goals</p>
                <div class="flex flex-wrap gap-2">
                    @forelse($selectedGoals as $goalKey)
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            {{ $goals[$goalKey]['icon'] ?? '' }} {{ $goals[$goalKey]['label'] ?? $goalKey }}
                        </span>
                    @empty
                        <span class="text-xs text-gray-400">None selected</span>
                    @endforelse
                </div>
            </div>

            {{-- AI Features --}}
            <div class="px-6 py-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">AI Capabilities</p>
                <div class="flex flex-wrap gap-2">
                    @forelse($enabledFeatures as $featureKey)
                        <span class="flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            {{ $aiFeatures[$featureKey]['label'] ?? $featureKey }}
                        </span>
                    @empty
                        <span class="text-xs text-gray-400">None selected</span>
                    @endforelse
                </div>
            </div>

            {{-- Autonomy --}}
            <div class="px-6 py-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Autonomy Level</p>
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-brand-purple-mid text-white flex items-center justify-center text-xs font-bold">{{ $autonomyLevel }}</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $autonomyInfo['label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $autonomyInfo['desc'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- What happens next --}}
        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-800 dark:text-blue-300">
            <p class="font-semibold mb-1">What happens next?</p>
            <p>You'll be taken to {{ $platformMeta['short'] }} to authorize access. Once approved, your AI agent will begin managing {{ $platformMeta['short'] }} according to the settings above.</p>
        </div>
    </div>
    @endif

    {{-- ── Navigation ───────────────────────────────────────────────────────── --}}
    @if($step > 1)
    <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
        <button wire:click="prevStep" type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        @if($step < 5)
            <button wire:click="nextStep" type="button"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-semibold rounded-xl transition">
                Continue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        @else
            <button wire:click="activate" type="button"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-8 py-3 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-bold rounded-xl shadow-lg shadow-yellow-200 dark:shadow-yellow-900/30 transition disabled:opacity-60">
                <span wire:loading.remove wire:target="activate">
                    <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Activate & Connect
                </span>
                <span wire:loading wire:target="activate">Connecting…</span>
            </button>
        @endif
    </div>
    @endif

</div>
