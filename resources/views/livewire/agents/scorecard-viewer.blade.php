<div class="space-y-6">
    {{-- Agent Header --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-700 to-purple-500 flex items-center justify-center text-white font-bold text-lg">
                    {{ substr($this->deployment->agent?->name ?? 'AI', 0, 2) }}
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->deployment->display_name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->deployment->agent?->agentDepartment?->name }} · {{ $this->deployment->agent?->name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <select wire:model.live="period"
                    class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                    <option value="7d">Last 7 days</option>
                    <option value="30d" selected>Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                </select>
                <button wire:click="recalculate" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-xl transition-colors disabled:opacity-50">
                    <span wire:loading.remove>Recalculate</span>
                    <span wire:loading>Calculating...</span>
                </button>
            </div>
        </div>
    </div>

    @if($this->scorecard)
        @php
            $overall = round($this->scorecard->overall_health_score ?? 0);
            $statusInfo = match(true) {
                $overall >= 90 => ['label' => 'Excellent', 'color' => 'emerald'],
                $overall >= 80 => ['label' => 'Good', 'color' => 'green'],
                $overall >= 70 => ['label' => 'Fair', 'color' => 'yellow'],
                $overall >= 60 => ['label' => 'Needs Attention', 'color' => 'orange'],
                default => ['label' => 'Critical', 'color' => 'red'],
            };
            $dimensions = [
                'Accuracy' => $this->scorecard->accuracy_score,
                'Compliance' => $this->scorecard->compliance_score,
                'Trustworthiness' => $this->scorecard->trustworthiness_score,
                'Productivity' => $this->scorecard->productivity_score,
                'Reliability' => $this->scorecard->reliability_score,
                'Cost Savings' => $this->scorecard->cost_savings_score,
                'Revenue Impact' => $this->scorecard->revenue_impact_score,
                'Risk Impact' => $this->scorecard->risk_impact_score,
                'User Satisfaction' => $this->scorecard->user_satisfaction_score,
                'Learning Rate' => $this->scorecard->learning_rate_score,
            ];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Overall Score --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 text-center">
                <div class="w-28 h-28 rounded-full border-8 border-{{ $statusInfo['color'] }}-500 flex items-center justify-center mx-auto mb-3">
                    <div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $overall }}</div>
                        <div class="text-xs text-gray-400">/100</div>
                    </div>
                </div>
                <h3 class="font-bold text-gray-900 dark:text-white">Overall Health</h3>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-{{ $statusInfo['color'] }}-100 text-{{ $statusInfo['color'] }}-700 dark:bg-{{ $statusInfo['color'] }}-900/30 dark:text-{{ $statusInfo['color'] }}-400 mt-2">
                    {{ $statusInfo['label'] }}
                </span>
                <p class="text-xs text-gray-400 mt-3">{{ $this->scorecard->period_start?->format('M j') }} – {{ $this->scorecard->period_end?->format('M j, Y') }}</p>
            </div>

            {{-- Dimension Scores --}}
            <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Performance Dimensions</h3>
                <div class="space-y-3">
                    @foreach($dimensions as $label => $score)
                        @php $val = round($score ?? 0); @endphp
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600 dark:text-gray-400">{{ $label }}</span>
                                <span class="font-semibold {{ $val >= 80 ? 'text-emerald-600' : ($val >= 60 ? 'text-yellow-600' : 'text-red-600') }}">{{ $val }}%</span>
                            </div>
                            <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $val >= 80 ? 'bg-emerald-500' : ($val >= 60 ? 'bg-yellow-500' : 'bg-red-500') }} transition-all"
                                    style="width: {{ $val }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Activity Metrics --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach([
                ['Total Tasks', number_format(($this->scorecard->tasks_completed ?? 0) + ($this->scorecard->tasks_failed ?? 0)), 'blue'],
                ['Completed', number_format($this->scorecard->tasks_completed ?? 0), 'emerald'],
                ['High-Risk Decisions', number_format($this->scorecard->hallucinations_detected ?? 0), 'orange'],
                ['Est. Savings', '$' . number_format($this->scorecard->estimated_savings ?? 0, 0), 'purple'],
            ] as [$label, $value, $color])
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <p class="text-2xl font-bold text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $value }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No scorecard data yet for this period.</p>
            <button wire:click="recalculate"
                class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-xl transition-colors">
                Generate Scorecard
            </button>
        </div>
    @endif

    {{-- History --}}
    @if($this->history->isNotEmpty())
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Historical Scores</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                        <th class="text-left px-5 py-2.5 text-gray-500 dark:text-gray-400 font-medium">Period</th>
                        <th class="text-center px-4 py-2.5 text-gray-500 dark:text-gray-400 font-medium">Health</th>
                        <th class="text-center px-4 py-2.5 text-gray-500 dark:text-gray-400 font-medium">Accuracy</th>
                        <th class="text-center px-4 py-2.5 text-gray-500 dark:text-gray-400 font-medium">Compliance</th>
                        <th class="text-center px-4 py-2.5 text-gray-500 dark:text-gray-400 font-medium">Tasks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->history as $sc)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                            <td class="px-5 py-2.5 text-gray-600 dark:text-gray-400">{{ $sc->period_start?->format('M j') }} – {{ $sc->period_end?->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5 text-center font-bold {{ $sc->overall_health_score >= 80 ? 'text-emerald-600' : ($sc->overall_health_score >= 60 ? 'text-yellow-600' : 'text-red-600') }}">{{ round($sc->overall_health_score ?? 0) }}%</td>
                            <td class="px-4 py-2.5 text-center text-gray-600 dark:text-gray-400">{{ round($sc->accuracy_score ?? 0) }}%</td>
                            <td class="px-4 py-2.5 text-center text-gray-600 dark:text-gray-400">{{ round($sc->compliance_score ?? 0) }}%</td>
                            <td class="px-4 py-2.5 text-center text-gray-600 dark:text-gray-400">{{ number_format($sc->total_tasks ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
