<div class="max-w-3xl space-y-6">

    {{-- Current Plan --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Current Subscription</h2>
            <a href="{{ route('billing.plans') }}" wire:navigate
                class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                Change Plan
            </a>
        </div>
        <div class="px-5 py-4">
            @if($this->subscription)
                @php
                    $statusColors = ['active' => 'emerald', 'trialing' => 'blue', 'past_due' => 'yellow', 'cancelled' => 'red', 'paused' => 'gray'];
                    $statusColor = $statusColors[$this->subscription->status] ?? 'gray';
                @endphp
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Active Plan</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white mt-0.5">
                            {{ $this->subscription->plan?->name ?? 'Unknown Plan' }} &mdash;
                            ${{ number_format($this->subscription->amount, 2) }}/{{ $this->subscription->billing_cycle === 'annual' ? 'yr' : 'mo' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Renews on <strong class="text-gray-700 dark:text-gray-300">{{ $this->subscription->current_period_end?->format('F j, Y') }}</strong>
                        </p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 dark:bg-{{ $statusColor }}-900/30 dark:text-{{ $statusColor }}-400">
                        {{ ucfirst(str_replace('_', ' ', $this->subscription->status)) }}
                    </span>
                </div>
            @else
                <div class="text-center py-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">This organization doesn't have an active subscription yet.</p>
                    <a href="{{ route('billing.plans') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-brand-purple-mid dark:text-brand-purple-light hover:text-brand-purple dark:hover:text-brand-purple-light font-medium">
                        Choose a plan to get started &rarr;
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Payment Method --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Payment Method</h2>
            <form method="POST" action="{{ route('billing.portal') }}">
                @csrf
                <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">Manage via Portal</button>
            </form>
        </div>
        <div class="px-5 py-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-brand-purple-pale dark:bg-brand-purple-deeper/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-brand-purple-mid dark:text-brand-purple-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V7a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                @if($this->organization?->stripe_customer_id)
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Connected to Stripe</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Your payment method is managed securely via the Stripe billing portal &mdash; we never store card details.</p>
                @else
                    <p class="text-sm font-medium text-gray-900 dark:text-white">No payment method on file</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Add a payment method via the Stripe billing portal to activate billing for this organization.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Invoices --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Invoices</h2>
            <form method="POST" action="{{ route('billing.portal') }}">
                @csrf
                <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">View All</button>
            </form>
        </div>
        <div>
            @if($this->invoices->isNotEmpty())
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-400 font-medium">Date</th>
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-400 font-medium">Invoice #</th>
                            <th class="text-right px-5 py-3 text-xs text-gray-500 dark:text-gray-400 font-medium">Amount</th>
                            <th class="text-center px-5 py-3 text-xs text-gray-500 dark:text-gray-400 font-medium">Status</th>
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-400 font-medium">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($this->invoices as $invoice)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-400">{{ $invoice->invoice_date?->format('M j, Y') }}</td>
                                <td class="px-5 py-3 text-xs font-mono text-gray-600 dark:text-gray-400">{{ $invoice->invoice_number }}</td>
                                <td class="px-5 py-3 text-xs text-right font-semibold text-gray-900 dark:text-white">${{ number_format($invoice->total, 2) }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $invoice->isPaid() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if($invoice->pdf_url)
                                        <a href="{{ $invoice->pdf_url }}" target="_blank" rel="noopener" class="text-xs text-brand-purple-mid dark:text-brand-purple-light hover:underline">Download</a>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-600 cursor-not-allowed" title="PDF not available yet">Download</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-gray-500 dark:text-gray-400 px-5 py-3">Full invoice history is available via the Stripe billing portal.</p>
            @else
                <div class="text-center py-8 px-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No invoices yet &mdash; invoices will appear here once your first billing cycle completes.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Cancel --}}
    @if($this->subscription && $this->subscription->status !== 'cancelled')
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-red-100 dark:border-red-900/30 overflow-hidden">
            <div class="px-5 py-4 border-b border-red-100 dark:border-red-900/30">
                <h2 class="text-sm font-semibold text-red-700 dark:text-red-400">Cancel Subscription</h2>
            </div>
            <div class="px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
                <p class="text-sm text-gray-600 dark:text-gray-400">Cancelling will downgrade your account at the end of the current billing period.</p>
                <form method="POST" action="{{ route('billing.portal') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white transition-colors">Cancel via Portal</button>
                </form>
            </div>
        </div>
    @endif

</div>
