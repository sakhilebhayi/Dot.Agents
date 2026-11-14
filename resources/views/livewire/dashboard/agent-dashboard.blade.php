@php
    $definitions = \App\Services\Insights\RunMetrics::DEFINITIONS;
    $peak = max(1, collect($this->daily)->max(fn ($day) => $day['completed'] + $day['failed']));
    $seconds = $this->summary['duration'];
    $delay = $this->summary['approval_delay'];
    $arrival = $this->arrival;
    $featured = $this->featured;
    $state = $arrival['state'];
    $none = in_array($state, ['first-agent', 'no-agents'], true);
    $budget = $this->budget;
    $needs = $this->needsYou;
    $minutes = fn (?int $s) => $s === null ? '–' : ($s >= 60 ? round($s / 60, 1).' min' : $s.' s');
@endphp

<div class="page dash" data-state-page="{{ $state }}">
    <section class="arrive" aria-labelledby="arrive-title">
        <div class="arrive-head">
            <span class="arrive-logo">
                <img class="on-dark" src="{{ asset('dot.logos3-light.png') }}" alt="Dot.Agents" width="1181" height="886">
                <img class="on-light" src="{{ asset('coming-soon/dot.logos3.png') }}" alt="Dot.Agents" width="1181" height="886">
            </span>
        </div>

        <div class="arrive-body">
            <div class="arrive-lead">
                <p class="arrive-welcome" data-welcome>{{ $arrival['welcome'] }}</p>
                <h1 id="arrive-title">{{ $arrival['headline'] }}</h1>
                <div class="arrive-act">
                    <a class="btn primary lead" href="{{ $arrival['href'] }}" data-primary-action data-state="{{ $arrival['state'] }}">{{ $arrival['label'] }}</a>
                    <p class="arrive-why">{{ $arrival['reason'] }}</p>
                </div>
                @if($arrival['more'] !== [])
                    <nav class="arrive-more" aria-label="Also here">
                        @foreach($arrival['more'] as [$label, $href])<a href="{{ $href }}">{{ $label }}</a>@endforeach
                    </nav>
                @endif
            </div>

            @if($featured)
                <section class="arrive-run" aria-label="{{ $featured['run']->status === \App\Models\AgentRun::WAITING_APPROVAL ? 'The run that is waiting' : 'The latest run' }}" data-featured-run>
                    <div class="sec-head">
                        <h2>{{ $featured['run']->status === \App\Models\AgentRun::WAITING_APPROVAL ? 'The run that is waiting' : 'The latest run' }}</h2>
                        <x-sig.lamp :state="$featured['run']->status" />
                    </div>
                    <p class="arrive-run-what"><b>{{ $featured['run']->summary ?: 'Untitled work' }}</b><span>{{ $featured['run']->deployment?->name }}, {{ $featured['run']->created_at->diffForHumans() }}</span></p>
                    @if($featured['stops'] !== [])
                        <x-sig.line :stops="$featured['stops']" class="upright" aria-label="The last steps of this run" />
                        @if($featured['total'] > count($featured['stops']))<p class="muted small-note">The last {{ count($featured['stops']) }} of {{ $featured['total'] }} steps.</p>@endif
                    @else
                        <p class="muted">No step has been written down for this run yet.</p>
                    @endif
                    <a class="btn small" href="{{ route('runs.show', $featured['run']) }}">Open this run</a>
                </section>
            @else
                <section class="arrive-run" aria-label="The latest run" data-no-run>
                    <p class="empty-line"><b>No run yet.</b> The first piece of work an agent does is drawn here, step by step.</p>
                </section>
            @endif
        </div>
        <x-sig.rail :state="$state" />
    </section>

    @unless($none)
    <div class="desk-grid board" wire:poll.30s>
        <div class="col">
            @if($needs > 0)
                <section class="sec" aria-label="Needs you">
                    <header class="sec-head"><h2>Needs you<span class="n num">{{ $needs }}</span></h2><a class="btn quiet small" href="{{ route('governance.approvals') }}">All approvals</a></header>
                    @foreach($this->approvals as $approval)
                        <a class="row two" href="{{ route('governance.approvals', ['approval' => $approval->uuid]) }}" wire:key="approval-{{ $approval->id }}" @if($featured && $featured['run']->id === $approval->agent_run_id) aria-current="true" @endif>
                            <span class="row-main"><b>{{ $approval->title }}</b><span>{{ $approval->deployment?->name }}, {{ $approval->risk_level }} risk</span></span>
                            <span class="row-age num">{{ $approval->created_at->diffForHumans(null, true, true) }}</span>
                            <x-sig.lamp state="waiting_approval" />
                        </a>
                    @endforeach
                    @foreach($this->failures as $run)
                        <a class="row two" href="{{ route('runs.show', $run) }}" wire:key="failed-{{ $run->id }}">
                            <span class="row-main"><b>{{ $run->summary }}</b><span>{{ $run->deployment?->name }}: {{ $run->error_message }}</span></span>
                            <span class="row-age num">{{ $run->finished_at?->diffForHumans(null, true, true) }}</span>
                            <x-sig.lamp :state="$run->status" />
                        </a>
                    @endforeach
                </section>
            @else
                <p class="calm" aria-label="All clear">Nothing needs a person.</p>
            @endif

            @if($state === 'work')
                @include('livewire.dashboard.partials.agents-table')
            @endif

            <section class="sec" aria-label="Under way">
                <header class="sec-head"><h2>Under way<span class="n num">{{ $this->workflows->count() + $this->running->count() }}</span></h2><a class="btn quiet small" href="{{ route('runs.index') }}">All runs</a></header>
                @foreach($this->workflows as $execution)
                    <div class="row two" wire:key="flow-{{ $execution->id }}"><div class="row-main"><b>{{ $execution->workflow?->name }}</b><span>{{ $execution->current_step }} of {{ $execution->total_steps }} steps</span></div><x-sig.lamp :state="$execution->status" /></div>
                @endforeach
                @foreach($this->running as $run)
                    <a class="row two" href="{{ route('runs.show', $run) }}" wire:key="run-{{ $run->id }}"><span class="row-main"><b>{{ $run->summary }}</b><span>{{ $run->deployment?->name }}, started {{ $run->created_at->diffForHumans() }}</span></span><x-sig.lamp :state="$run->status" /></a>
                @endforeach
                @if($this->workflows->isEmpty() && $this->running->isEmpty())
                    <div class="empty-line">Nothing is running right now.</div>
                @endif
            </section>

            @if($state !== 'work')
                @include('livewire.dashboard.partials.agents-table')
            @endif
        </div>

        <aside class="col" aria-label="The work in figures">
            <section class="sec" aria-label="How the work has gone">
                <header class="sec-head"><h2>How the work has gone</h2>
                    <div class="segmented" role="group" aria-label="Period">
                        @foreach(['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'] as $value => $label)
                            <button type="button" class="btn small {{ $timeframe === $value ? 'is-on' : 'quiet' }}" wire:click="setTimeframe('{{ $value }}')" aria-pressed="{{ $timeframe === $value ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </header>
                <dl class="facts">
                    <div class="row fig" title="{{ $definitions['finished'] }}"><dt>Finished</dt><dd class="num"  wire:key="fig-{{ $timeframe }}-finished" data-figure="finished">{{ number_format($this->summary['finished']) }}</dd></div>
                    <div class="row fig" title="{{ $definitions['rate'] }}"><dt>Completed</dt><dd class="num"  wire:key="fig-{{ $timeframe }}-rate" data-figure="rate">{{ $this->summary['rate'] === null ? '–' : $this->summary['rate'].'%' }}</dd></div>
                    <div class="row fig" title="{{ $definitions['duration'] }}"><dt>Average time</dt><dd class="num"  wire:key="fig-{{ $timeframe }}-duration" data-figure="duration">{{ $minutes($seconds) }}</dd></div>
                    <div class="row fig" title="{{ $definitions['approval_delay'] }}"><dt>Approval wait</dt><dd class="num"  wire:key="fig-{{ $timeframe }}-wait" data-figure="approval-wait">{{ $minutes($delay) }}</dd></div>
                </dl>
            </section>

            @if($budget)
                <section class="sec" aria-label="This month">
                    <header class="sec-head"><h2>This month</h2><a class="btn quiet small" href="{{ route('usage') }}">{{ $budget['limit'] === null ? 'Set a limit' : 'Usage' }}</a></header>
                    <dl class="facts">
                        <div class="row fig" title="{{ $definitions['cost'] }}"><dt>Spent</dt><dd class="num" data-figure="spent">{{ number_format($budget['spent'], 2) }}</dd></div>
                        @if($budget['limit'] !== null)<div class="row fig"><dt>Monthly limit</dt><dd class="num" data-figure="limit">{{ number_format($budget['limit'], 2) }}</dd></div>@endif
                    </dl>
                    @if($budget['percent'] !== null)
                        <div @class(['meter', 'warn' => $budget['warn']]) role="img" aria-label="{{ $budget['percent'] }} percent of the monthly limit spent"><i style="transform: scaleX({{ $budget['percent'] / 100 }})"></i></div>
                        <p class="muted small-note">{{ $budget['stop'] ? 'Runs stop at the limit.' : 'You are warned at the limit; runs carry on.' }}@if($this->summary['unpriced'] > 0) <a href="{{ route('usage') }}">{{ $this->summary['unpriced'] }} {{ Str::plural('call', $this->summary['unpriced']) }} {{ $this->summary['unpriced'] === 1 ? 'has' : 'have' }} no price on file</a>, so spending is missing {{ $this->summary['unpriced'] === 1 ? 'it' : 'them' }}.@endif</p>
                    @elseif($this->summary['unpriced'] > 0)
                        <p class="muted small-note"><a href="{{ route('usage') }}">Cost is missing {{ $this->summary['unpriced'] }} {{ Str::plural('call', $this->summary['unpriced']) }}</a> with no price on file.</p>
                    @endif
                </section>
            @endif

            <section class="sec" aria-label="Coming up">
                <header class="sec-head"><h2>Coming up</h2><a class="btn quiet small" href="{{ route('runs.index', ['tab' => 'scheduled']) }}">Schedules</a></header>
                @forelse($this->schedules as $schedule)
                    <div class="row two fig" wire:key="schedule-{{ $schedule->id }}"><div class="row-main"><b>{{ $schedule->name }}</b><span>{{ \App\Services\Triggers\ScheduleClock::words($schedule->settings ?? []) }}</span></div><span class="row-age num">in {{ $schedule->next_run_at->diffForHumans(null, true, true) }}</span></div>
                @empty
                    <div class="empty-line">No schedule is set.</div>
                @endforelse
            </section>

            <section class="sec" aria-label="The last two weeks">
                <header class="sec-head"><h2>The last two weeks</h2>
                    <span class="legend"><i class="done"></i>Completed <i class="bad"></i>Failed</span>
                </header>
                <div class="bars" role="img" aria-label="Completed and failed work each day. The table below has the same figures.">
                    @foreach($this->daily as $day)
                        <div class="bar" style="--i: {{ $loop->index }}" title="{{ $day['day'] }}: {{ $day['completed'] }} completed, {{ $day['failed'] }} failed">
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
            </section>
        </aside>
    </div>
    @else
        @if($budget)
            <div class="desk-grid single" wire:poll.30s>
                <section class="sec" aria-label="This month">
                    <header class="sec-head"><h2>This month</h2><a class="btn quiet small" href="{{ route('usage') }}">{{ $budget['limit'] === null ? 'Set a limit' : 'Usage' }}</a></header>
                    <dl class="facts"><div class="row fig"><dt>Spent</dt><dd class="num" data-figure="spent">{{ number_format($budget['spent'], 2) }}</dd></div></dl>
                </section>
            </div>
        @endif
    @endunless
</div>
