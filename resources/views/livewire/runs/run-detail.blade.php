<x-sig.page :title="$this->run->summary ?: 'Run'" :sub="$this->run->deployment?->name">
    <x-slot:actions>
        <a class="btn quiet" href="{{ route('runs.index') }}">All runs</a>
        @if(! $this->run->isFinished() && auth()->user()->can('cancel', $this->run))
            <button type="button" class="btn" wire:click="cancel" wire:confirm="Cancel this run? It stops at its next step.">Cancel</button>
        @endif
        @if($this->diagnosis && $this->diagnosis['recoverable'] && auth()->user()->canInOrganization('agents.operate', $this->run->organization_id))
            <button type="button" class="btn primary" wire:click="retry">Try again</button>
        @endif
    </x-slot:actions>

    <div class="grid two" @unless($this->run->isFinished()) wire:poll.3s="refresh" @endunless>
        <div class="grid">
            @error('retry')<p class="notice stop" role="alert">{{ $message }}</p>@enderror

            @if($this->approval)
                <a class="notice hold" href="{{ route('governance.approvals', ['approval' => $this->approval->uuid]) }}">Waiting for a person: {{ $this->approval->title }}</a>
            @endif

            @if($this->diagnosis)
                <section class="card" aria-label="What went wrong">
                    <div class="card-head"><h2>What went wrong</h2><x-sig.lamp :state="$this->run->status" /></div>
                    <div class="card-body">
                        <dl class="pairs wide">
                            <div><dt>What</dt><dd>{{ $this->diagnosis['what'] }}</dd></div>
                            <div><dt>Where</dt><dd>{{ $this->diagnosis['where'] }}</dd></div>
                            <div><dt>Can it be recovered</dt><dd>{{ $this->diagnosis['recoverable'] ? 'Yes' : 'Not from here' }}</dd></div>
                            <div><dt>What to do</dt><dd>{{ $this->diagnosis['action'] }}</dd></div>
                        </dl>
                    </div>
                </section>
            @endif

            @if($this->run->output)
                <section class="card" aria-label="Result">
                    <div class="card-head"><h2>Result</h2></div>
                    <div class="card-body said result">{{ $this->run->output }}</div>
                </section>
            @endif

            <section class="card" aria-label="What happened">
                <div class="card-head"><h2>What happened</h2></div>
                <div class="card-body"><x-run.timeline :run="$this->run" /></div>
            </section>
        </div>

        <aside class="grid">
            <section class="card" aria-label="About this run">
                <div class="card-head"><h2>About this run</h2>@unless($this->diagnosis)<x-sig.lamp :state="$this->run->status" />@endunless</div>
                <div class="card-body">
                    <dl class="pairs">
                        <div><dt>Started by</dt><dd>{{ $this->run->initiator?->name ?? str_replace('_', ' ', $this->run->trigger_type) }}</dd></div>
                        <div><dt>Agent version</dt><dd>{{ $this->run->configVersion?->number }}</dd></div>
                        <div><dt>Started</dt><dd>{{ $this->run->started_at?->format('j M Y, H:i:s') ?? 'Not yet' }}</dd></div>
                        <div><dt>Ended</dt><dd>{{ $this->run->finished_at?->format('j M Y, H:i:s') ?? 'Not yet' }}</dd></div>
                        <div><dt>Model turns</dt><dd>{{ $this->run->steps_used }}</dd></div>
                        <div><dt>Tool calls</dt><dd>{{ $this->run->tool_calls_used }}</dd></div>
                        <div><dt>Tokens</dt><dd>{{ number_format($this->run->tokens_used) }}</dd></div>
                        <div><dt>Cost</dt><dd>{{ (float) $this->run->cost > 0 ? number_format((float) $this->run->cost, 4) : 'Not known' }}</dd></div>
                    </dl>
                    <details class="tech">
                        <summary>Technical details</summary>
                        <dl class="pairs">
                            <div><dt>Run</dt><dd>{{ $this->run->uuid }}</dd></div>
                            <div><dt>Error code</dt><dd>{{ $this->run->error_code ?? 'None' }}</dd></div>
                            <div><dt>Exposed to</dt><dd>{{ implode(', ', array_filter([$this->run->taint & 1 ? 'outside content' : null, $this->run->taint & 2 ? 'sensitive data' : null])) ?: 'Neither outside content nor sensitive data' }}</dd></div>
                            <div><dt>Input</dt><dd>{{ \Illuminate\Support\Str::limit((string) $this->run->input, 2000) }}</dd></div>
                        </dl>
                    </details>
                </div>
            </section>
        </aside>
    </div>
</x-sig.page>
