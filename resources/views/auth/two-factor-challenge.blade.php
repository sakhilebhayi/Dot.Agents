<x-guest-layout title="Two-factor sign in">
    <div x-data="{ recovery: false }">
        <h1 x-text="recovery ? 'Use a recovery code' : 'Enter your code'">Enter your code</h1>
        <p class="auth-lede" x-text="recovery ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.'">Enter the code from your authenticator app.</p>

        <x-auth.problems />

        <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf
            <div x-show="! recovery">
                <x-auth.field name="code" label="Code" inputmode="numeric" autofocus x-ref="code" autocomplete="one-time-code" />
            </div>
            <div x-cloak x-show="recovery">
                <x-auth.field name="recovery_code" label="Recovery code" x-ref="recovery_code" autocomplete="one-time-code" />
            </div>
            <button type="submit" class="button solid">Sign in</button>
        </form>

        <p class="auth-more">
            <button type="button" class="link" x-show="! recovery" x-on:click="recovery = true; $nextTick(() => $refs.recovery_code.focus())">Use a recovery code</button>
            <button type="button" class="link" x-cloak x-show="recovery" x-on:click="recovery = false; $nextTick(() => $refs.code.focus())">Use your authenticator code</button>
        </p>
    </div>
</x-guest-layout>
