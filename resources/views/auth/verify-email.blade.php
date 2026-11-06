<x-guest-layout title="Verify your email">
    <h1>Verify your email</h1>
    <p class="auth-lede">We sent a link to your email address. Open it to finish setting up your account.</p>

    @if (session('status') == 'verification-link-sent')
        <p class="auth-note" role="status">A new link is on its way.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="button solid">Send the link again</button>
    </form>

    <div class="auth-row auth-more">
        <a href="{{ route('profile.show') }}">Edit your profile</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link">Sign out</button>
        </form>
    </div>
</x-guest-layout>
