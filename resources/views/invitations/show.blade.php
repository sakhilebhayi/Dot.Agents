<x-guest-layout title="Your invitation">
    @if($invitation === null)
        <h1>This invitation has ended</h1>
        <p class="auth-lede">It was used, cancelled or is out of date. Ask the person who invited you to send a new one.</p>
    @else
        <h1>Join {{ $invitation->organization->name }}</h1>
        <p class="auth-lede">You were invited as {{ strtolower($roleLabel) }}. {{ \App\Support\OrgRoles::DESCRIPTIONS[$invitation->role] ?? '' }}</p>

        @auth
            @if($matches)
                <form method="POST" action="{{ route('invitations.accept', $token) }}">
                    @csrf
                    <button type="submit" class="button solid">Join {{ $invitation->organization->name }}</button>
                </form>
            @else
                <div class="auth-problem" role="alert"><p>This invitation is for {{ $invitation->email }}. You are signed in as {{ auth()->user()->email }}.</p></div>
            @endif
        @else
            <p class="auth-more">Sign in with the account for {{ $invitation->email }}. If you do not come back here after signing in, open the link in your email again.</p>
            <p class="auth-more"><a class="button solid" href="{{ route('login') }}">Sign in</a></p>
        @endauth
    @endif
</x-guest-layout>
