@php $current = $this->currentSubscription; @endphp

<x-sig.page title="Choose your plan" sub="Compare the plans and pick the one for this organization. Payment goes through Stripe.">
    <x-slot:actions>
        <a class="btn" href="{{ route('billing.index') }}">Manage current subscription</a>
    </x-slot:actions>

    <div class="loading-bar" wire:loading role="status" aria-label="Loading"></div>

    <div class="plans">
        @forelse($this->plans as $plan)
            <article class="plan" wire:key="plan-{{ $plan->id }}">
                <div class="plan-top">
                    <h2>{{ $plan->name }}</h2>
                    @if($plan->is_featured)<span class="chip">Recommended</span>@endif
                </div>

                <div>
                    <p class="plan-price"><b>${{ number_format((float) $plan->price, 0) }}</b> a month</p>
                    @if($plan->yearly_price)
                        <p class="plan-note">or <span class="num">${{ number_format((float) $plan->yearly_price, 0) }}</span> a year</p>
                    @endif
                </div>

                @if($plan->description)<p class="plan-note">{{ $plan->description }}</p>@endif

                <ul class="plan-features">
                    @foreach(($plan->features ?? []) as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>

                @if($current?->plan_id === $plan->id)
                    <div class="plan-current"><span class="lamp clear"><i aria-hidden="true"></i>Current plan</span></div>
                @else
                    <form method="POST" action="{{ route('billing.checkout', $plan) }}">
                        @csrf
                        <button type="submit" @class(['btn', 'primary' => $plan->is_featured])>
                            {{ $current ? ($plan->price > ($current->plan?->price ?? 0) ? 'Upgrade to ' : 'Switch to ').$plan->name : 'Get started' }}
                        </button>
                    </form>
                @endif
            </article>
        @empty
            <div class="empty">No plans are currently available.</div>
        @endforelse
    </div>
</x-sig.page>
