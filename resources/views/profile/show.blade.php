<x-layouts.platform>
    <x-slot:header>Account Settings</x-slot:header>

    <div class="max-w-4xl mx-auto space-y-10">

        @if (Laravel\Fortify\Features::canUpdateProfileInformation())
            @livewire('profile.update-profile-information-form')
        @endif

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
            <x-section-border />
            @livewire('profile.update-password-form')
        @endif

        @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            <x-section-border />
            @livewire('profile.two-factor-authentication-form')
        @endif

        <x-section-border />
        @livewire('profile.logout-other-browser-sessions-form')

        @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
            <x-section-border />
            @livewire('profile.delete-user-form')
        @endif

    </div>
    <section class="page narrow" aria-label="Your data">
        <div class="card">
            <div class="card-head"><h2>Your data</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('privacy.export') }}">
                    @csrf
                    <p class="muted">Download everything this platform holds about you as a file.</p>
                    <button class="btn" type="submit" style="margin-top: 8px">Download my data</button>
                </form>
                <details class="tech">
                    <summary>Erase my account</summary>
                    <form method="POST" action="{{ route('privacy.erase') }}">
                        @csrf
                        <p class="muted">Your name, email and personal memories are removed and you are signed out. Records the law requires are kept without your name. This cannot be undone.</p>
                        <label class="field"><span>Type your email address to confirm</span><input class="input" type="email" name="confirm_email" required autocomplete="off"></label>
                        @error('confirm_email')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn danger" type="submit">Erase my account</button>
                    </form>
                </details>
            </div>
        </div>
    </section>
</x-layouts.platform>
