<x-action-section>
    <x-slot name="title">
        {{ __('Browser sessions') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Where you are signed in.') }}
    </x-slot>

    <x-slot name="content">
        <p class="muted">
            {{ __('Recent sessions are listed here, and the list may not be complete. If you think your account has been compromised, change your password as well.') }}
        </p>

        @if (count($this->sessions) > 0)
            <ul class="acct-rows">
                <!-- Other Browser Sessions -->
                @foreach ($this->sessions as $session)
                    <li class="row">
                        <div class="row-main">
                            <b>{{ $session->agent->platform() ? $session->agent->platform() : __('Unknown') }} · {{ $session->agent->browser() ? $session->agent->browser() : __('Unknown') }}</b>
                            <span class="mono">{{ $session->ip_address }}</span>
                        </div>

                        <div class="row-end">
                            @if ($session->is_current_device)
                                <span class="lamp clear"><i aria-hidden="true"></i>{{ __('This device') }}</span>
                            @else
                                {{ __('Last active') }} {{ $session->last_active }}
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="acct-actions">
            <x-button wire:click="confirmLogout" wire:loading.attr="disabled">
                {{ __('Sign out other browsers') }}
            </x-button>

            <x-action-message on="loggedOut">
                {{ __('Done.') }}
            </x-action-message>
        </div>

        <!-- Log Out Other Devices Confirmation Modal -->
        <x-dialog-modal wire:model.live="confirmingLogout">
            <x-slot name="title">
                {{ __('Sign out other browsers') }}
            </x-slot>

            <x-slot name="content">
                <p>{{ __('Enter your password to sign out of every other browser and device.') }}</p>

                <div class="field acct-gap" x-data="{}" x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-label for="logout_other_password" value="{{ __('Password') }}" />
                    <x-input id="logout_other_password" type="password"
                                autocomplete="current-password"
                                x-ref="password"
                                wire:model="password"
                                wire:keydown.enter="logoutOtherBrowserSessions" />

                    <x-input-error for="password" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingLogout')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-button wire:click="logoutOtherBrowserSessions"
                            wire:loading.attr="disabled">
                    {{ __('Sign out other browsers') }}
                </x-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
