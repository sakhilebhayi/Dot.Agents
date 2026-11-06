<section class="grid flow-runs" aria-label="Runs" @if($this->execution && in_array($this->execution->status, \App\Models\WorkflowExecution::OPEN, true)) wire:poll.3s="refresh" @endif>
    <div class="desk">
        <div class="card">
            <div class="card-head"><h2>Runs</h2></div>
            @forelse($this->executions as $run)
                <button type="button" class="row desk-row" wire:key="run-{{ $run->id }}" wire:click="open({{ $run->id }})" @if($openId === $run->id) aria-current="true" @endif>
                    <span class="row-main"><b>{{ $run->created_at->format('j M, H:i') }}</b><span>{{ $run->current_step }} of {{ $run->total_steps }} steps</span></span>
                    <x-sig.lamp :state="$run->status" />
                </button>
            @empty
                <div class="empty">This workflow has not run yet.</div>
            @endforelse
        </div>

        @if($this->execution)
            <div class="card">
                <div class="card-head">
                    <h2>Steps</h2>
                    @if(in_array($this->execution->status, \App\Models\WorkflowExecution::OPEN, true))
                        <button type="button" class="btn quiet small" wire:click="cancel" wire:confirm="Cancel this run? Steps under way stop at their next step.">Cancel run</button>
                    @endif
                </div>
                @if($this->execution->error_message)<p class="notice {{ $this->execution->status === 'partial' ? 'hold' : 'stop' }} flow-notice">{{ $this->execution->error_message }}</p>@endif
                @foreach($this->steps as $step)
                    <div wire:key="step-{{ $step->id }}">
                        <div class="row">
                            <button type="button" class="row-main flow-step-open" wire:click="openStep({{ $step->id }})" aria-expanded="{{ $openStepId === $step->id ? 'true' : 'false' }}">
                                <b>{{ $step->label }}</b>
                                <span>{{ $step->error_message ?: ($step->attempts > 1 ? "{$step->attempts} attempts" : ($step->kind === 'approval' ? 'A person approves' : ($step->run?->deployment?->name ?? ''))) }}</span>
                            </button>
                            @if($step->status === 'failed' && in_array($this->execution->status, ['failed', 'partial'], true))
                                <button type="button" class="btn quiet small" wire:click="retry({{ $step->id }})">Try again</button>
                            @endif
                            <x-sig.lamp :state="$step->status" />
                        </div>
                        @if($openStepId === $step->id)
                            <div class="card-body flow-io">
                                @if($step->input)<h3>What went in</h3><pre>{{ $step->input }}</pre>@endif
                                @if($step->output)<h3>What came out</h3><pre>{{ $step->output }}</pre>@endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
