@php
    $usage = $this->currentMonthUsage;
    $current = $this->subscription;
@endphp

<x-sig.page title="Billing" sub="Your plan, this month's usage and your invoices.">
    <div class="loading-bar" wire:loading role="status" aria-label="Loading"></div>

    <div class="stats">
        <div class="stat"><span>Total spend this month</span><b>${{ number_format($usage['total_cost'], 2) }}</b></div>
        <div class="stat"><span>Tokens used</span><b>{{ number_format($usage['tokens']) }}</b></div>
        <div class="stat"><span>Tasks completed</span><b>{{ number_format($usage['tasks']) }}</b></div>
        <div class="stat"><span>API calls</span><b>{{ number_format($usage['api_calls']) }}</b></div>
    </div>

    <div class="grid stack">
        <section class="card" aria-label="Current plan">
            <div class="card-head">
                <h2>Current plan</h2>
                @if($current)<x-sig.lamp :state="$current->status" />@endif
            </div>
            @if($current)
                <div class="card-body current-plan">
                    <div>
                        <h3>{{ $current->plan?->name }}</h3>
                        <p class="muted"><span class="num">${{ number_format($current->plan?->price, 0) }}</span> a month{{ $current->current_period_end ? ', renews '.$current->current_period_end->format('M j, Y') : '' }}</p>
                    </div>
                    <div class="plan-tags">
                        <span class="chip"><span class="num">{{ ($current->plan?->max_agents ?? 0) < 0 ? 'Unlimited' : $current->plan?->max_agents }}</span> agents</span>
                        <span class="chip"><span class="num">{{ number_format(($current->plan?->monthly_token_quota ?? 0) / 1000) }}K</span> tokens a month</span>
                    </div>
                </div>
            @else
                <div class="empty"><b>No active subscription</b>Choose a plan to get started.</div>
            @endif
        </section>

        <section class="card" aria-label="Available plans">
            <div class="card-head"><h2>Available plans</h2></div>
            @if($this->plans->isEmpty())
                <div class="empty">No plans are currently available.</div>
            @else
                <div class="table-scroll" tabindex="0" role="region" aria-label="Table, scrolls sideways">
                    <table class="table wide">
                        <thead>
                            <tr><th>Plan</th><th class="right">A month</th><th class="right">Agents</th><th class="right">Users</th><th class="right">Tokens a month</th><th class="right">Workflows</th><th aria-label="Change plan"></th></tr>
                        </thead>
                        <tbody>
                            @foreach($this->plans as $plan)
                                <tr wire:key="plan-{{ $plan->id }}">
                                    <td>{{ $plan->name }} @if($plan->is_featured)<span class="chip">Recommended</span>@endif</td>
                                    <td class="right num">${{ number_format($plan->price, 0) }}</td>
                                    <td class="right num">{{ $plan->max_agents < 0 ? 'Unlimited' : $plan->max_agents }}</td>
                                    <td class="right num">{{ $plan->max_users < 0 ? 'Unlimited' : $plan->max_users }}</td>
                                    <td class="right num">{{ number_format(($plan->monthly_token_quota ?? 0) / 1000) }}K</td>
                                    <td class="right num">{{ $plan->max_workflows < 0 ? 'Unlimited' : $plan->max_workflows }}</td>
                                    <td class="right">
                                        @if($current?->plan_id === $plan->id)
                                            <span class="chip clear">Current plan</span>
                                        @else
                                            <a href="{{ route('billing.plans') }}" wire:navigate @class(['btn', 'small', 'primary' => $plan->is_featured])>{{ $current ? ($plan->price > ($current->plan?->price ?? 0) ? 'Upgrade' : 'Switch') : 'Choose' }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if($this->invoices->isNotEmpty())
            <section class="card" aria-label="Invoice history">
                <div class="card-head"><h2>Invoice history</h2></div>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Table, scrolls sideways">
                    <table class="table wide">
                        <thead>
                            <tr><th>Invoice</th><th>Date</th><th class="right">Amount</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach($this->invoices as $invoice)
                                <tr wire:key="invoice-{{ $invoice->id }}">
                                    <td class="num">{{ $invoice->invoice_number }}</td>
                                    <td class="cell-time">{{ $invoice->invoice_date?->format('M j, Y') }}</td>
                                    <td class="right num">${{ number_format($invoice->total, 2) }}</td>
                                    <td><span @class(['chip', 'clear' => $invoice->isPaid(), 'hold' => ! $invoice->isPaid()])>{{ ucfirst($invoice->status) }}</span></td>
                                    <td>
                                        @if($invoice->pdf_url)
                                            <a class="btn small" href="{{ $invoice->pdf_url }}" target="_blank" rel="noopener">Download PDF</a>
                                        @else
                                            <span class="muted">PDF not available yet</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-sig.page>
