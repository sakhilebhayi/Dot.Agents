<x-guest-layout>
    <div>
        <h1 class="text-2xl font-bold text-[#111111] font-display mb-1">Reset your password</h1>
        <p class="text-sm text-[#909088] mb-8">
            Enter your work email and we'll send you a reset link.
        </p>

        @session('status')
            <div class="mb-5 da-alert da-alert-success">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ $value }}
            </div>
        @endsession

        <x-validation-errors class="mb-5 da-alert da-alert-critical" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="Work Email" />
                <x-input id="email" type="email" name="email"
                         :value="old('email')"
                         required autofocus autocomplete="username"
                         placeholder="you@company.com" />
            </div>

            <button type="submit" class="da-btn-primary w-full py-2.5 text-sm font-semibold">
                Send Reset Link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-[#909088]">
            <a href="{{ route('login') }}" class="text-brand-purple font-medium hover:text-brand-purple-mid transition-colors">
                &larr; Back to sign in
            </a>
        </p>
    </div>
</x-guest-layout>
