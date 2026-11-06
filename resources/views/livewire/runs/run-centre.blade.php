<x-sig.page title="Runs" sub="Everything your agents are doing and have done.">
    <div wire:poll.10s>
        <div class="tabs" role="tablist" aria-label="Runs">
            @foreach(['running' => 'Running', 'waiting' => 'Waiting for a person', 'failed' => 'Failed', 'done' => 'Finished', 'scheduled' => 'Scheduled', 'paused' => 'Paused agents'] as $key => $label)
                <button type="button" class="tab" role="tab" wire:click="setTab('{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}@isset($this->counts[$key]) <span class="num">{{ $this->counts[$key] }}</span>@endisset</button>
            @endforeach
        </div>

        @if($tab === 'scheduled')
            <section class="card" aria-label="Scheduled">
                @forelse($this->schedules as $schedule)
                    <div class="row" wire:key="schedule-{{ $schedule->id }}">
                        <div class="row-main"><b>{{ $schedule->name }}</b><span>{{ \App\Services\Triggers\ScheduleClock::words($schedule->settings ?? []) }}</span></div>
                        <span class="row-end">{{ $schedule->next_run_at->setTimezone($schedule->settings['timezone'] ?? 'UTC')->format('j M, H:i') }}</span>
                    </div>
                @empty
                    <div class="empty">Nothing is scheduled.</div>
                @endforelse
            </section>
        @elseif($tab === 'paused')
            <section class="card" aria-label="Paused agents">
                @forelse($this->paused as $agent)
                    <a class="row" href="{{ route('agents.show', $agent) }}" wire:key="paused-{{ $agent->id }}"><span class="row-main"><b>{{ $agent->name }}</b><span>{{ $agent->role }}</span></span><x-sig.lamp state="paused" /></a>
                @empty
                    <div class="empty">No agent is paused.</div>
                @endforelse
            </section>
        @else
            <section class="card" aria-label="Runs">
                <div class="card-head roster-tools">
                    <select class="select" aria-label="Agent" wire:model.live="agent">
                        <option value="">Every agent</option>
                        @foreach($this->agents as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                    </select>
                </div>
                @forelse($this->runs as $run)
                    <a class="row" href="{{ route('runs.show', $run) }}" wire:key="run-{{ $run->id }}">
                        <span class="row-main"><b>{{ $run->summary ?: 'No description' }}</b><span>{{ $run->deployment?->name }}, {{ str_replace('_', ' ', $run->trigger_type) }}, {{ $run->created_at->diffForHumans() }}</span></span>
                        <x-sig.lamp :state="$run->status" />
                    </a>
                @empty
                    <div class="empty">Nothing here.</div>
                @endforelse
                @if($this->runs->hasPages())<div class="card-body">{{ $this->runs->links() }}</div>@endif
            </section>
        @endif
    </div>
</x-sig.page>
