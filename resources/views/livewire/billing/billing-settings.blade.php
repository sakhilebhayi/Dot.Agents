<x-sig.page title="Billing settings" sub="Your subscription, payment method and invoices." narrow>
    <div class="grid stack">
        <section class="card" aria-label="Current subscription">
            <div class="card-head">
                <h2>Current subscription</h2>
                <a class="btn small" href="{{ route('billing.plans') }}" wire:navigate>Change plan</a>
            </div>
            @if($this->subscription)
                <div class="row long">
                    <div class="row-main">
                        <b>{{ $this->subscription->plan?->name ?? 'Unknown plan' }}, ${{ number_format($this->subscription->amount, 2) }}/{{ $this->subscription->billing_cycle === 'annual' ? 'yr' : 'mo' }}</b>
                        <span>Renews on {{ $this->subscription->current_period_end?->format('F j, Y') }}</span>
                    </div>
                    <x-sig.lamp :state="$this->subscription->status" />
                </div>
            @else
                <div class="empty">
                    <b>This organization doesn't have an active subscription yet.</b>
                    <a class="btn" href="{{ route('billing.plans') }}" wire:navigate>Choose a plan to get started</a>
                </div>
            @endif
        </section>

        <section class="card" aria-label="Payment method">
            <div class="card-head">
                <h2>Payment method</h2>
                <form method="POST" action="{{ route('billing.portal') }}">
                    @csrf
                    <button type="submit" class="btn small">Manage in Stripe portal</button>
                </form>
            </div>
            <div class="row long">
                <div class="row-main">
                    @if($this->organization?->stripe_customer_id)
                        <b>Connected to Stripe</b>
                        <span>Stripe holds your payment method. We never store card details.</span>
                    @else
                        <b>No payment method on file</b>
                        <span>Add a payment method in the Stripe billing portal to activate billing for this organization.</span>
                    @endif
                </div>
            </div>
        </section>

        <section class="card" aria-label="Recent invoices">
            <div class="card-head">
                <h2>Recent invoices</h2>
                <form method="POST" action="{{ route('billing.portal') }}">
                    @csrf
                    <button type="submit" class="btn small">View all</button>
                </form>
            </div>
            @if($this->invoices->isNotEmpty())
                <div class="table-scroll">
                    <table class="table wide">
                        <thead>
                            <tr><th>Date</th><th>Invoice</th><th class="right">Amount</th><th>Status</th><th>PDF</th></tr>
                        </thead>
                        <tbody>
                            @foreach($this->invoices as $invoice)
                                <tr wire:key="invoice-{{ $invoice->id }}">
                                    <td class="cell-time">{{ $invoice->invoice_date?->format('M j, Y') }}</td>
                                    <td class="num">{{ $invoice->invoice_number }}</td>
                                    <td class="right num">${{ number_format($invoice->total, 2) }}</td>
                                    <td><span @class(['chip', 'clear' => $invoice->isPaid(), 'hold' => ! $invoice->isPaid()])>{{ ucfirst($invoice->status) }}</span></td>
                                    <td>
                                        @if($invoice->pdf_url)
                                            <a class="btn small" href="{{ $invoice->pdf_url }}" target="_blank" rel="noopener">Download</a>
                                        @else
                                            <span class="muted">Not available yet</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="card-body muted">Full invoice history is available in the Stripe billing portal.</p>
            @else
                <div class="empty"><b>No invoices yet.</b> They will appear here once your first billing cycle completes.</div>
            @endif
        </section>

        @if($this->subscription && $this->subscription->status !== 'cancelled')
            <section class="card" aria-label="Cancel subscription">
                <div class="card-head"><h2>Cancel subscription</h2></div>
                <div class="row long">
                    <div class="row-main"><span>Cancelling ends your subscription at the end of the current billing period.</span></div>
                    <form method="POST" action="{{ route('billing.portal') }}">
                        @csrf
                        <button type="submit" class="btn small danger">Cancel in Stripe portal</button>
                    </form>
                </div>
            </section>
        @endif
    </div>
</x-sig.page>
