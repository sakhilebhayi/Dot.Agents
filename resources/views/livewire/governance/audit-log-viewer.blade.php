<x-sig.page title="Audit log" sub="Every recorded action by agents, people and the system.">
    <x-slot:actions>
        <button type="button" class="btn" wire:click="export('csv')">Export CSV</button>
        <button type="button" class="btn" wire:click="export('json')">Export JSON</button>
    </x-slot:actions>

    <div class="loading-bar" wire:loading role="status" aria-label="Loading"></div>

    <section class="card" aria-label="Audit log">
        <div class="filters">
            <label class="field filters-wide">
                <span>Search</span>
                <x-dot.filter-search name="search" label="Search logs" placeholder="Search logs" wire:model.live.debounce.300ms="search" />
            </label>
            <label class="field">
                <span>Category</span>
                <select class="select" wire:model.live="filterCategory">
                    <option value="">All categories</option>
                    <option value="agent_action">Agent actions</option>
                    <option value="user_action">User actions</option>
                    <option value="security">Security</option>
                    <option value="system">System</option>
                    <option value="governance">Governance</option>
                </select>
            </label>
            <label class="field">
                <span>Risk</span>
                <select class="select" wire:model.live="filterRisk">
                    <option value="">All risk levels</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                    <option value="info">Info</option>
                </select>
            </label>
            <label class="field">
                <span>Agent</span>
                <select class="select" wire:model.live="filterAgent">
                    <option value="">All agents</option>
                    @foreach($this->deployments as $dep)
                        <option value="{{ $dep->id }}">{{ $dep->display_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>From</span>
                <input class="input num" type="date" wire:model.live="dateFrom">
            </label>
            <label class="field">
                <span>To</span>
                <input class="input num" type="date" wire:model.live="dateTo">
            </label>
            <label class="check-line">
                <input type="checkbox" wire:model.live="showFlagged">
                <span>Flagged only</span>
                @if($this->flaggedCount > 0)
                    <span class="chip stop num">{{ $this->flaggedCount }}</span>
                @endif
            </label>
        </div>

        @if($this->logs->isEmpty())
            <div class="empty">No audit log entries found matching your filters.</div>
        @else
            <div class="table-scroll" tabindex="0" role="region" aria-label="Table, scrolls sideways">
                <table class="table wide">
                    <thead>
                        <tr><th>Time</th><th>Event</th><th>Actor</th><th>Category</th><th>Risk</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($this->logs as $log)
                            @php
                                [$actor, $kind] = match (true) {
                                    (bool) $log->agent_deployment_id => [$log->deployment?->display_name ?? 'Agent', 'Agent'],
                                    (bool) $log->user_id => [$log->user?->name ?? 'User', 'User'],
                                    default => ['System', 'System'],
                                };
                            @endphp
                            <tr wire:key="log-{{ $log->id }}">
                                <td class="cell-time">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <span class="cell-title">{{ $log->event }}</span>
                                    @if($log->description)<span class="cell-sub" title="{{ $log->description }}">{{ $log->description }}</span>@endif
                                </td>
                                <td>
                                    {{ $actor }}
                                    @if($kind !== $actor)<span class="cell-sub">{{ $kind }}</span>@endif
                                </td>
                                <td><span class="chip">{{ ucfirst(str_replace('_', ' ', $log->event_category ?? 'system')) }}</span></td>
                                <td><x-approval.risk :level="$log->risk_level ?? 'info'" /></td>
                                <td>
                                    @if($log->flagged)
                                        <span class="lamp stop"><i aria-hidden="true"></i>Flagged</span>
                                    @else
                                        <span class="lamp off"><i aria-hidden="true"></i>Normal</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($this->logs->hasPages())
            <div class="card-body">{{ $this->logs->links() }}</div>
        @endif
    </section>
</x-sig.page>
