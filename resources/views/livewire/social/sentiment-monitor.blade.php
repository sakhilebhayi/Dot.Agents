<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sentiment Monitor</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Real-time brand sentiment and reputation health</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach(['1h' => '1h', '24h' => '24h', '7d' => '7 Days', '30d' => '30 Days'] as $key => $label)
                <button wire:click="setTimeframe('{{ $key }}')"
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $timeframe === $key ? 'bg-brand-purple-mid text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Health + Breakdown Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Brand Health --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 flex flex-col items-center justify-center text-center">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Brand Health Score</p>
            <div class="w-28 h-28 rounded-full border-8 {{ $this->brandHealthScore >= 70 ? 'border-green-500' : ($this->brandHealthScore >= 40 ? 'border-yellow-500' : 'border-red-500') }} flex items-center justify-center mb-4">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ round($this->brandHealthScore) }}</span>
            </div>
            <p class="text-sm font-semibold {{ $this->brandHealthScore >= 70 ? 'text-green-600' : ($this->brandHealthScore >= 40 ? 'text-yellow-600' : 'text-red-600') }}">
                @if($this->brandHealthScore >= 70) Excellent
                @elseif($this->brandHealthScore >= 55) Good
                @elseif($this->brandHealthScore >= 40) At Risk
                @else Critical
                @endif
            </p>
            <p class="text-xs text-gray-400 mt-1">Avg sentiment: {{ round($this->averageSentimentScore, 1) }}/100</p>
        </div>

        {{-- Sentiment Breakdown --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Sentiment Distribution</p>
            @php
                $sentimentConfig = [
                    'positive'   => ['color' => 'bg-green-500',  'label' => 'Positive'],
                    'neutral'    => ['color' => 'bg-gray-400',   'label' => 'Neutral'],
                    'concerned'  => ['color' => 'bg-yellow-500', 'label' => 'Concerned'],
                    'frustrated' => ['color' => 'bg-orange-500', 'label' => 'Frustrated'],
                    'angry'      => ['color' => 'bg-red-600',    'label' => 'Angry'],
                ];
                $total = max(1, array_sum($this->sentimentBreakdown));
            @endphp
            <div class="space-y-3">
                @foreach($this->sentimentBreakdown as $sentiment => $count)
                    @php $pct = round($count / $total * 100); $cfg = $sentimentConfig[$sentiment]; @endphp
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-xs text-gray-600 dark:text-gray-400 text-right">{{ $cfg['label'] }}</span>
                        <div class="flex-1 h-3 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            <div class="h-full rounded-full {{ $cfg['color'] }} transition-all duration-500" :style="{ width: '{{ $pct }}%' }"></div>
                        </div>
                        <span class="w-12 text-xs text-gray-600 dark:text-gray-400">{{ number_format($count) }}</span>
                        <span class="w-10 text-xs font-semibold text-gray-900 dark:text-white text-right">{{ $pct }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Unhandled Escalations --}}
    @if($this->unhandledEscalations->count() > 0)
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-red-200 dark:border-red-800 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800">
                <h3 class="font-semibold text-red-700 dark:text-red-400">
                    Unhandled Escalations ({{ $this->unhandledEscalations->count() }})
                </h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($this->unhandledEscalations as $score)
                    <div class="flex items-center gap-4 px-6 py-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $score->socialConversation?->contact_name ?? 'Brand Mention' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $score->summary }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $score->scored_at->diffForHumans() }} · {{ ucfirst($score->sentiment) }}</p>
                        </div>
                        <span class="text-sm font-bold {{ $score->score < 30 ? 'text-red-600' : 'text-orange-600' }}">
                            {{ round($score->score) }}/100
                        </span>
                        <button wire:click="markHandled({{ $score->id }})"
                            wire:loading.attr="disabled"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 disabled:opacity-50 transition">
                            Mark Handled
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-6 py-5 text-center">
            <p class="text-sm font-medium text-green-700 dark:text-green-400">✓ No unhandled escalations</p>
        </div>
    @endif
</div>
