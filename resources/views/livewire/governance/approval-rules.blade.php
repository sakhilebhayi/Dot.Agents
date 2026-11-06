<div class="grid">
    <section class="card" aria-label="Rules">
        <div class="card-head"><h2>Rules</h2></div>
        @forelse($this->rules as $rule)
            <div class="row" wire:key="rule-{{ $rule->id }}">
                <div class="row-main">
                    <b>{{ $rule->name }}</b>
                    <span>
                        {{ $rule->tool_key ? 'The tool '.$rule->tool_key : ($rule->category ?? 'Everything') }}
                        @if($rule->approver_role), sent to {{ \App\Support\OrgRoles::LABELS[$rule->approver_role] ?? $rule->approver_role }}@endif
                        @if($rule->expires_hours), answer within {{ $rule->expires_hours }} hours @endif
                    </span>
                </div>
                @if(auth()->user()->canInOrganization('approvals.rules', $rule->organization_id))
                    <button type="button" class="btn quiet small" wire:click="remove({{ $rule->id }})" wire:confirm="Remove this rule? Agents may then act alone where their autonomy allows.">Remove</button>
                @endif
            </div>
        @empty
            <div class="empty">
                <b>No rules yet.</b>
                The platform already stops for anything an agent may not do alone. A rule adds more.
            </div>
        @endforelse
    </section>

    @if(auth()->user()->canInOrganization('approvals.rules', \App\Support\TenantContext::id()))
        <form class="card" wire:submit="save">
            <div class="card-head"><h2>Add a rule</h2></div>
            <div class="card-body">
                <div class="rule-form">
                    <label class="field">
                        <span>Name</span>
                        <input class="input" wire:model="name" maxlength="120">
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                    </label>
                    <label class="field">
                        <span>Always ask a person about</span>
                        <select class="select" wire:model="covers">
                            <option value="">Choose</option>
                            @foreach($this->choices as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach($options as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('covers')<p class="error">{{ $message }}</p>@enderror
                    </label>
                    <label class="field">
                        <span>Send to</span>
                        <select class="select" wire:model="approverRole">
                            <option value="">The agent's owner</option>
                            @foreach($this->roles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Answer within (hours)</span>
                        <input class="input num" type="number" min="1" max="720" wire:model="expiresHours" placeholder="72">
                        @error('expiresHours')<p class="error">{{ $message }}</p>@enderror
                    </label>
                    <button class="btn primary" type="submit">Add rule</button>
                </div>
            </div>
        </form>
    @endif
</div>
