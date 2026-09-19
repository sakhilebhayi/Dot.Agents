<div class="space-y-6">
    {{-- Header & Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Decision Logs</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">All AI agent decisions with governance scores and delusion risk analysis.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach(['24h' => '24h', '7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days'] as $key => $label)
            <button wire:click="$set('timeframe', '{{ $key }}')"
                wire:loading.attr="disabled"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $timeframe === $key ? 'bg-brand-purple-mid text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Filters row --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-wrap gap-3">
        <select wire:model.live="filterRisk"
            class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <option value="">All Risk Levels</option>
            <option value="high">High Risk (≥70)</option>
            <option value="medium">Medium Risk (40-69)</option>
            <option value="low">Low Risk (&lt;40)</option>
        </select>
        <select wire:model.live="filterDeployment"
            class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <option value="">All Agents</option>
            @foreach($this->deployments as $dep)
                <option value="{{ $dep->id }}">{{ $dep->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterReviewRequired"
            class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <option value="">All Decisions</option>
            <option value="1">Requires Human Review</option>
            <option value="0">Auto-processed</option>
        </select>
    </div>

    {{-- Detail Modal --}}
    @if($viewingId && $this->viewingDecision)
    @php $d = $this->viewingDecision; @endphp
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeDetail">
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Decision Detail</h3>
                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $d->title }}</h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach([['Confidence', number_format($d->confidence_score, 1).'%', $d->confidence_score >= 80 ? 'green' : ($d->confidence_score >= 60 ? 'yellow' : 'red')], ['Risk Score', number_format($d->risk_score, 1), $d->risk_score >= 70 ? 'red' : ($d->risk_score >= 40 ? 'yellow' : 'green')], ['Delusion Risk', number_format($d->delusion_risk_score, 1), $d->delusion_risk_score >= 60 ? 'red' : ($d->delusion_risk_score >= 30 ? 'yellow' : 'green')], ['Reality Align', number_format($d->reality_alignment_score, 1).'%', $d->reality_alignment_score >= 70 ? 'green' : 'yellow']] as [$label, $val, $color])
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-center">
                        <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
                        <p class="font-bold text-sm text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $val }}</p>
                    </div>
                    @endforeach
                </div>
                @if($d->decision_summary)
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Summary</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $d->decision_summary }}</p>
                </div>
                @endif
                @if($d->reasoning)
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Reasoning</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ is_array($d->reasoning) ? json_encode($d->reasoning, JSON_PRETTY_PRINT) : $d->reasoning }}</p>
                </div>
                @endif
                @if($d->requires_human_review)
                <div class="flex items-center gap-2 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-xl border border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-400 text-xs">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    This decision required human review.
                    {{ $d->human_reviewed ? 'Reviewed — verdict: ' . ucfirst($d->human_verdict ?? 'pending') : 'Not yet reviewed.' }}
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm min-w-[700px]">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Decision</th>
                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Agent</th>
                    <th class="text-center px-4 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Confidence</th>
                    <th class="text-center px-4 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Risk</th>
                    <th class="text-center px-4 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Delusion</th>
                    <th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                    <th class="px-4 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->decisions as $decision)
                @php
                    $risk = $decision->risk_score ?? 0;
                    $riskColor = $risk >= 70 ? 'red' : ($risk >= 40 ? 'yellow' : 'green');
                    $conf = $decision->confidence_score ?? 0;
                    $confColor = $conf >= 80 ? 'green' : ($conf >= 60 ? 'yellow' : 'red');
                    $del = $decision->delusion_risk_score ?? 0;
                    $delColor = $del >= 60 ? 'red' : ($del >= 30 ? 'yellow' : 'green');
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="px-5 py-4">
                        <p class="font-medium text-gray-900 dark:text-white truncate max-w-xs">{{ $decision->title }}</p>
                        @if($decision->requires_human_review)
                            <span class="inline-flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400 mt-0.5">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                Review required
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-gray-600 dark:text-gray-400 text-xs">
                        {{ $decision->deployment?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="text-{{ $confColor }}-600 dark:text-{{ $confColor }}-400 font-medium text-xs">{{ number_format($conf, 0) }}%</span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $riskColor }}-100 text-{{ $riskColor }}-700 dark:bg-{{ $riskColor }}-900/30 dark:text-{{ $riskColor }}-400">
                            {{ number_format($risk, 0) }}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $delColor }}-100 text-{{ $delColor }}-700 dark:bg-{{ $delColor }}-900/30 dark:text-{{ $delColor }}-400">
                            {{ number_format($del, 0) }}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-gray-500 text-xs whitespace-nowrap">{{ $decision->created_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-4">
                        <button wire:click="view({{ $decision->id }})" class="text-xs text-brand-purple-mid hover:text-brand-purple font-medium whitespace-nowrap">View →</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center">
                        <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No decision logs in this period.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($this->decisions->hasPages())
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->decisions->links() }}
        </div>
        @endif
    </div>
</div>
