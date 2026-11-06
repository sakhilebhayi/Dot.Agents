<x-action-section>
    <x-slot name="title">
        {{ __('Two-factor authentication') }}
    </x-slot>

    <x-slot name="description">
        {{ __('At each sign-in you enter a one-time code from an authenticator app on your phone.') }}
    </x-slot>

    <x-slot name="aside">
        @if ($this->enabled)
            @if ($showingConfirmation)
                <span class="lamp hold"><i aria-hidden="true"></i>{{ __('Waiting for your code') }}</span>
            @else
                <span class="lamp clear"><i aria-hidden="true"></i>{{ __('On') }}</span>
            @endif
        @else
            <span class="lamp off"><i aria-hidden="true"></i>{{ __('Off') }}</span>
        @endif
    </x-slot>

    <x-slot name="content">
        @if ($this->enabled)
            @if ($showingQrCode)
                <p>
                    @if ($showingConfirmation)
                        {{ __('To finish, scan this QR code with your authenticator app or enter the setup key, then type the code it gives you.') }}
                    @else
                        {{ __('Two-factor authentication is on. Scan this QR code with your authenticator app or enter the setup key.') }}
                    @endif
                </p>

                <div class="acct-qr">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <p class="acct-gap">
                    {{ __('Setup key') }}: <span class="mono">{{ decrypt($this->user->two_factor_secret) }}</span>
                </p>

                @if ($showingConfirmation)
                    <div class="field acct-gap">
                        <x-label for="code" value="{{ __('Code') }}" />

                        <x-input id="code" type="text" name="code" inputmode="numeric" autofocus autocomplete="one-time-code"
                            wire:model="code"
                            wire:keydown.enter="confirmTwoFactorAuthentication" />

                        <x-input-error for="code" />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <p class="acct-gap">
                    {{ __('Store these recovery codes in a password manager. They let you back in if you lose your phone.') }}
                </p>

                <div class="acct-codes">
                    @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="acct-actions">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <x-button type="button" wire:loading.attr="disabled">
                        {{ __('Turn on') }}
                    </x-button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <x-secondary-button>
                            {{ __('Regenerate recovery codes') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @elseif ($showingConfirmation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <x-button type="button" wire:loading.attr="disabled">
                            {{ __('Confirm') }}
                        </x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <x-secondary-button>
                            {{ __('Show recovery codes') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @endif

                @if ($showingConfirmation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-secondary-button wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-danger-button wire:loading.attr="disabled">
                            {{ __('Turn off') }}
                        </x-danger-button>
                    </x-confirms-password>
                @endif

            @endif
        </div>
    </x-slot>
</x-action-section>
