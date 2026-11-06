@php $working = $this->runs->contains(fn ($run) => ! $run->isFinished()) || collect($this->results)->contains('status', 'running'); @endphp

<x-sig.page :title="'Test '.$this->agent->name" sub="Tests use the real model and your real knowledge. Nothing is changed and nothing leaves the platform." >
    <x-slot:actions>
        <a class="btn quiet" href="{{ route('agents.show', $this->agent) }}">Back to agent</a>
        @if($this->agent->status === 'draft')<button type="button" class="btn" wire:click="moveTo('testing')">Mark as testing</button>@endif
        @if($this->agent->status === 'testing')<button type="button" class="btn" wire:click="moveTo('staging')">Move to staging</button>@endif
    </x-slot:actions>

    <div @if($working) wire:poll.2s="refresh" @endif class="grid">
        <form class="card" wire:submit="start">
            <div class="card-head"><h2>Try it</h2><x-sig.lamp :state="$this->agent->status" /></div>
            <div class="card-body">
                <label class="field"><span>Ask it something</span><textarea class="textarea" rows="3" wire:model="input"></textarea>@error('input')<p class="error">{{ $message }}</p>@enderror</label>
                <div class="limit-grid bench-grid">
                    <label class="field">
                        <span>Version</span>
                        <select class="select" wire:model="versionId">
                            @foreach($this->versions as $version)<option value="{{ $version->id }}">Version {{ $version->number }}, {{ $version->state }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Compare with</span>
                        <select class="select" wire:model="compareId">
                            <option value="">Nothing</option>
                            @foreach($this->versions as $version)<option value="{{ $version->id }}">Version {{ $version->number }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Arrives as</span>
                        <select class="select" wire:model="simulate">
                            <option value="manual">A person asking</option>
                            <option value="webhook">Outside content</option>
                            <option value="schedule">A schedule</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Make a tool fail</span>
                        <select class="select" wire:model="failTool">
                            <option value="">None</option>
                            @foreach(($this->versions->first()?->settings()['tools'] ?? []) as $tool)<option value="{{ $tool['key'] }}">{{ str_replace('_', ' ', $tool['key']) }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <button type="submit" class="btn primary" wire:loading.attr="disabled">Run test</button>
            </div>
        </form>

        @if($this->runs->isNotEmpty())
            <div @class(['grid', 'bench-compare' => $this->runs->count() > 1])>
                @foreach($this->runs as $run)
                    <section class="card" wire:key="test-run-{{ $run->id }}" aria-label="Test result">
                        <div class="card-head"><h2>Version {{ $run->configVersion->number }}</h2><x-sig.lamp :state="$run->status" /></div>
                        <div class="card-body">
                            @if($run->output)<div class="said result">{{ $run->output }}</div>@endif
                            @if($run->error_message)<p class="error">{{ $run->error_message }}</p>@endif
                            <p class="muted bench-measures"><span class="num">{{ $run->steps_used }}</span> turns, <span class="num">{{ $run->tool_calls_used }}</span> tool calls, <span class="num">{{ number_format($run->tokens_used) }}</span> tokens</p>
                            <x-run.timeline :run="$run" />
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <section class="card" aria-label="Saved tests">
            <div class="card-head">
                <h2>Saved tests</h2>
                @if($this->cases->isNotEmpty())<button type="button" class="btn small" wire:click="runCases" wire:loading.attr="disabled">Run all on this version</button>@endif
            </div>
            @forelse($this->cases as $case)
                @php $result = $this->results[$case->id] ?? null; @endphp
                <div class="row template" wire:key="case-{{ $case->id }}">
                    <div class="row-main">
                        <b>{{ $case->name }}</b>
                        @if($result?->measures)<span>{{ $result->measures['seconds'] ?? 0 }} seconds, {{ number_format($result->measures['tokens']) }} tokens</span>@endif
                        @foreach($result?->checks ?? [] as $check)
                            @unless($check['passed'])<span class="error">{{ $check['name'] }}: not met</span>@endunless
                        @endforeach
                    </div>
                    <span class="chip {{ ['passed' => 'clear', 'failed' => 'stop', 'running' => 'running'][$result?->status] ?? '' }}">{{ ['passed' => 'Passed', 'failed' => 'Failed', 'running' => 'Running'][$result?->status] ?? 'Not run' }}</span>
                    <button type="button" class="btn quiet small" wire:click="removeCase({{ $case->id }})">Remove</button>
                </div>
            @empty
                <div class="empty"><b>No saved tests yet.</b> Save the questions this agent must always get right, then hold each new version to them.</div>
            @endforelse

            <details class="card-body add-case">
                <summary>Add a test</summary>
                <form wire:submit="saveCase" class="add-case-form">
                    <label class="field"><span>Name</span><input class="input" wire:model="caseName" maxlength="160">@error('caseName')<p class="error">{{ $message }}</p>@enderror</label>
                    <label class="field"><span>What it is asked</span><textarea class="textarea" rows="2" wire:model="caseInput"></textarea>@error('caseInput')<p class="error">{{ $message }}</p>@enderror</label>
                    <div class="pair-fields">
                        <label class="field"><span>Must say</span><input class="input" wire:model="mustSay"><small>Separate with commas.</small></label>
                        <label class="field"><span>Must not say</span><input class="input" wire:model="mustNotSay"></label>
                        <label class="field"><span>Must use the tools</span><input class="input" wire:model="mustUse"></label>
                    </div>
                    <button type="submit" class="btn">Save test</button>
                </form>
            </details>
        </section>
    </div>
</x-sig.page>
