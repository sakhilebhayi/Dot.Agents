<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Customer Success Command Center</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Social commerce, lead pipeline, reputation, and revenue operations</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('social.accounts') }}"
               class="px-3 py-1.5 text-xs font-medium rounded-lg border border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 hover:bg-yellow-100 dark:hover:bg-yellow-900/40 transition">
                + Connect Account
            </a>
            @foreach(['7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days'] as $key => $label)
                <button wire:click="setTimeframe('{{ $key }}')"
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $timeframe === $key ? 'bg-brand-purple-mid text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Active Leads</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($this->scorecard['active_leads']) }}</p>
            <p class="text-xs text-yellow-600 font-medium mt-1">{{ $this->scorecard['hot_leads'] }} hot 🔥</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Conversion Rate</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $this->scorecard['lead_conversion_rate'] }}%</p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->scorecard['total_conversions'] }} conversions</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Revenue Generated</p>
            <p class="text-3xl font-bold text-green-600">${{ number_format($this->scorecard['total_revenue'], 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->scorecard['upsell_conversions'] }} upsells</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Sentiment Health</p>
            <p class="text-3xl font-bold {{ $this->scorecard['sentiment_health_score'] >= 70 ? 'text-green-600' : ($this->scorecard['sentiment_health_score'] >= 40 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ number_format($this->scorecard['sentiment_health_score'], 1) }}/100
            </p>
            <p class="text-xs text-gray-500 mt-1">Brand health score</p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Avg Response</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                @if($this->scorecard['response_time_avg_seconds'] < 60)
                    {{ round($this->scorecard['response_time_avg_seconds']) }}s
                @else
                    {{ round($this->scorecard['response_time_avg_seconds'] / 60) }}m
                @endif
            </p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->scorecard['connected_accounts'] }} channels</p>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Urgent Conversations --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-semibold text-gray-900 dark:text-white">Needs Attention</h3>
                <a href="{{ route('social.inbox') }}" class="text-xs text-brand-purple-mid dark:text-brand-purple-light hover:underline">Open Inbox →</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->urgentConversations as $conv)
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                        <div class="w-9 h-9 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-sm flex-shrink-0">
                            {{ strtoupper(substr($conv->contact_name ?? '?', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $conv->contact_name ?? $conv->contact_handle }}</p>
                            <p class="text-xs text-gray-500">{{ ucfirst($conv->platform) }} · {{ $conv->last_message_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($conv->sentiment === 'angry')
                                <span class="px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium">Angry</span>
                            @elseif($conv->sentiment === 'frustrated')
                                <span class="px-2 py-0.5 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-xs font-medium">Frustrated</span>
                            @elseif($conv->is_escalated)
                                <span class="px-2 py-0.5 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs font-medium">Escalated</span>
                            @endif
                            @if($conv->priority === 'urgent')
                                <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-xs font-bold">URGENT</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center">
                        <p class="text-gray-500 text-sm">All conversations are under control.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right column --}}
        <div class="space-y-4">
            {{-- Brand Health --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Brand Health</p>
                <div class="flex items-center gap-3">
                    <div class="w-16 h-16 rounded-full border-4 {{ $this->brandHealthScore >= 70 ? 'border-green-500' : ($this->brandHealthScore >= 40 ? 'border-yellow-500' : 'border-red-500') }} flex items-center justify-center">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ round($this->brandHealthScore) }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            @if($this->brandHealthScore >= 70) Healthy
                            @elseif($this->brandHealthScore >= 40) At Risk
                            @else Critical
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">30-day brand score</p>
                    </div>
                </div>
            </div>

            {{-- Hot Leads --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Hot Leads 🔥</h3>
                    <a href="{{ route('social.leads') }}" class="text-xs text-brand-purple-mid hover:underline">View all</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($this->hotLeads as $lead)
                        <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $lead->contact_name ?? $lead->contact_handle }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($lead->platform) }} · {{ ucfirst(str_replace('_', ' ', $lead->intent_level)) }}</p>
                            </div>
                            <span class="text-sm font-bold {{ $lead->lead_score >= 80 ? 'text-green-600' : 'text-yellow-600' }}">{{ round($lead->lead_score) }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-xs text-gray-400">No hot leads yet</div>
                    @endforelse
                </div>
            </div>

            {{-- Pending Post Approvals --}}
            @if($this->pendingPosts->count() > 0)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-yellow-300 dark:border-yellow-700 p-5">
                <p class="text-xs font-bold text-yellow-700 dark:text-yellow-400 uppercase tracking-wide mb-2">
                    {{ $this->pendingPosts->count() }} Post{{ $this->pendingPosts->count() > 1 ? 's' : '' }} Need Approval
                </p>
                <a href="{{ route('social.posts') }}" class="text-sm text-brand-purple-mid hover:underline">Review posts →</a>
            </div>
            @endif
        </div>
    </div>
</div>
