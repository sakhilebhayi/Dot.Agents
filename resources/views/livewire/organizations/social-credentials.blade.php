<div class="grid org-stack">
    <section class="card">
        <div class="card-head"><h2>Social accounts</h2></div>
        <div class="card-body">
            <p class="muted">Connect the accounts this business uses. The social tools are a separate pack and keep their older look for now.</p>
            <div class="answer-main flow-after">
                <a class="btn primary" href="{{ route('social.connect') }}">Connect an account</a>
                <a class="btn" href="{{ route('social.accounts') }}">See connections</a>
            </div>
        </div>
    </section>

    <section class="card" aria-label="Your own developer apps">
        <div class="card-head"><h2>Your own developer apps</h2></div>
        <div class="card-body"><p class="muted">Only needed when your business must use its own registered app on a platform.</p></div>
        @foreach(\App\Livewire\Organizations\SocialCredentials::$platforms as $platform => $meta)
            @php $cred = $this->credentials->get($platform); @endphp
            <div class="row org-row" wire:key="platform-{{ $platform }}">
                <div class="row-main">
                    <b>{{ $meta['label'] }}</b>
                    <span>{{ $cred ? 'Your app, updated '.$cred->updated_at->diffForHumans() : 'Using the default app' }}</span>
                </div>
                <div class="org-end">
                    @if($editing === $platform)
                        <button type="button" class="btn quiet small" wire:click="cancelEdit">Cancel</button>
                    @else
                        @writable
                            @if($cred)
                                <button type="button" class="btn quiet small" wire:click="removePlatform('{{ $platform }}')" wire:confirm="Remove your app for {{ $meta['label'] }}?">Remove</button>
                            @endif
                            <button type="button" class="btn small" wire:click="openEdit('{{ $platform }}')">{{ $cred ? 'Update' : 'Set up' }}</button>
                        @endwritable
                    @endif
                </div>
                @if($editing === $platform)
                    <form class="org-confirm" wire:submit="savePlatform">
                        <p>In the <a href="{{ $meta['docs'] }}" target="_blank" rel="noopener noreferrer">{{ $meta['label'] }} developer console</a>, set the redirect address to <code class="tech">{{ route('social.auth.callback', ['platform' => $platform]) }}</code></p>
                        <div class="pair-fields">
                            <label class="field">
                                <span>App or client ID</span>
                                <input class="input" wire:model="clientId" type="text" autocomplete="off" spellcheck="false">
                                @error('clientId')<p class="error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span>App or client secret</span>
                                <input class="input" wire:model="clientSecret" type="password" autocomplete="new-password">
                                @error('clientSecret')<p class="error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                        <button type="submit" class="btn primary small" wire:loading.attr="disabled" wire:target="savePlatform">Save</button>
                    </form>
                @endif
            </div>
        @endforeach
    </section>
</div>
