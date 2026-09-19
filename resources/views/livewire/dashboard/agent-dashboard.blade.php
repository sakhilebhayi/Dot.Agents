<div class="space-y-6">
    {{-- Header Row --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">AI Workforce Overview</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Monitor your deployed agents, tasks, and governance metrics</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach(["24h" => "24h", "7d" => "7 Days", "30d" => "30 Days", "90d" => "90 Days"] as $key => $label)
                <button wire:click="setTimeframe('{{ $key }}')"
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $timeframe === $key ? 'bg-brand-purple-mid text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Active Agents</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $this->deploymentStats["active"] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->deploymentStats["total"] }} total deployed</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Tasks Completed</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($this->taskStats["completed"]) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->taskStats["total"] > 0 ? round(($this->taskStats["completed"] / $this->taskStats["total"]) * 100) . "%" : "0%" }} completion</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Pending Approvals</p>
            <p class="text-3xl font-bold {{ count($this->pendingApprovals) > 0 ? "text-yellow-600" : "text-gray-900 dark:text-white" }}">{{ count($this->pendingApprovals) }}</p>
            <p class="text-xs text-gray-500 mt-1">Awaiting human review</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">AI Spend</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($this->costStats["total"], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">${{ number_format($this->costStats["daily_avg"], 2) }}/day avg</p>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Active Agents List --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-semibold text-gray-900 dark:text-white">Deployed Agents</h3>
                <a href="{{ route("agents.deployments") }}" class="text-xs text-brand-purple-mid dark:text-brand-purple-light hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->activeAgents as $deployment)
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-purple-light to-brand-purple flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                            {{ strtoupper(substr($deployment->display_name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $deployment->display_name }}</p>
                            <p class="text-xs text-gray-500">{{ $deployment->agent->agentDepartment?->name ?? "General" }} &bull; {{ ucfirst($deployment->deployment_mode) }}</p>
                        </div>
                        @if($deployment->latestScorecard)
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-bold {{ $deployment->latestScorecard->overall_health_score >= 80 ? "text-green-600" : ($deployment->latestScorecard->overall_health_score >= 60 ? "text-yellow-600" : "text-red-600") }}">
                                {{ number_format($deployment->latestScorecard->overall_health_score, 1) }}/100
                            </p>
                            <p class="text-xs text-gray-400">Health</p>
                        </div>
                        @endif
                        <span class="w-2.5 h-2.5 rounded-full {{ $deployment->status === "active" ? "bg-green-500" : "bg-gray-300" }} flex-shrink-0"></span>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="text-gray-500 text-sm">No agents deployed yet.</p>
                        <a href="{{ route("marketplace") }}" class="text-brand-purple-mid text-sm hover:underline mt-1 inline-block">Browse Marketplace &rarr;</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right column --}}
        <div class="space-y-4">
            {{-- Pending Approvals --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Approval Queue</h3>
                    @if(count($this->pendingApprovals) > 0)
                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full font-medium">{{ count($this->pendingApprovals) }}</span>
                    @endif
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($this->pendingApprovals as $approval)
                        <div class="px-5 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ $approval->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $approval->deployment?->display_name }}</p>
                                </div>
                                <span class="flex-shrink-0 px-1.5 py-0.5 text-xs rounded font-medium
                                    {{ $approval->risk_level === "critical" ? "bg-red-100 text-red-700" : ($approval->risk_level === "high" ? "bg-orange-100 text-orange-700" : "bg-yellow-100 text-yellow-700") }}">
                                    {{ ucfirst($approval->risk_level) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center">
                            <p class="text-xs text-green-600">&#10003; All clear</p>
                        </div>
                    @endforelse
                </div>
                @if(count($this->pendingApprovals) > 0)
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800">
                    <a href="{{ route("governance.approvals") }}" class="text-xs text-brand-purple-mid hover:underline">Review all &rarr;</a>
                </div>
                @endif
            </div>

            {{-- Security Events --}}
            @if(count($this->recentSecurityEvents) > 0)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-red-200 dark:border-red-900">
                <div class="flex items-center gap-2 px-5 py-4 border-b border-red-200 dark:border-red-900">
                    <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                    <h3 class="font-semibold text-red-700 text-sm">Security Alerts</h3>
                </div>
                @foreach($this->recentSecurityEvents as $event)
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <p class="text-xs font-medium text-gray-900 dark:text-white">{{ $event->title }}</p>
                        <p class="text-xs text-gray-500">{{ $event->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800">
                    <a href="{{ route("security.center") }}" class="text-xs text-red-600 hover:underline">Security center &rarr;</a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
