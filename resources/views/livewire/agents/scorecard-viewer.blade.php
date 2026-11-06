@php
    $agent = $this->deployment;
    $card = $this->scorecard;
    $range = fn ($scorecard) => $scorecard->period_start?->format('j M').' to '.$scorecard->period_end?->format('j M Y');
    $decisions = (int) ($card?->decisions_made ?? 0);
    $tasks = (int) ($card?->tasks_completed ?? 0) + (int) ($card?->tasks_failed ?? 0);
    $measures = [
        ['Accuracy', 'Decisions not flagged as high risk', $card?->accuracy_score, $decisions > 0],
        ['Compliance', 'Decisions that passed the compliance check', $card?->compliance_score, $decisions > 0],
        ['Trustworthiness', 'Decisions a person did not reject', $card?->trustworthiness_score, $decisions > 0],
        ['Risk impact', 'Loses 5 points for each high risk decision', $card?->risk_impact_score, $decisions > 0],
        ['Productivity', 'Tasks completed out of all tasks', $card?->productivity_score, $tasks > 0],
        ['Reliability', 'Falls as tasks fail', $card?->reliability_score, $tasks > 0],
    ];
@endphp

<x-sig.page :title="$agent->display_name" :sub="collect([$agent->agent?->agentDepartment?->name, $agent->agent?->name])->filter()->implode(', ')">
    @writable
        <x-slot:actions>
            <select class="select score-period" aria-label="Period to calculate" wire:model.live="period">
                <option value="7d">This week</option>
                <option value="30d">This month</option>
                <option value="90d">This quarter</option>
            </select>
            <button type="button" class="btn" wire:click="recalculate" wire:loading.attr="disabled" wire:target="recalculate">
                <span wire:loading.remove wire:target="recalculate">Recalculate</span>
                <span wire:loading wire:target="recalculate">Calculating</span>
            </button>
        </x-slot:actions>
    @endwritable

    @if(session('status'))<div class="notice clear" role="status">{{ session('status') }}</div>@endif

    <div class="grid">
        @if($card)
            <p class="muted">Scored from the tasks and decisions recorded from {{ $range($card) }}.</p>

            <div class="stats score-stats">
                <div class="stat"><span>Tasks completed</span><b>{{ number_format($card->tasks_completed ?? 0) }}</b></div>
                <div class="stat"><span>Tasks failed</span><b>{{ number_format($card->tasks_failed ?? 0) }}</b></div>
                <div class="stat"><span>Decisions</span><b>{{ number_format($decisions) }}</b></div>
                <div class="stat"><span>High risk decisions</span><b>{{ number_format($card->hallucinations_detected ?? 0) }}</b></div>
            </div>

            <section class="card" aria-label="Scores">
                <div class="card-head"><h2>Scores</h2></div>
                @if($decisions === 0 && $tasks === 0)
                    <div class="empty">
                        <b>Nothing to score in this period.</b>
                        No tasks or decisions were recorded.
                    </div>
                @else
                    @foreach($measures as [$label, $what, $score, $known])
                        <div class="row score" wire:key="score-{{ $loop->index }}">
                            <div class="row-main"><b>{{ $label }}</b><span>{{ $what }}</span></div>
                            @if($known)
                                <meter aria-hidden="true" min="0" max="100" low="60" high="80" optimum="100" value="{{ round($score ?? 0) }}"></meter>
                                <span class="row-end num score-figure">{{ round($score ?? 0) }}%</span>
                            @else
                                <span class="row-end score-figure">No data</span>
                            @endif
                        </div>
                    @endforeach
                @endif
            </section>
        @else
            <section class="card">
                <div class="empty">
                    <b>No scorecard yet.</b>
                    Scores come from the tasks and decisions recorded for this agent.
                    @writable
                        <div>
                            <button type="button" class="btn primary" wire:click="recalculate" wire:loading.attr="disabled" wire:target="recalculate">Generate scorecard</button>
                        </div>
                    @endwritable
                </div>
            </section>
        @endif

        @if($this->history->isNotEmpty())
            <section class="card" aria-label="Score history">
                <div class="card-head"><h2>Score history</h2></div>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Table, scrolls sideways">
                    <table class="table score-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="right">Accuracy</th>
                                <th class="right">Compliance</th>
                                <th class="right">Tasks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->history as $sc)
                                <tr wire:key="history-{{ $sc->id }}">
                                    <td>{{ $range($sc) }}</td>
                                    @if($sc->decisions_made)
                                        <td class="right num">{{ round($sc->accuracy_score ?? 0) }}%</td>
                                        <td class="right num">{{ round($sc->compliance_score ?? 0) }}%</td>
                                    @else
                                        <td class="right muted">No data</td>
                                        <td class="right muted">No data</td>
                                    @endif
                                    <td class="right num">{{ number_format($sc->tasks_completed ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-sig.page>
