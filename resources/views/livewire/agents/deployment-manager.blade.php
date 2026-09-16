<div class="space-y-6">
    {{-- Flash messages --}}
    @if (session('status'))
        <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-wrap gap-3">
        <div class="flex-1 min-w-48">
            <input wire:model.live.debounce.300ms="search" type="search"
                placeholder="Search deployments..."
                class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-purple-600">
        </div>
        <select wire:model.live="filterStatus"
            class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-purple-600">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="configuring">Configuring</option>
            <option value="decommissioned">Decommissioned</option>
        </select>
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($deployments as $dep)
            @php
                $score = $dep->latestScorecard?->overall_health_score ?? null;
                $statusColors = ['active' => 'emerald', 'paused' => 'yellow', 'configuring' => 'blue', 'decommissioned' => 'gray', 'suspended' => 'red'];
                $statusColor = $statusColors[$dep->status] ?? 'gray';
            @endphp
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-all">
                {{-- Header --}}
                <div class="flex items-start justify-between mb-3">
                    <a href="{{ route('agents.show', $dep) }}" class="flex items-center gap-3 min-w-0 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-700 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                            {{ substr($dep->agent?->name ?? 'AI', 0, 2) }}
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate group-hover:text-purple-600 dark:group-hover:text-purple-400">{{ $dep->display_name }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $dep->agent?->agentDepartment?->name }}</p>
                        </div>
                    </a>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 dark:bg-{{ $statusColor }}-900/30 dark:text-{{ $statusColor }}-400">
                        {{ ucfirst($dep->status) }}
                    </span>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <div class="text-center bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
                        <p class="text-xs text-gray-400">Health</p>
                        <p class="font-bold text-sm {{ $score >= 80 ? 'text-emerald-600' : ($score >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $score ? round($score) . '%' : '—' }}
                        </p>
                    </div>
                    <div class="text-center bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
                        <p class="text-xs text-gray-400">Mode</p>
                        <p class="font-semibold text-xs text-gray-700 dark:text-gray-300 truncate">{{ ucfirst($dep->deployment_mode) }}</p>
                    </div>
                    <div class="text-center bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
                        <p class="text-xs text-gray-400">Tasks</p>
                        <p class="font-bold text-sm text-gray-700 dark:text-gray-300">{{ number_format($dep->total_tasks_completed ?? 0) }}</p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2">
                    @if($dep->status === 'active')
                        <a href="{{ route('agents.chat', $dep) }}"
                            class="flex-1 py-1.5 text-xs font-medium text-center bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            Chat
                        </a>
                    @else
                        <span class="flex-1 py-1.5 text-xs font-medium text-center bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600 rounded-lg cursor-not-allowed" title="Chat only available for active deployments">
                            Chat
                        </span>
                    @endif
                    <a href="{{ route('agents.scorecard', $dep) }}"
                        class="flex-1 py-1.5 text-xs font-medium text-center border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        Scorecard
                    </a>
                    @can('update', $dep)
                        @if($dep->status === 'active')
                            <button wire:click="pauseDeployment({{ $dep->id }})"
                                wire:confirm="Pause this deployment?"
                                wire:loading.attr="disabled"
                                class="p-1.5 text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 disabled:opacity-50 rounded-lg transition-colors" title="Pause">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>
                        @elseif($dep->status === 'paused')
                            <button wire:click="resumeDeployment({{ $dep->id }})"
                                wire:loading.attr="disabled"
                                class="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 disabled:opacity-50 rounded-lg transition-colors" title="Resume">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No agents deployed yet.</p>
                <a href="{{ route('marketplace') }}" class="inline-flex items-center gap-2 text-sm text-purple-600 hover:text-purple-700 font-medium">
                    Browse the Marketplace →
                </a>
            </div>
        @endforelse
    </div>

    <div>{{ $deployments->links() }}</div>
</div>
