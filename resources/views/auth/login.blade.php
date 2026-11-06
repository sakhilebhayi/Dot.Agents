<x-guest-layout title="Sign in">
    <h1>Sign in</h1>

    <x-auth.problems />

    @session('status')
        <p class="auth-note" role="status">{{ $value }}</p>
    @endsession

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-auth.field name="email" label="Work email" type="email" :value="old('email')" required autofocus autocomplete="username" />
        <x-auth.field name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="auth-row">
            <label class="check"><input name="remember" type="checkbox"> Keep me signed in</label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Forgot your password?</a>
            @endif
        </div>
        <button type="submit" class="button solid">Sign in</button>
    </form>

    @if (Route::has('register'))
        <p class="auth-more">New here? <a href="{{ route('register') }}">Create an account</a></p>
    @endif
</x-guest-layout>
