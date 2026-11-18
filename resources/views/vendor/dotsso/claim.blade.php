{{-- R-54: the claim page, in The Signal Box. Shown when an account here already uses the email address of the InfoDot
     sign-in. Nothing on the account is replaced; the code proves the address. Variables come from the shared package. --}}
<x-guest-layout title="Confirm it is you">
    <h1>Confirm it is you</h1>
    <p class="auth-lede">An account here already uses <strong class="claim-email">{{ $maskedEmail }}</strong>. We sent a {{ $codeLength }}-digit code to that address. Enter it to connect the two. Your agents, runs and businesses stay as they are.</p>

    @if (session('dotsso_status'))
        <p class="auth-note" role="status">{{ session('dotsso_status') }}</p>
    @endif

    <form method="POST" action="{{ $verifyUrl }}" novalidate>
        @csrf
        <div class="field">
            <label for="code">Code from the email</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                   maxlength="{{ $codeLength }}" required autofocus class="claim-code"
                   aria-describedby="code-help code-error" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
            <p id="code-help" class="claim-help">It works for {{ $minutes }} minutes. You have {{ $maxAttempts }} tries.</p>
            <p id="code-error" class="claim-error" role="alert">{{ $errors->first('code') }}</p>
        </div>
        <button type="submit" class="button solid">Confirm and continue</button>
    </form>

    <div class="claim-aside">
        <form method="POST" action="{{ $resendUrl }}">@csrf<button type="submit" class="button claim-link">Send a new code</button></form>
        <form method="POST" action="{{ $cancelUrl }}">@csrf<button type="submit" class="button claim-link">Cancel</button></form>
    </div>
</x-guest-layout>
