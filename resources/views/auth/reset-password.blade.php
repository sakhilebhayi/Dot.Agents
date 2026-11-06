<x-guest-layout title="Set a new password">
    <h1>Set a new password</h1>

    <x-auth.problems />

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-auth.field name="email" label="Work email" type="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        <x-auth.field name="password" label="New password" type="password" required autocomplete="new-password" />
        <x-auth.field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <button type="submit" class="button solid">Save the password</button>
    </form>
</x-guest-layout>
