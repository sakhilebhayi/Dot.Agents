<x-sig.page title="API keys" :sub="'Let your own systems work with this business. A key works for this business only, does only what you tick, and lasts '.\App\Livewire\Organizations\ApiKeyManager::DAYS_VALID.' days.'" narrow>
    {{-- Shown once, immediately after creation. --}}
    @if($plainTextToken)
        <div class="notice hold" role="alert">
            <div class="grid org-stack org-key">
                <b>Copy this key now. It will not be shown again.</b>
                <code class="org-token">{{ $plainTextToken }}</code>
                <button type="button" class="btn small" wire:click="dismissToken">I have copied it</button>
            </div>
        </div>
    @endif

    <div class="grid org-stack">
        @writable
            @if(auth()->user()->canInOrganization('api.manage', (int) session('current_organization_id')))
                <form class="card" wire:submit="createToken">
                    <div class="card-head"><h2>Create a key</h2></div>
                    <div class="card-body">
                        <label class="field">
                            <span>Name</span>
                            <input class="input" type="text" wire:model="newKeyName" placeholder="CI pipeline, n8n">
                            @error('newKeyName')<p class="error">{{ $message }}</p>@enderror
                        </label>

                        <fieldset class="flow-after">
                            <legend class="label">What this key may do</legend>
                            @foreach(\App\Models\PersonalAccessToken::ABILITIES as $ability => $label)
                                <label class="check-line" wire:key="ability-{{ $ability }}">
                                    <input type="checkbox" wire:model="newKeyAbilities" value="{{ $ability }}">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </fieldset>

                        <button type="submit" class="btn primary" wire:loading.attr="disabled" wire:loading.class="org-busy">
                            <span wire:loading.remove wire:target="createToken">Generate key</span>
                            <span wire:loading wire:target="createToken">Generating…</span>
                        </button>
                    </div>
                </form>
            @endif
        @endwritable

        <section class="card" aria-label="Keys">
            <div class="card-head"><h2>Keys</h2></div>
            <div>
                @forelse($this->tokens as $token)
                    @php $keyName = \Illuminate\Support\Str::after($token['name'], ':'); @endphp
                    <div class="row" wire:key="token-{{ $token['id'] }}">
                        <div class="row-main">
                            <b>{{ $keyName }}</b>
                            <span>Created <time class="num">{{ $token['created_at'] }}</time>, {{ $token['last_used_at'] ? 'last used '.$token['last_used_at'] : 'never used' }}</span>
                        </div>
                        @writable
                            <button type="button" class="btn quiet small" aria-label="Revoke key {{ $keyName }}" wire:click="revokeToken({{ $token['id'] }})" wire:confirm="Revoke this API key? Any integrations using it will stop working immediately.">Revoke</button>
                        @endwritable
                    </div>
                @empty
                    <div class="empty"><b>No API keys yet.</b></div>
                @endforelse
            </div>
        </section>
    </div>
</x-sig.page>
