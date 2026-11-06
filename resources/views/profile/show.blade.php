<x-layouts.platform>
    <x-slot:header>Your account</x-slot:header>

    <x-sig.page title="Your account" sub="Your sign-in details, your devices and your data." narrow>
        <div class="acct">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                @livewire('profile.update-password-form')
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                @livewire('profile.two-factor-authentication-form')
            @endif

            @livewire('profile.logout-other-browser-sessions-form')

            <section class="card" aria-labelledby="your-data">
                <div class="card-head"><h2 id="your-data">Your data</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('privacy.export') }}">
                        @csrf
                        <p class="muted">Download everything this platform holds about you as a file.</p>
                        <button class="btn acct-gap" type="submit">Download my data</button>
                    </form>
                    <details class="tech">
                        <summary>Erase my account</summary>
                        <form method="POST" action="{{ route('privacy.erase') }}">
                            @csrf
                            <p class="muted">Your name, email and personal memories are removed and you are signed out. Records the law requires are kept without your name. This cannot be undone.</p>
                            <label class="field acct-gap"><span>Type your email address to confirm</span><input class="input" type="email" name="confirm_email" required autocomplete="off"></label>
                            @error('confirm_email')<p class="error" role="alert">{{ $message }}</p>@enderror
                            <button class="btn danger" type="submit">Erase my account</button>
                        </form>
                    </details>
                </div>
            </section>
        </div>
    </x-sig.page>
</x-layouts.platform>
