<x-guest-layout title="Reset your password">
    <h1>Reset your password</h1>
    <p class="auth-lede">Enter your work email and we will send you a link.</p>

    @session('status')
        <p class="auth-note" role="status">{{ $value }}</p>
    @endsession

    <x-auth.problems />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-auth.field name="email" label="Work email" type="email" :value="old('email')" required autofocus autocomplete="username" />
        <button type="submit" class="button solid">Send the link</button>
    </form>

    <p class="auth-more"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-guest-layout>
