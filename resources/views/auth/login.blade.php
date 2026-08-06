<x-guest-layout>
    <div>
        <h1 class="text-2xl font-bold text-[#111111] font-display mb-1">Welcome back</h1>
        <p class="text-sm text-[#909088] mb-8">Sign in to your workforce platform</p>

        <x-validation-errors class="mb-5 da-alert da-alert-critical" />

        @session('status')
            <div class="mb-5 da-alert da-alert-success">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="Work Email" />
                <x-input id="email" type="email" name="email"
                         :value="old('email')"
                         required autofocus autocomplete="username"
                         placeholder="you@company.com" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <x-label for="password" value="Password" />
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-xs text-brand-purple hover:text-brand-purple-mid transition-colors">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <x-input id="password" type="password" name="password"
                         required autocomplete="current-password"
                         placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" />
            </div>

            <div class="flex items-center gap-2">
                <input id="remember_me" name="remember" type="checkbox"
                       class="w-4 h-4 rounded border-[#d0d0c8] text-brand-purple
                              focus:ring-brand-purple/20 focus:ring-2 cursor-pointer">
                <label for="remember_me" class="text-sm text-[#555550] cursor-pointer select-none">
                    Keep me signed in
                </label>
            </div>

            <button type="submit" class="da-btn-primary w-full py-2.5 text-sm font-semibold">
                Sign In
            </button>
        </form>

        @if (Route::has('register'))
            <p class="mt-6 text-center text-sm text-[#909088]">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-brand-purple font-medium hover:text-brand-purple-mid transition-colors">
                    Create one
                </a>
            </p>
        @endif
    </div>
</x-guest-layout>
