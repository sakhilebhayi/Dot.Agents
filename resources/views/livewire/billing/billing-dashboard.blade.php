<div class="space-y-6">
    {{-- Loading indicator --}}
    <div wire:loading class="fixed top-0 inset-x-0 h-0.5 bg-purple-600 z-50 animate-pulse" role="status" aria-label="Loading"></div>

    {{-- Current Plan --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="md:col-span-2 bg-gradient-to-br from-purple-700 to-purple-900 rounded-2xl p-6 text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-purple-200 text-sm font-medium mb-1">Current Plan</p>
                    @if($this->subscription)
                        <h2 class="text-2xl font-bold">{{ $this->subscription->plan?->name }}</h2>
                        <p class="text-purple-200 text-sm mt-1">
                            ${{ number_format($this->subscription->plan?->price, 0) }}/month &middot;
                            Renews {{ $this->subscription->current_period_end?->format('M j, Y') }}
                        </p>
                        <div class="flex gap-3 mt-3">
                            <span class="text-xs bg-white/10 rounded-full px-3 py-1">{{ ($this->subscription->plan?->max_agents ?? 0) < 0 ? 'Unlimited' : $this->subscription->plan?->max_agents }} Agents</span>
                            <span class="text-xs bg-white/10 rounded-full px-3 py-1">{{ number_format(($this->subscription->plan?->monthly_token_quota ?? 0) / 1000) }}K Tokens/mo</span>
                        </div>
                    @else
                        <h2 class="text-2xl font-bold">No Active Subscription</h2>
                        <p class="text-purple-200 text-sm mt-1">Choose a plan to get started</p>
                    @endif
                </div>
                <div class="text-right">
                    @if($this->subscription)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold {{ $this->subscription->isActive() ? 'bg-emerald-400/20 text-emerald-300' : 'bg-red-400/20 text-red-300' }}">
                            {{ ucfirst($this->subscription->status) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Month Spend --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-3">This Month's Usage</p>
            @php $usage = $this->currentMonthUsage; @endphp
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Total Spend</span>
                    <span class="font-bold text-gray-900 dark:text-white">${{ number_format($usage['total_cost'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Tokens Used</span>
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format($usage['tokens']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Tasks Completed</span>
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format($usage['tasks']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">API Calls</span>
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format($usage['api_calls']) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Plan Options --}}
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Available Plans</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach($this->plans as $plan)
                <div class="bg-white dark:bg-gray-900 rounded-2xl border {{ $plan->is_featured ? 'border-purple-400 ring-2 ring-purple-200 dark:ring-purple-800' : 'border-gray-200 dark:border-gray-700' }} p-6 relative">
                    @if($plan->is_featured)
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-yellow-400 text-gray-900 text-xs font-bold px-3 py-1 rounded-full">Most Popular</div>
                    @endif
                    <h4 class="font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h4>
                    <div class="my-3">
                        <span class="text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($plan->price, 0) }}</span>
                        <span class="text-gray-400 text-sm">/month</span>
                    </div>
                    <ul class="space-y-2 mb-5">
                        @foreach([
                            ($plan->max_agents < 0 ? 'Unlimited' : $plan->max_agents) . ' AI Agents',
                            ($plan->max_users < 0 ? 'Unlimited' : $plan->max_users) . ' Users',
                            number_format(($plan->monthly_token_quota ?? 0) / 1000) . 'K Tokens/month',
                            ($plan->max_workflows < 0 ? 'Unlimited' : $plan->max_workflows) . ' Workflows',
                        ] as $feature)
                            <li class="text-xs text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                <span class="text-emerald-500">✓</span> {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    @if($this->subscription?->plan_id === $plan->id)
                        <div class="w-full py-2 text-center text-sm font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-950/20 rounded-xl">
                            Current Plan
                        </div>
                    @else
                        <a href="{{ route('billing.plans') }}" wire:navigate class="block w-full py-2 text-center text-sm font-medium {{ $plan->is_featured ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }} rounded-xl transition-colors">
                            {{ ($this->subscription && $plan->price > ($this->subscription->plan?->price ?? 0)) ? 'Upgrade' : 'Switch' }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Invoice History --}}
    @if($this->invoices->isNotEmpty())
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Invoice History</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                    <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Invoice #</th>
                    <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Date</th>
                    <th class="text-right px-5 py-3 text-xs text-gray-500 font-medium">Amount</th>
                    <th class="text-center px-5 py-3 text-xs text-gray-500 font-medium">Status</th>
                    <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($this->invoices as $invoice)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                        <td class="px-5 py-3 text-xs font-mono text-gray-600 dark:text-gray-400">{{ $invoice->invoice_number }}</td>
                        <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-400">{{ $invoice->invoice_date?->format('M j, Y') }}</td>
                        <td class="px-5 py-3 text-xs text-right font-semibold text-gray-900 dark:text-white">${{ number_format($invoice->total, 2) }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $invoice->isPaid() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            @if($invoice->pdf_url)
                                <a href="{{ $invoice->pdf_url }}" target="_blank" rel="noopener" class="text-xs text-purple-600 dark:text-purple-400 hover:underline">Download PDF</a>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-600 cursor-not-allowed" title="PDF not available yet">Download PDF</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
