<div class="space-y-6">
    @php
        $statusColors = ['active' => 'emerald', 'paused' => 'yellow', 'configuring' => 'blue', 'decommissioned' => 'gray', 'suspended' => 'red'];
        $statusColor = $statusColors[$this->deployment->status] ?? 'gray';
    @endphp

    {{-- Header --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-700 to-purple-500 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                    {{ substr($this->deployment->agent?->name ?? 'AI', 0, 2) }}
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->deployment->display_name }}</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 dark:bg-{{ $statusColor }}-900/30 dark:text-{{ $statusColor }}-400">
                            {{ ucfirst($this->deployment->status) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $this->deployment->agent?->name }}
                        @if($this->deployment->agent?->agent_type)
                            · {{ ucfirst($this->deployment->agent->agent_type) }}
                        @endif
                        @if($this->deployment->department?->name)
                            · {{ $this->deployment->department->name }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Tab nav --}}
        <nav class="flex gap-1 mt-5 -mb-6 border-t border-gray-100 dark:border-gray-800 pt-4">
            <span class="px-4 py-2 text-sm font-medium border-b-2 border-purple-600 text-purple-600 dark:text-purple-400">
                Overview
            </span>
            @if($this->deployment->status === 'active')
                <a href="{{ route('agents.chat', $this->deployment) }}"
                    class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                    Chat
                </a>
            @else
                <span class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-300 dark:text-gray-700 cursor-not-allowed" title="Chat only available for active deployments">
                    Chat
                </span>
            @endif
            <a href="{{ route('agents.scorecard', $this->deployment) }}"
                class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                Scorecard
            </a>
        </nav>
    </div>

    {{-- Non-active banner --}}
    @if($this->deployment->status !== 'active')
        @php
            $bannerCopy = match($this->deployment->status) {
                'paused' => 'This deployment is paused. It is not processing tasks or chat messages right now, and the figures below reflect its last active state.',
                'decommissioned' => 'This deployment has been decommissioned. It is permanently retired and the figures below are historical only.',
                'suspended' => 'This deployment is suspended pending review. It cannot act until it is reinstated, and the figures below reflect its last active state.',
                default => 'This deployment is not currently active. The figures below reflect its last active state, not live data.',
            };
        @endphp
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl bg-{{ $statusColor }}-50 dark:bg-{{ $statusColor }}-900/20 border border-{{ $statusColor }}-200 dark:border-{{ $statusColor }}-800 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 text-sm">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            <span>{{ $bannerCopy }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Configuration --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Configuration</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Deployment Mode</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ ucfirst($this->deployment->deployment_mode) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Confidence Threshold</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ rtrim(rtrim(number_format((float) $this->deployment->confidence_threshold, 2), '0'), '.') }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Risk Tolerance</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ rtrim(rtrim(number_format((float) $this->deployment->risk_tolerance, 2), '0'), '.') }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Human Approval</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                        @if($this->deployment->requires_human_approval)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Required</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">Not required</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Model Override</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->deployment->model_override ?: 'Platform default' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Custom Instructions</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                        @if($this->deployment->custom_instructions)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">Configured</span>
                        @else
                            <span class="text-gray-400 dark:text-gray-500">Not set</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Deployed</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->deployment->deployed_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Last Active</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->deployment->last_active_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide">Deployed By</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $this->deployment->deployedBy?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Scorecard summary --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 flex flex-col">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Latest Scorecard</h3>
            @if($this->scorecard)
                @php $overall = round((float) ($this->scorecard->overall_health_score ?? 0)); @endphp
                <div class="text-center flex-1 flex flex-col items-center justify-center">
                    <div class="w-24 h-24 rounded-full border-8 {{ $overall >= 80 ? 'border-emerald-500' : ($overall >= 60 ? 'border-yellow-500' : 'border-red-500') }} flex items-center justify-center mb-3">
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $overall }}</div>
                            <div class="text-xs text-gray-400">/100</div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $this->scorecard->period_start?->format('M j') }} – {{ $this->scorecard->period_end?->format('M j, Y') }}
                    </p>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400 text-center py-6">
                    No scorecard data yet.
                </div>
            @endif
            <a href="{{ route('agents.scorecard', $this->deployment) }}"
                class="mt-4 block w-full py-2 text-xs font-medium text-center border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                View Full Scorecard
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Enabled skills --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Enabled Skills</h3>
            </div>
            @if($this->enabledSkills->isNotEmpty())
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->enabledSkills as $assignment)
                        <li class="px-5 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $assignment->skill?->name ?? $assignment->skill?->key ?? 'Unknown skill' }}</p>
                                @if($assignment->skill?->key)
                                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $assignment->skill->key }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">No skills enabled for this deployment.</p>
            @endif
        </div>

        {{-- Recent activity --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Recent Activity</h3>
                @if($this->pendingApprovalsCount > 0)
                    <a href="{{ route('governance.approvals') }}"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 hover:bg-yellow-200 dark:hover:bg-yellow-900/50 transition-colors">
                        {{ $this->pendingApprovalsCount }} pending approval{{ $this->pendingApprovalsCount === 1 ? '' : 's' }}
                    </a>
                @endif
            </div>
            @if($this->recentTasks->isNotEmpty())
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->recentTasks as $task)
                        @php
                            $taskStatusColors = ['completed' => 'emerald', 'failed' => 'red', 'in_progress' => 'blue', 'pending' => 'gray', 'cancelled' => 'gray', 'awaiting_approval' => 'yellow'];
                            $taskColor = $taskStatusColors[$task->status] ?? 'gray';
                        @endphp
                        <li class="px-5 py-3 flex items-center justify-between gap-3">
                            <p class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $task->title }}</p>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $taskColor }}-100 text-{{ $taskColor }}-700 dark:bg-{{ $taskColor }}-900/30 dark:text-{{ $taskColor }}-400">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $task->created_at?->diffForHumans() }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">No tasks recorded yet.</p>
            @endif
        </div>
    </div>
</div>
