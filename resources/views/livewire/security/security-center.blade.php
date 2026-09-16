<div class="space-y-6">
    {{-- Flash message --}}
    @if(session('status'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl p-4 text-green-800 dark:text-green-300 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    {{-- Emergency Kill Switches --}}
    <div class="bg-red-950 border border-red-700/40 rounded-2xl p-6 text-white">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 rounded-xl bg-red-700/50 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div>
                <h2 class="font-bold text-base text-red-200">Emergency Kill Switches</h2>
                <p class="text-red-400 text-xs">Use only in active incidents. All actions are permanently logged.</p>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-4">
            {{-- Halt all workflows --}}
            <div class="flex-1 bg-red-900/40 rounded-xl p-4 border border-red-700/30">
                <p class="text-xs text-red-300 mb-3 font-medium uppercase tracking-wide">Halt All Workflows</p>
                <input wire:model="killSwitchConfirmation"
                    type="text" placeholder="Type: HALT WORKFLOWS"
                    class="w-full bg-red-950 border border-red-600 text-white text-sm rounded-lg px-3 py-2 mb-3 placeholder-red-600 focus:outline-none focus:ring-2 focus:ring-red-500">
                @error('killSwitchConfirmation')<p class="text-red-400 text-xs mb-2">{{ $message }}</p>@enderror
                <button wire:click="killAllWorkflows" wire:loading.attr="disabled" wire:confirm="This will immediately halt all running workflows. Confirm?"
                    class="w-full py-2 bg-red-600 hover:bg-red-500 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition-colors">
                    <span wire:loading.remove wire:target="killAllWorkflows">⛔ Halt All Workflows</span>
                    <span wire:loading wire:target="killAllWorkflows">Halting...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- DIS Control Panel --}}
    <div class="bg-gradient-to-br from-gray-900 to-purple-950 dark:from-gray-950 dark:to-purple-950 rounded-2xl border border-purple-800/30 p-6 text-white">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-700/50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-lg">Digital Immune System</h2>
                    <p class="text-purple-300 text-sm">Real-time threat detection & autonomous remediation</p>
                </div>
            </div>
            <button wire:click="runDISCheck" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold text-sm rounded-xl transition-colors disabled:opacity-60">
                <span wire:loading.remove wire:target="runDISCheck">▶ Run Health Check</span>
                <span wire:loading wire:target="runDISCheck">Scanning...</span>
            </button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['Events (24h)', $this->stats['total_24h'], 'purple'],
                ['Critical Open', $this->stats['critical'], 'red'],
                ['Auto-Remediated', $this->stats['auto_remediated'], 'emerald'],
                ['Quarantined', $this->stats['quarantined'], 'orange'],
            ] as [$label, $value, $color])
                <div class="bg-white/5 rounded-xl p-4 text-center">
                    <div class="text-3xl font-bold text-white">{{ $value }}</div>
                    <div class="text-xs text-purple-300 mt-1">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- DIS Report (shown after running) --}}
    @if($disReport)
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">
                DIS Health Check Report
                <span class="ml-2 text-xs text-gray-400">{{ $disReport['total_agents'] ?? 0 }} agents checked &middot; {{ count($disReport['events'] ?? []) }} events</span>
            </h3>

            @if(!empty($disReport['events']))
                <div class="space-y-2 mb-4">
                    @foreach($disReport['events'] as $event)
                        <div class="flex items-start gap-3 p-3 rounded-xl
                            {{ in_array($event['severity'] ?? '', ['critical', 'error']) ? 'bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800' : 'bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800' }}">
                            <span class="text-sm">{{ ($event['severity'] ?? '') === 'critical' ? '🔴' : '⚠️' }}</span>
                            <div>
                                <p class="text-xs text-gray-700 dark:text-gray-300">{{ $event['message'] ?? ucwords(str_replace('_', ' ', $event['type'] ?? 'Unknown issue')) }}</p>
                                @if(!empty($event['recommendation']))
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $event['recommendation'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center gap-2 p-3 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-xl mb-4">
                    <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">All agents healthy. No threats detected.</p>
                </div>
            @endif

            @if(($disReport['quarantined'] ?? 0) > 0)
                <div class="text-xs text-purple-700 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/20 rounded-lg px-3 py-2">
                    ⚡ {{ $disReport['quarantined'] }} agent(s) automatically quarantined.
                </div>
            @endif
        </div>
    @endif

    {{-- Security Events --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Security Events</h3>
            <div class="flex gap-2">
                <select wire:model.live="filterSeverity"
                    class="text-xs rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                    <option value="">All Severities</option>
                    <option value="critical">Critical</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                    <option value="info">Info</option>
                </select>
                <select wire:model.live="filterType"
                    class="text-xs rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                    <option value="">All Types</option>
                    <option value="prompt_injection">Prompt Injection</option>
                    <option value="agent_drift">Agent Drift</option>
                    <option value="delusion_detected">Delusion Detected</option>
                    <option value="permission_abuse">Permission Abuse</option>
                    <option value="autonomy_violation">Autonomy Violation</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                        <th class="text-left px-5 py-3 text-gray-500 font-medium">Timestamp</th>
                        <th class="text-left px-5 py-3 text-gray-500 font-medium">Event Type</th>
                        <th class="text-left px-5 py-3 text-gray-500 font-medium">Severity</th>
                        <th class="text-left px-5 py-3 text-gray-500 font-medium">Status</th>
                        <th class="text-left px-5 py-3 text-gray-500 font-medium">Auto-Remediated</th>
                        <th class="text-left px-5 py-3 text-gray-500 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($this->events as $event)
                        @php
                            $sevColors = ['critical' => 'red', 'error' => 'orange', 'warning' => 'yellow', 'info' => 'blue'];
                            $sevColor = $sevColors[$event->severity] ?? 'gray';
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                            <td class="px-5 py-3 font-mono text-gray-400">{{ $event->created_at->format('M j H:i:s') }}</td>
                            <td class="px-5 py-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ ucwords(str_replace('_', ' ', $event->event_type)) }}</span>
                                @if($event->description)
                                    <p class="text-gray-400 mt-0.5 line-clamp-1">{{ $event->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-{{ $sevColor }}-100 text-{{ $sevColor }}-700 dark:bg-{{ $sevColor }}-900/30 dark:text-{{ $sevColor }}-400">
                                    {{ ucfirst($event->severity) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="{{ $event->status === 'resolved' ? 'text-emerald-600 dark:text-emerald-400' : 'text-orange-600 dark:text-orange-400' }} font-medium">
                                    {{ ucfirst($event->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if($event->auto_remediated)
                                    <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($event->status === 'open')
                                    <div class="flex gap-2 items-center">
                                        <button wire:click="resolveEvent({{ $event->id }})"
                                            class="text-xs text-purple-600 hover:text-purple-700 dark:text-purple-400 font-medium">
                                            Resolve
                                        </button>
                                        @if($event->agent_deployment_id)
                                            <button wire:click="killDeployment({{ $event->agent_deployment_id }})"
                                                wire:confirm="Immediately suspend this agent? This cannot be undone without manual reactivation."
                                                class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 font-medium">
                                                Kill Agent
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">No security events found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->events->links() }}
        </div>
    </div>
</div>
