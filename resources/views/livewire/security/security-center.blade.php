<x-sig.page title="Security" sub="Problems found in your agents and what was done about them. A health check runs every 15 minutes and can suspend an agent by itself.">
    @writable
        <x-slot:actions>
            <button type="button" class="btn primary" wire:click="runDISCheck" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="runDISCheck">Run health check</span>
                <span wire:loading wire:target="runDISCheck">Checking</span>
            </button>
        </x-slot:actions>
    @endwritable

    @if(session('status'))
        <div class="notice clear" role="status">{{ session('status') }}</div>
    @endif

    <div class="stats">
        <div class="stat"><span>Events in 24 hours</span><b>{{ number_format($this->stats['total_24h']) }}</b></div>
        <div class="stat"><span>Critical and open</span><b>{{ number_format($this->stats['critical']) }}</b></div>
        <div class="stat"><span>Suspended agents</span><b>{{ number_format($this->stats['quarantined']) }}</b></div>
    </div>

    <div class="grid stack">
        @if($disReport)
            @php
                $checked = $disReport['total_agents'] ?? 0;
                $found = $disReport['events'] ?? [];
            @endphp
            <section class="card" aria-label="Health check report" role="status">
                <div class="card-head has-tools">
                    <h2>Health check report</h2>
                    <span class="muted"><span class="num">{{ $checked }}</span> {{ Str::plural('agent', $checked) }} checked, <span class="num">{{ count($found) }}</span> {{ Str::plural('event', count($found)) }}</span>
                </div>

                @forelse($found as $event)
                    <div class="row long">
                        <span @class(['chip', 'stop' => in_array($event['severity'] ?? '', ['critical', 'error']), 'hold' => ! in_array($event['severity'] ?? '', ['critical', 'error'])])>{{ ucfirst($event['severity'] ?? 'warning') }}</span>
                        <div class="row-main">
                            <b>{{ $event['message'] ?? ucfirst(str_replace('_', ' ', $event['type'] ?? 'Unknown issue')) }}</b>
                            @if(!empty($event['recommendation']))<span>{{ str_replace(" \u{2014} ", ': ', $event['recommendation']) }}</span>@endif
                        </div>
                    </div>
                @empty
                    <div class="empty">{{ $checked > 0 ? 'The check found no problems.' : 'No active agents to check.' }}</div>
                @endforelse

                @if(($disReport['quarantined'] ?? 0) > 0)
                    <div class="row long">
                        <span class="lamp stop"><i aria-hidden="true"></i><span class="num">{{ $disReport['quarantined'] }}</span> {{ Str::plural('agent', $disReport['quarantined']) }} suspended automatically</span>
                    </div>
                @endif
            </section>
        @endif

        <section class="card halt" aria-label="Halt all workflows">
            <div class="card-head"><h2>Halt all workflows</h2></div>
            <div class="card-body halt-body">
                <p class="muted">Stops every running workflow and pauses every active one. Use only in an active incident. Every halt is recorded in the audit log.</p>
                @writable
                <div>
                    <div class="halt-form">
                        <input class="input" type="text" aria-label="Type: HALT WORKFLOWS" wire:model="killSwitchConfirmation" placeholder="Type: HALT WORKFLOWS" autocomplete="off">
                        <button type="button" class="btn danger" wire:click="killAllWorkflows" wire:loading.attr="disabled" wire:confirm="This will immediately halt all running workflows. Confirm?">
                            <span wire:loading.remove wire:target="killAllWorkflows">Halt all workflows</span>
                            <span wire:loading wire:target="killAllWorkflows">Halting</span>
                        </button>
                    </div>
                    @error('killSwitchConfirmation')<p class="error">{{ $message }}</p>@enderror
                </div>
                @endwritable
            </div>
        </section>

        <section class="card" aria-label="Security events">
            <div class="card-head has-tools">
                <h2>Security events</h2>
                <div class="head-tools">
                    <select class="select desk-filter" aria-label="Severity" wire:model.live="filterSeverity">
                        <option value="">All severities</option>
                        <option value="critical">Critical</option>
                        <option value="error">Error</option>
                        <option value="warning">Warning</option>
                        <option value="info">Info</option>
                    </select>
                    <select class="select desk-filter" aria-label="Type" wire:model.live="filterType">
                        <option value="">All types</option>
                        <option value="prompt_injection">Prompt injection</option>
                        <option value="agent_drift">Agent drift</option>
                        <option value="delusion_detected">Delusion detected</option>
                        <option value="permission_abuse">Permission abuse</option>
                        <option value="autonomy_violation">Autonomy violation</option>
                    </select>
                </div>
            </div>

            @if($this->events->isEmpty())
                <div class="empty">No security events found.</div>
            @else
                <div class="table-scroll">
                    <table class="table wide">
                        <thead>
                            <tr><th>Time</th><th>Event</th><th>Severity</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach($this->events as $event)
                                @php
                                    $severityTone = ['critical' => 'stop', 'error' => 'stop', 'warning' => 'hold'][$event->severity] ?? '';
                                    $statusLamp = ['open' => 'hold', 'investigating' => 'running', 'resolved' => 'clear'][$event->status] ?? 'off';
                                @endphp
                                <tr wire:key="event-{{ $event->id }}">
                                    <td class="cell-time">{{ $event->created_at->format('M j H:i:s') }}</td>
                                    <td>
                                        <span class="cell-title">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
                                        @if($event->description)<span class="cell-sub" title="{{ $event->description }}">{{ $event->description }}</span>@endif
                                    </td>
                                    <td><span class="chip {{ $severityTone }}">{{ ucfirst($event->severity) }}</span></td>
                                    <td><span class="lamp {{ $statusLamp }}"><i aria-hidden="true"></i>{{ ucfirst(str_replace('_', ' ', $event->status)) }}</span></td>
                                    <td>
                                        @if($event->status === 'open')
                                            <div class="cell-actions">
                                                @writable
                                                    <button type="button" class="btn small" wire:click="resolveEvent({{ $event->id }})">Resolve</button>
                                                @endwritable
                                                @if($event->agent_deployment_id)
                                                    @writable
                                                        <button type="button" class="btn small danger" wire:click="killDeployment({{ $event->agent_deployment_id }})" wire:confirm="Immediately suspend this agent? This cannot be undone without manual reactivation.">Suspend agent</button>
                                                    @endwritable
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($this->events->hasPages())
                <div class="card-body">{{ $this->events->links() }}</div>
            @endif
        </section>
    </div>
</x-sig.page>
