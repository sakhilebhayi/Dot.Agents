<x-guest-layout title="Confirm your password">
    <h1>Confirm your password</h1>
    <p class="auth-lede">This part needs your password again before it continues.</p>

    <x-auth.problems />

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <x-auth.field name="password" label="Password" type="password" required autofocus autocomplete="current-password" />
        <button type="submit" class="button solid">Continue</button>
    </form>
</x-guest-layout>
