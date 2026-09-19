<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Lead Pipeline</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Qualify, score, and convert your social leads</p>
        </div>
        @if($this->hotLeadCount > 0)
            <span class="px-4 py-2 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-sm font-semibold">
                🔥 {{ $this->hotLeadCount }} Hot Lead{{ $this->hotLeadCount > 1 ? 's' : '' }}
            </span>
        @endif
    </div>

    {{-- Pipeline Funnel --}}
    <div class="grid grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach($this->pipeline as $stage => $count)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($count) }}</p>
                <p class="text-xs text-gray-500 capitalize mt-1">{{ str_replace('_', ' ', $stage) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="intentLevel"
            class="text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-purple-light">
            <option value="all">All Intent Levels</option>
            <option value="high_intent">High Intent</option>
            <option value="ready_to_buy">Ready to Buy</option>
            <option value="considering">Considering</option>
            <option value="interested">Interested</option>
            <option value="browsing">Browsing</option>
        </select>
        <select wire:model.live="platform"
            class="text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-purple-light">
            <option value="all">All Platforms</option>
            <option value="facebook">Facebook</option>
            <option value="instagram">Instagram</option>
            <option value="linkedin">LinkedIn</option>
            <option value="x">X</option>
            <option value="whatsapp">WhatsApp</option>
        </select>
        <select wire:model.live="sortBy"
            class="text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-purple-light">
            <option value="lead_score">Sort by Lead Score</option>
            <option value="intent_score">Sort by Intent Score</option>
            <option value="last_touch_at">Sort by Last Touch</option>
        </select>
    </div>

    {{-- Lead Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/50">
                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Platform</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Intent</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Lead Score</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Recommended</th>
                    <th class="px-6 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->leads as $lead)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                        <td class="px-6 py-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->contact_name ?? $lead->contact_handle }}</p>
                                @if($lead->company)
                                    <p class="text-xs text-gray-500">{{ $lead->company }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-medium capitalize">{{ $lead->platform }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $intentColors = ['high_intent' => 'bg-green-100 text-green-700', 'ready_to_buy' => 'bg-teal-100 text-teal-700', 'considering' => 'bg-blue-100 text-blue-700', 'interested' => 'bg-brand-purple-pale text-brand-purple', 'browsing' => 'bg-gray-100 text-gray-500'];
                                $color = $intentColors[$lead->intent_level] ?? 'bg-gray-100 text-gray-500';
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $color }}">
                                {{ ucfirst(str_replace('_', ' ', $lead->intent_level)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <div class="w-16 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                    <div class="h-full rounded-full {{ $lead->lead_score >= 80 ? 'bg-green-500' : ($lead->lead_score >= 50 ? 'bg-yellow-500' : 'bg-gray-400') }}"
                                        style="width: {{ $lead->lead_score }}%"></div>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ round($lead->lead_score) }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                {{ match($lead->status) {
                                    'converted' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'qualified' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'new' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                    default => 'bg-gray-100 text-gray-600',
                                } }}">
                                {{ ucfirst($lead->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @foreach(array_slice($lead->recommended_actions ?? [], 0, 2) as $action)
                                <span class="inline-block mr-1 px-2 py-0.5 rounded bg-brand-purple-pale dark:bg-brand-purple-deeper/30 text-brand-purple dark:text-brand-purple-light text-xs">
                                    {{ str_replace('_', ' ', $action) }}
                                </span>
                            @endforeach
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($lead->status === 'new')
                                <button wire:click="qualify({{ $lead->id }})"
                                    wire:loading.attr="disabled"
                                    class="text-xs font-medium text-brand-purple-mid hover:text-brand-purple-dark dark:text-brand-purple-light">
                                    Qualify →
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">
                            No leads yet. Connect your social accounts and deploy a Lead Generation Agent.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
            {{ $this->leads->links() }}
        </div>
    </div>
</div>
