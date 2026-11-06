<x-sig.page title="Business settings" sub="The name, region and currency of this business." narrow>
    <div class="tabs" role="tablist" aria-label="Settings sections">
        <button type="button" class="tab" role="tab" aria-selected="{{ $tab === 'general' ? 'true' : 'false' }}" wire:click="$set('tab','general')">General</button>
        <button type="button" class="tab" role="tab" aria-selected="{{ $tab === 'social' ? 'true' : 'false' }}" wire:click="$set('tab','social')">Social platform credentials</button>
    </div>

    @if($saved)
        <div class="notice clear" role="status">Settings saved successfully.</div>
    @endif

    <div role="tabpanel">
        @if($tab === 'general')
            <form class="grid org-stack" wire:submit="save">
                <section class="card" aria-label="Identity">
                    <div class="card-head"><h2>Identity</h2></div>
                    <div class="card-body">
                        <div class="pair-fields">
                            <label class="field">
                                <span>Business name</span>
                                <input class="input" type="text" wire:model="name" placeholder="Acme Corp" aria-required="true">
                                @error('name')<p class="error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span>Domain</span>
                                <input class="input" type="text" wire:model="domain" placeholder="acme.com">
                            </label>
                            <label class="field">
                                <span>Industry</span>
                                <select class="select" wire:model="industry">
                                    <option value="">Choose an industry</option>
                                    @foreach(['Technology','Finance','Healthcare','Retail','Manufacturing','Education','Legal','Marketing','Real Estate','Other'] as $ind)
                                        <option value="{{ $ind }}">{{ $ind }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">
                                <span>Company size</span>
                                <select class="select" wire:model="size">
                                    <option value="">Choose a size</option>
                                    <option value="1-10">1 to 10 employees</option>
                                    <option value="11-50">11 to 50 employees</option>
                                    <option value="51-200">51 to 200 employees</option>
                                    <option value="201-500">201 to 500 employees</option>
                                    <option value="501+">More than 500 employees</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="card" aria-label="Region and currency">
                    <div class="card-head"><h2>Region and currency</h2></div>
                    <div class="card-body">
                        <div class="rule-form">
                            <label class="field">
                                <span>Country code</span>
                                <input class="input" type="text" wire:model="country" maxlength="2" placeholder="ZA">
                            </label>
                            <label class="field">
                                <span>Timezone</span>
                                <input class="input" type="text" wire:model="timezone" placeholder="Africa/Johannesburg">
                            </label>
                            <label class="field">
                                <span>Currency</span>
                                <select class="select" wire:model="currency">
                                    <option value="USD">USD, US dollar</option>
                                    <option value="EUR">EUR, euro</option>
                                    <option value="GBP">GBP, British pound</option>
                                    <option value="ZAR">ZAR, South African rand</option>
                                    <option value="AUD">AUD, Australian dollar</option>
                                    <option value="CAD">CAD, Canadian dollar</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </section>

                @can('update', $this->currentOrganization)
                    @writable
                        <div class="answer-main">
                            <button type="submit" class="btn primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Save settings</span>
                                <span wire:loading>Saving…</span>
                            </button>
                        </div>
                    @else
                        <p class="muted">Your access here is read-only.</p>
                    @endwritable
                @else
                    <p class="muted">Only the business owner or an administrator can change these settings.</p>
                @endcan
            </form>
        @elseif($tab === 'social')
            @livewire('organizations.social-credentials')
        @endif
    </div>
</x-sig.page>
