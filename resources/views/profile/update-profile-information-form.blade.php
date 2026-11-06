<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Your name and the email address you sign in with.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="field">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" hidden
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="acct-photo">
                </div>

                <!-- New Profile Photo Preview -->
                <div x-show="photoPreview" style="display: none;">
                    <span class="acct-photo"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <div class="acct-actions">
                    <x-secondary-button type="button" x-on:click.prevent="$refs.photo.click()">
                        {{ __('Select a new photo') }}
                    </x-secondary-button>

                    @if ($this->user->profile_photo_path)
                        <x-secondary-button type="button" wire:click="deleteProfilePhoto">
                            {{ __('Remove photo') }}
                        </x-secondary-button>
                    @endif
                </div>

                <x-input-error for="photo" />
            </div>
        @endif

        <!-- Name -->
        <div class="field">
            <x-label for="name" value="{{ __('Name') }}" />
            <x-input id="name" type="text" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" />
        </div>

        <!-- Email -->
        <div class="field">
            <x-label for="email" value="{{ __('Email') }}" />
            <x-input id="email" type="email" wire:model="state.email" required autocomplete="username" />
            <x-input-error for="email" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <p class="hint">
                    {{ __('Your email address is not verified.') }}

                    <button type="button" class="acct-link" wire:click.prevent="sendEmailVerification">
                        {{ __('Send the verification email again.') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="hint" role="status">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
