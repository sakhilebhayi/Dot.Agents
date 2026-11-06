@php $definitions = \App\Services\Insights\RunMetrics::DEFINITIONS; @endphp

<x-sig.page title="Usage" sub="What agents did and what it cost, counted from the record of each run.">
    <x-slot:actions>
        <select class="select desk-filter" aria-label="Period" wire:change="setDays($event.target.value)">
            @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $value => $label)<option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>@endforeach
        </select>
    </x-slot:actions>

    @if($this->summary['unpriced'] > 0)
        <p class="notice hold">{{ $this->summary['unpriced'] }} model {{ Str::plural('call', $this->summary['unpriced']) }} had no price on file, so the cost below is missing them.</p>
    @endif

    <div class="stats" style="margin-bottom: var(--gap)">
        <div class="stat" title="{{ $definitions['finished'] }}"><span>Finished</span><b>{{ number_format($this->summary['finished']) }}</b></div>
        <div class="stat" title="{{ $definitions['rate'] }}"><span>Completed</span><b>{{ $this->summary['rate'] === null ? '–' : $this->summary['rate'].'%' }}</b></div>
        <div class="stat" title="{{ $definitions['cost'] }}"><span>Cost</span><b>{{ number_format($this->summary['cost'], 2) }}</b></div>
        <div class="stat" title="{{ $definitions['wasted'] }}"><span>Of that, no result</span><b>{{ number_format($this->summary['wasted'], 2) }}</b></div>
        <div class="stat"><span>Tokens</span><b>{{ number_format($this->summary['tokens']) }}</b></div>
        <div class="stat"><span>Tool calls</span><b>{{ number_format($this->summary['tool_calls']) }}</b></div>
    </div>

    <div class="grid two">
        <div class="grid">
            <section class="card" aria-label="By agent">
                <div class="card-head"><h2>By agent</h2></div>
                @if($this->agents->isEmpty())
                    <div class="empty">No finished work in this period.</div>
                @else
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Table, scrolls sideways">
                        <table class="table">
                            <thead><tr><th>Agent</th><th class="right">Runs</th><th class="right">Completed</th><th class="right">Tokens</th><th class="right">Cost</th></tr></thead>
                            <tbody>
                                @foreach($this->agents as $row)
                                    <tr><td>{{ $row['agent']?->name }}</td><td class="right num">{{ $row['runs'] }}</td><td class="right num">{{ $row['rate'] === null ? '–' : $row['rate'].'%' }}</td><td class="right num">{{ number_format($row['tokens']) }}</td><td class="right num">{{ number_format($row['cost'], 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card" aria-label="By model">
                <div class="card-head"><h2>By model</h2></div>
                @if($this->models->isEmpty())
                    <div class="empty">No model has been called in this period.</div>
                @else
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Table, scrolls sideways">
                        <table class="table">
                            <thead><tr><th>Model</th><th class="right">Calls</th><th class="right">Tokens in</th><th class="right">Tokens out</th><th class="right">Cost</th></tr></thead>
                            <tbody>
                                @foreach($this->models as $row)
                                    <tr><td>{{ $row->model }}</td><td class="right num">{{ $row->calls }}</td><td class="right num">{{ number_format($row->input_tokens) }}</td><td class="right num">{{ number_format($row->output_tokens) }}</td><td class="right num">{{ $row->unpriced == $row->calls ? 'No price' : number_format((float) $row->cost, 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="grid">
            <form class="card" wire:submit="saveBudget">
                <div class="card-head"><h2>Monthly budget</h2></div>
                <div class="card-body">
                    @if(session('saved'))<p class="muted" role="status">{{ session('saved') }}</p>@endif
                    <p class="muted budget-spent">Spent this month: <span class="num">{{ number_format($this->spent, 2) }}</span>@if($this->budget) of <span class="num">{{ number_format((float) $this->budget->monthly_limit, 2) }}</span>@endif</p>
                    @if(auth()->user()->canInOrganization('billing.manage', \App\Support\TenantContext::id()))
                        <label class="field"><span>Limit</span><input class="input num" type="number" step="0.01" min="1" wire:model="limit">@error('limit')<p class="error">{{ $message }}</p>@enderror</label>
                        <label class="field"><span>Warn at (percent)</span><input class="input num" type="number" min="1" max="100" wire:model="warnAt"></label>
                        <label class="check-line"><input type="checkbox" wire:model="stopAtLimit"><span>Stop new work at the limit</span></label>
                        <button type="submit" class="btn" style="margin-top: 12px">Save budget</button>
                    @endif
                </div>
            </form>

            <section class="card" aria-label="Definitions">
                <div class="card-head"><h2>How these are counted</h2></div>
                <dl class="card-body defs">
                    @foreach(['Finished' => 'finished', 'Completed' => 'rate', 'Cost' => 'cost', 'No result' => 'wasted'] as $label => $key)
                        <dt>{{ $label }}</dt><dd>{{ $definitions[$key] }}</dd>
                    @endforeach
                </dl>
            </section>

            <section class="card" aria-label="Knowledge">
                <div class="card-head"><h2>Knowledge</h2></div>
                <div class="card-body muted"><span class="num">{{ number_format($this->passages) }}</span> passages stored.</div>
            </section>
        </div>
    </div>
</x-sig.page>
