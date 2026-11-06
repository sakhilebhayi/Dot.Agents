<x-guest-layout title="Create an account">
    <h1>Create an account</h1>

    <x-auth.problems />

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <x-auth.field name="name" label="Full name" :value="old('name')" required autofocus autocomplete="name" />
        <x-auth.field name="email" label="Work email" type="email" :value="old('email')" required autocomplete="username" />
        <x-auth.field name="password" label="Password" type="password" required autocomplete="new-password" />
        <x-auth.field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

        @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
            <label class="check">
                <input name="terms" type="checkbox" required>
                <span>I agree to the <a href="{{ route('terms.show') }}" target="_blank">terms of service</a> and <a href="{{ route('policy.show') }}" target="_blank">privacy policy</a></span>
            </label>
        @endif

        <button type="submit" class="button solid">Create account</button>
    </form>

    <p class="auth-more">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</x-guest-layout>
