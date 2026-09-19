<div class="space-y-5">
    {{-- Loading indicator --}}
    <div wire:loading class="fixed top-0 inset-x-0 h-0.5 bg-brand-purple-mid z-50 animate-pulse" role="status" aria-label="Loading"></div>

    {{-- Filters bar --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
        <div class="flex flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input wire:model.live.debounce.300ms="search" type="search"
                    placeholder="Search logs..."
                    class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            </div>
            <select wire:model.live="filterCategory"
                class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
                <option value="">All Categories</option>
                <option value="agent_action">Agent Actions</option>
                <option value="user_action">User Actions</option>
                <option value="security">Security</option>
                <option value="system">System</option>
                <option value="governance">Governance</option>
            </select>
            <select wire:model.live="filterRisk"
                class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
                <option value="">All Risk Levels</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="info">Info</option>
            </select>
            <select wire:model.live="filterAgent"
                class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
                <option value="">All Agents</option>
                @foreach($this->deployments as $dep)
                    <option value="{{ $dep->id }}">{{ $dep->display_name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                <input wire:model.live="showFlagged" type="checkbox"
                    class="rounded border-gray-300 dark:border-gray-600 text-brand-purple-mid focus:ring-brand-purple-mid">
                Flagged only
                @if($this->flaggedCount > 0)
                    <span class="bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 text-xs font-bold rounded-full px-2">{{ $this->flaggedCount }}</span>
                @endif
            </label>
            <input wire:model.live="dateFrom" type="date"
                class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <input wire:model.live="dateTo" type="date"
                class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <div class="flex items-center gap-2 ml-auto">
                <button type="button" wire:click="export('csv')"
                    class="text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Export CSV
                </button>
                <button type="button" wire:click="export('json')"
                    class="text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Export JSON
                </button>
            </div>
        </div>
    </div>

    {{-- Log Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Timestamp</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actor</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Risk</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($this->logs as $log)
                        @php
                            $riskBadges = [
                                'critical' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                                'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                'low' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                'info' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                            ];
                            $riskClass = $riskBadges[$log->risk_level] ?? $riskBadges['info'];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors {{ $log->flagged ? 'bg-red-50/50 dark:bg-red-950/10' : '' }}">
                            <td class="px-5 py-3 text-xs font-mono text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    @if($log->flagged)
                                        <span class="text-red-500 text-xs" title="Flagged">⚠</span>
                                    @endif
                                    <span class="font-medium text-gray-900 dark:text-white text-xs">{{ $log->event }}</span>
                                </div>
                                @if($log->description)
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $log->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-400">
                                @if($log->agent_deployment_id)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-brand-purple-light"></span>
                                        {{ $log->deployment?->display_name ?? 'Agent' }}
                                    </span>
                                @elseif($log->user_id)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        {{ $log->user?->name ?? 'User' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        System
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 font-medium">
                                    {{ ucwords(str_replace('_', ' ', $log->event_category ?? 'system')) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $riskClass }}">
                                    {{ ucfirst($log->risk_level ?? 'info') }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $log->flagged ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                                    {{ $log->flagged ? 'Flagged' : 'Normal' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                                No audit log entries found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->logs->links() }}
        </div>
    </div>
</div>
