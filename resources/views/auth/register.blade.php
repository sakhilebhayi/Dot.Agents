<x-guest-layout>
    <div>
        <h1 class="text-2xl font-bold text-[#111111] font-display mb-1">Create your account</h1>
        <p class="text-sm text-[#909088] mb-8">Set up your organization's workforce platform</p>

        <x-validation-errors class="mb-5 da-alert da-alert-critical" />

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="name" value="Full Name" />
                <x-input id="name" type="text" name="name"
                         :value="old('name')"
                         required autofocus autocomplete="name"
                         placeholder="Jane Smith" />
            </div>

            <div>
                <x-label for="email" value="Work Email" />
                <x-input id="email" type="email" name="email"
                         :value="old('email')"
                         required autocomplete="username"
                         placeholder="you@company.com" />
            </div>

            <div>
                <x-label for="password" value="Password" />
                <x-input id="password" type="password" name="password"
                         required autocomplete="new-password"
                         placeholder="Min. 8 characters" />
            </div>

            <div>
                <x-label for="password_confirmation" value="Confirm Password" />
                <x-input id="password_confirmation" type="password" name="password_confirmation"
                         required autocomplete="new-password"
                         placeholder="Repeat password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="flex items-start gap-2.5">
                    <input id="terms" name="terms" type="checkbox"
                           class="mt-0.5 w-4 h-4 rounded border-[#d0d0c8] text-brand-purple
                                  focus:ring-brand-purple/20 focus:ring-2 cursor-pointer"
                           required>
                    <label for="terms" class="text-sm text-[#555550] cursor-pointer leading-snug">
                        I agree to the
                        <a href="{{ route('terms.show') }}" target="_blank"
                           class="text-brand-purple hover:text-brand-purple-mid transition-colors">Terms of Service</a>
                        and
                        <a href="{{ route('policy.show') }}" target="_blank"
                           class="text-brand-purple hover:text-brand-purple-mid transition-colors">Privacy Policy</a>
                    </label>
                </div>
            @endif

            <button type="submit" class="da-btn-primary w-full py-2.5 text-sm font-semibold">
                Create Account
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-[#909088]">
            Already have an account?
            <a href="{{ route('login') }}" class="text-brand-purple font-medium hover:text-brand-purple-mid transition-colors">
                Sign in
            </a>
        </p>
    </div>
</x-guest-layout>
