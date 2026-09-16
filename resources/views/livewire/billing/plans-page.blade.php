<div class="space-y-6">
    {{-- Loading indicator --}}
    <div wire:loading class="fixed top-0 inset-x-0 h-0.5 bg-purple-600 z-50 animate-pulse" role="status" aria-label="Loading"></div>

    {{-- Header --}}
    <div class="text-center py-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Choose Your Plan</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Scale your AI workforce with flexible pricing.</p>
    </div>

    {{-- Plans Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
        @forelse($this->plans as $plan)
            <div class="bg-white dark:bg-gray-900 rounded-2xl border {{ $plan->is_featured ? 'border-purple-400 ring-2 ring-purple-200 dark:ring-purple-800' : 'border-gray-200 dark:border-gray-700' }} p-6 flex flex-col relative">
                @if($plan->is_featured)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                        Most Popular
                    </span>
                @endif

                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ $plan->name }}</p>
                    <div class="mt-2 flex items-end gap-1">
                        <span class="text-3xl font-bold text-gray-900 dark:text-white">${{ number_format((float) $plan->price, 0) }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 mb-1">/mo</span>
                    </div>
                    @if($plan->yearly_price)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">or ${{ number_format((float) $plan->yearly_price, 0) }}/year</p>
                    @endif
                    @if($plan->description)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $plan->description }}</p>
                    @endif
                </div>

                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300 flex-1 mb-6">
                    @foreach(($plan->features ?? []) as $feature)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>

                @if($this->currentSubscription?->plan_id === $plan->id)
                    <div class="w-full py-2 text-center text-sm font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 rounded-xl">
                        Current Plan
                    </div>
                @else
                    <form method="POST" action="{{ route('billing.checkout', $plan) }}">
                        @csrf
                        <button type="submit" class="w-full py-2 text-sm font-medium rounded-xl transition-colors {{ $plan->is_featured ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                            {{ $this->currentSubscription && $plan->price > ($this->currentSubscription->plan?->price ?? 0) ? 'Upgrade to '.$plan->name : 'Get Started' }}
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="md:col-span-3 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">No plans are currently available.</p>
            </div>
        @endforelse
    </div>

    {{-- Footer --}}
    <div class="max-w-2xl mx-auto pt-6">
        <p class="text-xs text-center text-gray-500 dark:text-gray-400">
            <a href="{{ route('billing.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Manage current subscription &rarr;</a>
        </p>
    </div>
</div>
