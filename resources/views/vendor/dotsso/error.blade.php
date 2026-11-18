{{-- R-54: every sign-in failure ends here, in words, with one next step and no automatic redirect. --}}
<x-guest-layout title="Sign-in problem">
    <div role="alert">
        <h1>{{ $headline }}</h1>
        <p class="auth-lede">{{ $body }}</p>
    </div>
    <div class="claim-actions">
        @if ($retry)<a class="button solid" href="{{ $retryUrl }}">Start again</a>@endif
        <a class="button" href="{{ $homeUrl }}">Back to Dot.Agents</a>
    </div>
    <p class="claim-help">Reference: {{ $code }}</p>
</x-guest-layout>
