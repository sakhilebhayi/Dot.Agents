<x-sig.page title="Decision log" sub="What people decided about agent work, and why.">
    <x-slot:actions>
        <div class="segmented" role="group" aria-label="Period">
            @foreach(['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $key => $label)
                <button type="button" @class(['btn', 'small', 'quiet', 'is-on' => $timeframe === $key]) wire:click="$set('timeframe', '{{ $key }}')" wire:loading.attr="disabled" aria-pressed="{{ $timeframe === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    </x-slot:actions>

    <section class="card" aria-label="Decisions">
        <div class="filters">
            <label class="field">
                <span>Risk</span>
                <select class="select" wire:model.live="filterRisk">
                    <option value="">All risk levels</option>
                    <option value="high">High risk (70 and over)</option>
                    <option value="medium">Medium risk (40 to 69)</option>
                    <option value="low">Low risk (under 40)</option>
                </select>
            </label>
            <label class="field">
                <span>Agent</span>
                <select class="select" wire:model.live="filterDeployment">
                    <option value="">All agents</option>
                    @foreach($this->deployments as $dep)
                        <option value="{{ $dep->id }}">{{ $dep->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Review</span>
                <select class="select" wire:model.live="filterReviewRequired">
                    <option value="">All decisions</option>
                    <option value="1">Requires human review</option>
                    <option value="0">No review required</option>
                </select>
            </label>
        </div>

        @if($this->decisions->isEmpty())
            <div class="empty">No decision logs in this period.</div>
        @else
            <div class="table-scroll">
                <table class="table wide">
                    <thead>
                        <tr><th>Decision</th><th>Agent</th><th class="right">Risk</th><th class="right">Delusion risk</th><th>Date</th><th aria-label="Open"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($this->decisions as $decision)
                            @php
                                $risk = (float) ($decision->risk_score ?? 0);
                                $del = (float) ($decision->delusion_risk_score ?? 0);
                            @endphp
                            <tr wire:key="decision-{{ $decision->id }}">
                                <td>
                                    <span class="cell-title">{{ $decision->title }}</span>
                                    @if($decision->requires_human_review)<span class="lamp hold"><i aria-hidden="true"></i>Review required</span>@endif
                                </td>
                                <td>{{ $decision->deployment?->name ?? '-' }}</td>
                                <td class="right">
                                    @if($risk > 0)
                                        <span @class(['chip', 'num', 'stop' => $risk >= 70, 'hold' => $risk >= 40 && $risk < 70])>{{ number_format($risk, 0) }}</span>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                                <td class="right"><span @class(['chip', 'num', 'stop' => $del >= 60, 'hold' => $del >= 30 && $del < 60])>{{ number_format($del, 0) }}</span></td>
                                <td class="cell-time">{{ $decision->created_at->format('d M Y H:i') }}</td>
                                <td><button type="button" class="btn small" wire:click="view({{ $decision->id }})">View</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($this->decisions->hasPages())
            <div class="card-body">{{ $this->decisions->links() }}</div>
        @endif
    </section>

    @if($viewingId && $this->viewingDecision)
        @php
            $d = $this->viewingDecision;
            $dRisk = (float) ($d->risk_score ?? 0);
        @endphp
        <div class="sheet-wrap" wire:key="decision-detail" x-on:keydown.escape.window="$wire.closeDetail()">
            <div class="veil" wire:click.self="closeDetail"></div>
            <aside class="sheet" role="dialog" aria-modal="true" aria-label="Decision detail">
                <div class="sheet-head">
                    <b>Decision detail</b>
                    <span class="top-space"></span>
                    <button type="button" class="icon-btn" aria-label="Close detail" wire:click="closeDetail"><x-sig.icon name="close" /></button>
                </div>
                <div class="sheet-body plate sheet-plate">
                    <h2>{{ $d->title }}</h2>

                    <div class="stats side-stats">
                        <div class="stat"><span>Risk</span><b>{{ $dRisk > 0 ? number_format($dRisk, 1) : '-' }}</b><small>{{ $dRisk > 0 ? 'Stated by the agent' : 'None stated' }}</small></div>
                        <div class="stat"><span>Delusion risk</span><b>{{ number_format((float) $d->delusion_risk_score, 1) }}</b><small>Rule-based estimate</small></div>
                    </div>

                    @if($d->decision_summary)
                        <div>
                            <h3>Agent's summary</h3>
                            <p class="said">{{ $d->decision_summary }}</p>
                        </div>
                    @endif

                    @if($d->reasoning)
                        <div>
                            <h3>Agent's reasoning</h3>
                            <p class="said">{{ is_array($d->reasoning) ? json_encode($d->reasoning, JSON_PRETTY_PRINT) : $d->reasoning }}</p>
                        </div>
                    @endif

                    @if($d->requires_human_review)
                        <p class="notice hold">This decision required human review. {{ $d->human_reviewed ? 'Reviewed, verdict: '.ucfirst($d->human_verdict ?? 'pending') : 'Not yet reviewed.' }}</p>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</x-sig.page>
