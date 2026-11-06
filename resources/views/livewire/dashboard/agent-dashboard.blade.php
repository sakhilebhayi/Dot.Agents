@php
    $definitions = \App\Services\Insights\RunMetrics::DEFINITIONS;
    $peak = max(1, collect($this->daily)->max(fn ($day) => $day['completed'] + $day['failed']));
    $seconds = $this->summary['duration'];
@endphp

<x-sig.page title="Command centre" sub="What needs you, what is under way, and how the work has gone.">
    <x-slot:actions>
        <div class="segmented" role="group" aria-label="Period">
            @foreach(['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'] as $value => $label)
                <button type="button" class="btn small {{ $timeframe === $value ? 'is-on' : 'quiet' }}" wire:click="setTimeframe('{{ $value }}')" aria-pressed="{{ $timeframe === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    </x-slot:actions>

    <div class="grid two" wire:poll.30s>
        <div class="grid">
            @if($this->approvals->isNotEmpty() || $this->failures->isNotEmpty())
                <section class="card" aria-label="Needs you">
                    <div class="card-head"><h2>Needs you</h2></div>
                    @foreach($this->approvals as $approval)
                        <a class="row" href="{{ route('governance.approvals', ['approval' => $approval->uuid]) }}" wire:key="approval-{{ $approval->id }}">
                            <span class="row-main"><b>{{ $approval->title }}</b><span>{{ $approval->deployment?->name }}, {{ $approval->created_at->diffForHumans() }}</span></span>
                            <x-sig.lamp state="waiting_approval" />
                        </a>
                    @endforeach
                    @foreach($this->failures as $run)
                        <a class="row" href="{{ route('runs.show', $run) }}" wire:key="failed-{{ $run->id }}">
                            <span class="row-main"><b>{{ $run->summary }}</b><span>{{ $run->deployment?->name }}: {{ $run->error_message }}</span></span>
                            <x-sig.lamp :state="$run->status" />
                        </a>
                    @endforeach
                </section>
            @endif

            <section class="card" aria-label="Under way">
                <div class="card-head"><h2>Under way</h2><a class="btn quiet small" href="{{ route('runs.index') }}">All runs</a></div>
                @foreach($this->workflows as $execution)
                    <div class="row" wire:key="flow-{{ $execution->id }}"><div class="row-main"><b>{{ $execution->workflow?->name }}</b><span>{{ $execution->current_step }} of {{ $execution->total_steps }} steps</span></div><x-sig.lamp :state="$execution->status" /></div>
                @endforeach
                @foreach($this->running as $run)
                    <a class="row" href="{{ route('runs.show', $run) }}" wire:key="run-{{ $run->id }}"><span class="row-main"><b>{{ $run->summary }}</b><span>{{ $run->deployment?->name }}</span></span><x-sig.lamp :state="$run->status" /></a>
                @endforeach
                @if($this->workflows->isEmpty() && $this->running->isEmpty())
                    <div class="empty">Nothing is running right now.</div>
                @endif
            </section>

            <section class="card" aria-label="The last two weeks">
                <div class="card-head"><h2>The last two weeks</h2>
                    <span class="legend"><i class="done"></i>Completed <i class="bad"></i>Failed</span>
                </div>
                <div class="card-body">
                    <div class="bars" role="img" aria-label="Completed and failed work each day. The table below has the same figures.">
                        @foreach($this->daily as $day)
                            <div class="bar" title="{{ $day['day'] }}: {{ $day['completed'] }} completed, {{ $day['failed'] }} failed">
                                @if($day['failed'] > 0)<i class="bad" style="height: {{ round(100 * $day['failed'] / $peak) }}%"></i>@endif
                                @if($day['completed'] > 0)<i class="done" style="height: {{ round(100 * $day['completed'] / $peak) }}%"></i>@endif
                            </div>
                        @endforeach
                    </div>
                    <details class="tech"><summary>See the figures</summary>
                        <table class="table"><thead><tr><th>Day</th><th class="right">Completed</th><th class="right">Failed</th></tr></thead><tbody>
                            @foreach($this->daily as $day)<tr><td>{{ $day['day'] }}</td><td class="right num">{{ $day['completed'] }}</td><td class="right num">{{ $day['failed'] }}</td></tr>@endforeach
                        </tbody></table>
                    </details>
                </div>
            </section>

            <section class="card" aria-label="How each agent is doing">
                <div class="card-head"><h2>How each agent is doing</h2></div>
                @forelse($this->agents as $row)
                    <a class="row" href="{{ route('agents.show', $row['agent']) }}" wire:key="agent-{{ $row['agent']?->id }}">
                        <span class="row-main"><b>{{ $row['agent']?->name }}</b><span>{{ $row['runs'] }} {{ Str::plural('run', $row['runs']) }}, {{ $row['failed'] }} failed</span></span>
                        <span class="row-end num">{{ $row['rate'] === null ? '–' : $row['rate'].'%' }}</span>
                    </a>
                @empty
                    <div class="empty">No agent has finished any work in this period.</div>
                @endforelse
            </section>
        </div>

        <div class="grid">
            <div class="stats side-stats">
                <div class="stat" title="{{ $definitions['finished'] }}"><span>Finished</span><b>{{ number_format($this->summary['finished']) }}</b></div>
                <div class="stat" title="{{ $definitions['rate'] }}"><span>Completed</span><b>{{ $this->summary['rate'] === null ? '–' : $this->summary['rate'].'%' }}</b></div>
                <div class="stat" title="{{ $definitions['cost'] }}"><span>Cost</span><b>{{ number_format($this->summary['cost'], 2) }}</b></div>
                <div class="stat" title="{{ $definitions['duration'] }}"><span>Average time</span><b>{{ $seconds === null ? '–' : ($seconds >= 60 ? round($seconds / 60, 1).' min' : $seconds.' s') }}</b></div>
            </div>
            @if($this->summary['unpriced'] > 0)
                <p class="muted small-note"><a href="{{ route('usage') }}">Cost is missing {{ $this->summary['unpriced'] }} {{ Str::plural('call', $this->summary['unpriced']) }}</a> with no price on file.</p>
            @endif

            <section class="card" aria-label="Your agents">
                <div class="card-head"><h2>Your agents</h2></div>
                @forelse($this->mine as $agent)
                    <div class="row" wire:key="mine-{{ $agent->id }}">
                        <button type="button" class="star" wire:click="toggleFavourite({{ $agent->id }})" aria-pressed="{{ $agent->starred ? 'true' : 'false' }}" aria-label="Star {{ $agent->name }}">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1.8l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.6l-3.8 2 .7-4.3-3.1-3 4.3-.6z"/></svg>
                        </button>
                        <a class="row-main" href="{{ route('agents.show', $agent) }}"><b>{{ $agent->name }}</b><span>{{ $agent->role }}</span></a>
                        <x-sig.lamp :state="$agent->status" class="desk-lamp" />
                    </div>
                @empty
                    <div class="empty"><b>No agents yet.</b> <a class="btn small" href="{{ route('agents.create') }}">New agent</a></div>
                @endforelse
            </section>
        </div>
    </div>
</x-sig.page>
