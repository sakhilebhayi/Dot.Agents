<x-sig.page title="Teams" sub="One agent supervises and hands work to the others. Give it to the supervisor and it does the rest." narrow>
    <section class="card" aria-label="Teams" style="margin-bottom: var(--gap)">
        @forelse($this->teams as $team)
            <div class="row template" wire:key="team-{{ $team->id }}">
                <div class="row-main">
                    <b>{{ $team->name }}</b>
                    <p class="muted">{{ $team->purpose }}</p>
                    <span>Led by {{ $team->supervisor?->name }}, with {{ $team->members->map(fn ($member) => $member->deployment?->name)->filter()->implode(', ') }}</span>
                </div>
                <button type="button" class="btn quiet small" wire:click="remove({{ $team->id }})" wire:confirm="Remove {{ $team->name }}? The agents stay.">Remove</button>
            </div>
        @empty
            <div class="empty"><b>No teams yet.</b> Most work needs one agent. Make a team when the work really splits into separate jobs.</div>
        @endforelse
    </section>

    <form class="card" wire:submit="save">
        <div class="card-head"><h2>New team</h2></div>
        <div class="card-body">
            <div class="pair-fields">
                <label class="field"><span>Name</span><input class="input" wire:model="name" maxlength="120">@error('name')<p class="error">{{ $message }}</p>@enderror</label>
                <label class="field">
                    <span>Supervisor</span>
                    <select class="select" wire:model="supervisorId">
                        <option value="">Choose an agent</option>
                        @foreach($this->agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach
                    </select>
                    @error('supervisorId')<p class="error">Choose who leads this team.</p>@enderror
                </label>
            </div>
            <label class="field"><span>What the team is for</span><input class="input" wire:model="purpose" maxlength="500"></label>

            <fieldset class="flow-after">
                <legend class="label">Teammates</legend>
                @foreach($this->agents as $agent)
                    <div class="team-pick" wire:key="pick-{{ $agent->id }}">
                        <label class="check-line"><input type="checkbox" wire:model.live="members.{{ $agent->id }}.on"><span>{{ $agent->name }}</span></label>
                        @if($members[$agent->id]['on'] ?? false)
                            <input class="input" aria-label="What {{ $agent->name }} is responsible for" placeholder="Responsible for" wire:model="members.{{ $agent->id }}.responsibility" maxlength="500">
                        @endif
                    </div>
                @endforeach
                @error('members')<p class="error">{{ $message }}</p>@enderror
            </fieldset>

            <button type="submit" class="btn primary">Create team</button>
        </div>
    </form>
</x-sig.page>
