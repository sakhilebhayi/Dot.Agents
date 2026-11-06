<section class="card" aria-label="What starts this">
    <div class="card-head">
        <h2>What starts this</h2>
        @if(auth()->user()->canInOrganization('agents.manage', \App\Support\TenantContext::id()))
            <button type="button" class="btn quiet small" wire:click="$toggle('adding')">{{ $adding ? 'Cancel' : 'Add a trigger' }}</button>
        @endif
    </div>

    @if($newSecret)
        <div class="card-body plate">
            <p class="notice hold">Copy this now. The secret is not shown again.</p>
            <dl class="pairs">
                <div><dt>Address</dt><dd>{{ $newAddress }}</dd></div>
                <div><dt>Secret</dt><dd>{{ $newSecret }}</dd></div>
                <div><dt>Sign with</dt><dd>X-Dot-Timestamp: seconds since 1970
X-Dot-Signature: sha256= + HMAC-SHA256 of "timestamp.body"</dd></div>
            </dl>
        </div>
    @endif

    @if($adding)
        <form class="card-body" wire:submit="save">
            <div class="pair-fields">
                <label class="field">
                    <span>Starts when</span>
                    <select class="select" wire:model.live="kind">
                        <option value="schedule">A time comes round</option>
                        <option value="webhook">Another system calls</option>
                        <option value="event">Something happens here</option>
                    </select>
                </label>
                <label class="field"><span>Name</span><input class="input" wire:model="name" maxlength="120">@error('name')<p class="error">{{ $message }}</p>@enderror</label>
            </div>
            <label class="field"><span>What it should do</span><textarea class="textarea" rows="2" wire:model="instruction"></textarea></label>

            @if($kind === 'schedule')
                <div class="pair-fields">
                    <label class="field">
                        <span>How often</span>
                        <select class="select" wire:model.live="settings.frequency">
                            @foreach(\App\Services\Triggers\ScheduleClock::FREQUENCIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field"><span>At</span><input class="input" type="time" wire:model="settings.time">@error('time')<p class="error">{{ $message }}</p>@enderror</label>
                    @if($settings['frequency'] === 'weekly')
                        <label class="field">
                            <span>On</span>
                            <select class="select" wire:model="settings.weekday">
                                @foreach(\App\Services\Triggers\ScheduleClock::DAYS as $index => $day)<option value="{{ $index }}">{{ $day }}</option>@endforeach
                            </select>
                        </label>
                    @elseif($settings['frequency'] === 'monthly')
                        <label class="field"><span>Day of the month</span><input class="input num" type="number" min="1" max="31" wire:model="settings.monthday"></label>
                    @endif
                    <label class="field"><span>{{ $settings['frequency'] === 'once' ? 'Date' : 'Start on' }}</span><input class="input" type="date" wire:model="settings.start_date">@error('start_date')<p class="error">{{ $message }}</p>@enderror</label>
                    @if($settings['frequency'] !== 'once')
                        <label class="field"><span>End on</span><input class="input" type="date" wire:model="settings.end_date"></label>
                        <label class="field"><span>Stop after (runs)</span><input class="input num" type="number" min="1" wire:model="settings.max_runs"></label>
                    @endif
                    <label class="field">
                        <span>Time zone</span>
                        <select class="select" wire:model="settings.timezone">
                            @foreach(\DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA | \DateTimeZone::UTC) as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach
                        </select>
                    </label>
                </div>
            @elseif($kind === 'event')
                <label class="field">
                    <span>When</span>
                    <select class="select" wire:model="settings.event">
                        @foreach(\App\Models\AgentTrigger::EVENTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            @else
                <p class="muted">You get an address and a secret. Whatever arrives is treated as outside content, so the agent can never be instructed by it.</p>
            @endif

            @error('target')<p class="error">{{ $message }}</p>@enderror
            <button type="submit" class="btn primary">Add trigger</button>
        </form>
    @endif

    @forelse($this->triggers as $trigger)
        <div class="row template" wire:key="trigger-{{ $trigger->id }}">
            <div class="row-main">
                <b>{{ $trigger->name }}</b>
                <span>
                    @if($trigger->kind === 'schedule')
                        {{ \App\Services\Triggers\ScheduleClock::words($trigger->settings ?? []) }}@if($trigger->next_run_at). Next {{ $trigger->next_run_at->setTimezone($trigger->settings['timezone'] ?? 'UTC')->format('j M, H:i') }}@endif
                    @elseif($trigger->kind === 'webhook')
                        Called by another system
                    @else
                        {{ \App\Models\AgentTrigger::EVENTS[$trigger->settings['event'] ?? ''] ?? 'An event' }}
                    @endif
                </span>
                @if($trigger->last_error)<span class="error">{{ $trigger->last_error }}</span>@endif
            </div>
            <span class="chip {{ $trigger->is_active ? 'clear' : '' }}">{{ $trigger->is_active ? 'On' : 'Paused' }}</span>
            <button type="button" class="btn quiet small" wire:click="toggle({{ $trigger->id }})">{{ $trigger->is_active ? 'Pause' : 'Resume' }}</button>
            @if($trigger->kind !== 'webhook')<button type="button" class="btn quiet small" wire:click="fireNow({{ $trigger->id }})">Run now</button>@endif
            <button type="button" class="btn quiet small" wire:click="remove({{ $trigger->id }})" wire:confirm="Remove {{ $trigger->name }}?">Remove</button>
        </div>
    @empty
        @unless($adding)<div class="empty">It runs only when someone starts it.</div>@endunless
    @endforelse
</section>
