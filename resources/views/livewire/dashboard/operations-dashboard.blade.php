<div class="space-y-6" wire:poll.30s="refresh">
    {{-- Loading indicator --}}
    <div wire:loading class="fixed top-0 inset-x-0 h-0.5 bg-brand-purple-mid z-50 animate-pulse" role="status" aria-label="Loading"></div>

    {{-- Platform Health Banner --}}
    @php
        $healthColors = ['green' => 'bg-green-100 border-green-400 text-green-800 dark:bg-green-900/30 dark:border-green-500 dark:text-green-300',
                         'amber' => 'bg-yellow-100 border-yellow-400 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-500 dark:text-yellow-300',
                         'red'   => 'bg-red-100 border-red-400 text-red-800 dark:bg-red-900/30 dark:border-red-500 dark:text-red-300'];
        $healthLabels = ['green' => 'All Systems Operational', 'amber' => 'Degraded Performance', 'red' => 'Critical — Immediate Action Required'];
        $health = $this->platformHealth;
    @endphp
    <div class="border-l-4 rounded-lg p-4 {{ $healthColors[$health] ?? $healthColors['green'] }}" role="alert" aria-live="polite">
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded-full {{ $health === 'green' ? 'bg-green-500' : ($health === 'amber' ? 'bg-yellow-500' : 'bg-red-500') }}"></div>
            <span class="font-semibold">Platform Status: {{ $healthLabels[$health] ?? 'Unknown' }}</span>
            <span class="ml-auto text-xs opacity-70">Updated {{ now()->format('H:i:s') }}</span>
        </div>
    </div>

    {{-- Primary KPI Row --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {{-- Active Deployments --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Active Agents</p>
            <p class="mt-1 text-3xl font-bold text-brand-purple dark:text-brand-purple-light">{{ $this->activeDeployments }}</p>
        </div>

        {{-- Executions 24h --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Executions (24h)</p>
            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($this->agentExecutions['total_24h'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-gray-400">✓ {{ $this->agentExecutions['completed'] ?? 0 }} &nbsp; ✗ {{ $this->agentExecutions['failed'] ?? 0 }}</p>
        </div>

        {{-- Failure Rate --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Failure Rate</p>
            <p class="mt-1 text-3xl font-bold {{ $this->failureRate >= 10 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                {{ $this->failureRate }}%
            </p>
        </div>

        {{-- Avg Response Time --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Avg Response</p>
            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                {{ $this->avgResponseTimeMs >= 1000 ? round($this->avgResponseTimeMs / 1000, 1).'s' : $this->avgResponseTimeMs.'ms' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Circuit Breaker Status --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-4 font-semibold text-gray-700 dark:text-gray-300">AI Provider Status</h3>
            <ul class="space-y-2">
                @forelse($this->circuitBreakers as $service => $cb)
                    @php
                        $state = $cb['state'] ?? 'closed';
                        $label = str_replace('ai_inference_', '', $service);
                        $dot = $state === 'closed' ? 'bg-green-500' : ($state === 'half_open' ? 'bg-yellow-500' : 'bg-red-500');
                    @endphp
                    <li class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                            <span class="capitalize text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        </div>
                        <span class="font-mono text-xs {{ $state === 'closed' ? 'text-green-600' : 'text-red-600' }}">
                            {{ strtoupper($state) }}
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">No circuit data available</li>
                @endforelse
            </ul>
        </div>

        {{-- Queue Depths --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-4 font-semibold text-gray-700 dark:text-gray-300">Queue Depth</h3>
            <ul class="space-y-2">
                @php $queueDepth = $this->queueDepth; @endphp
                @foreach(['governance', 'notifications', 'agents', 'billing'] as $queue)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400 capitalize">{{ $queue }}</span>
                        <span class="font-mono {{ ($queueDepth[$queue] ?? 0) > 100 ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ number_format($queueDepth[$queue] ?? 0) }}
                        </span>
                    </li>
                @endforeach
                <li class="flex items-center justify-between border-t border-gray-100 pt-2 text-sm dark:border-gray-700">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Total</span>
                    <span class="font-bold">{{ number_format($queueDepth['_total'] ?? 0) }}</span>
                </li>
                @if(($queueDepth['_failed'] ?? 0) > 0)
                <li class="flex items-center justify-between text-sm text-red-600">
                    <span>Failed Jobs</span>
                    <span class="font-bold">{{ $queueDepth['_failed'] }}</span>
                </li>
                @endif
            </ul>
        </div>

        {{-- Security Events --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-4 font-semibold text-gray-700 dark:text-gray-300">Security Events</h3>
            @php $sec = $this->securityEvents; @endphp
            <ul class="space-y-2">
                <li class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Critical (Open)</span>
                    <span class="{{ ($sec['open_critical'] ?? 0) > 0 ? 'font-bold text-red-600' : 'text-gray-700 dark:text-gray-300' }}">
                        {{ $sec['open_critical'] ?? 0 }}
                    </span>
                </li>
                <li class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">High (Open)</span>
                    <span class="{{ ($sec['open_high'] ?? 0) > 0 ? 'font-bold text-orange-600' : 'text-gray-700 dark:text-gray-300' }}">
                        {{ $sec['open_high'] ?? 0 }}
                    </span>
                </li>
                <li class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Events (24h)</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ $sec['last_24h'] ?? 0 }}</span>
                </li>
                <li class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Prompt Injections (24h)</span>
                    <span class="{{ ($sec['prompt_injections_24h'] ?? 0) > 0 ? 'font-bold text-orange-600' : 'text-gray-700 dark:text-gray-300' }}">
                        {{ $sec['prompt_injections_24h'] ?? 0 }}
                    </span>
                </li>
                <li class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Auto-Remediated</span>
                    <span class="text-green-600 dark:text-green-400">{{ $sec['auto_remediated'] ?? 0 }}</span>
                </li>
            </ul>
        </div>
    </div>

    {{-- Awaiting Approval Banner --}}
    @if(($this->agentExecutions['awaiting_approval'] ?? 0) > 0)
    <div class="flex items-center justify-between rounded-lg border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-600 dark:bg-yellow-900/20">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/></svg>
            <span class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                {{ $this->agentExecutions['awaiting_approval'] }} task(s) awaiting human approval
            </span>
        </div>
        <a href="{{ route('approvals.index') }}" class="text-sm font-semibold text-yellow-700 underline dark:text-yellow-400">
            Review Now →
        </a>
    </div>
    @endif
</div>
