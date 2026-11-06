@php
    $agent = $this->deployment;
    $settings = ($agent->publishedVersion ?? $agent->draftVersion()->first())?->settings() ?? \App\Services\Agents\AgentConfig::defaults();
    $level = \App\Services\Agents\AgentConfig::AUTONOMY[(int) $agent->autonomy_level] ?? null;
@endphp

<x-sig.page :title="$agent->name" :sub="$agent->role ?: $agent->agent?->name">
    <x-slot:actions>
        @can('update', $agent)
            <a class="btn" href="{{ route('agents.setup', $agent) }}">Set up</a>
            <a class="btn" href="{{ route('agents.test', $agent) }}">Test</a>
            <a class="btn quiet" href="{{ route('agents.export', $agent) }}">Export</a>
            <button type="button" class="btn quiet" wire:click="duplicate">Duplicate</button>
        @endcan
        <a class="btn" href="{{ route('agents.scorecard', $agent) }}">Scorecard</a>
        @if($agent->status === 'active')<a class="btn primary" href="{{ route('agents.chat', $agent) }}">Talk</a>@endif
    </x-slot:actions>

    @if($agent->status !== 'active')
        <div class="notice hold" role="status">{{ $agent->name }} is {{ $agent->status }}, so it is not taking work.</div>
    @endif

    <div class="grid two">
        <div class="grid">
            <section class="card" aria-label="The job">
                <div class="card-head"><h2>The job</h2><x-sig.lamp :state="$agent->status" /></div>
                <div class="card-body">
                    <dl class="pairs wide">
                        <div><dt>Responsible for</dt><dd>{{ $agent->responsibility ?: 'Not written yet' }}</dd></div>
                        <div><dt>Catalogue agent</dt><dd>{{ $agent->agent?->name }}</dd></div>
                        <div><dt>Department</dt><dd>{{ $agent->department?->name ?? $agent->agent?->agentDepartment?->name ?? 'None' }}</dd></div>
                        <div><dt>Answers to</dt><dd>{{ $agent->owner?->name ?? $agent->deployedBy?->name ?? 'Nobody yet' }}</dd></div>
                        <div><dt>Autonomy</dt><dd>{{ $level ? "{$level[0]}. {$level[1]}" : 'Not set' }}</dd></div>
                        <div><dt>Running version</dt><dd>{{ $agent->publishedVersion ? 'Version '.$agent->publishedVersion->number : 'Not published' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="card" aria-label="Recent work">
                <div class="card-head"><h2>Recent work</h2></div>
                @forelse($this->recentRuns as $run)
                    <div class="row" wire:key="run-{{ $run->id }}">
                        <div class="row-main"><b>{{ $run->summary }}</b><span>{{ str_replace('_', ' ', $run->trigger_type) }}, {{ $run->created_at->diffForHumans() }}</span></div>
                        <x-sig.lamp :state="$run->status" />
                    </div>
                @empty
                    <div class="empty">It has not done any work yet.</div>
                @endforelse
            </section>

            @if($this->recentTasks->isNotEmpty())
                <section class="card" aria-label="Tasks">
                    <div class="card-head"><h2>Tasks</h2></div>
                    @foreach($this->recentTasks as $task)
                        <div class="row" wire:key="task-{{ $task->id }}">
                            <div class="row-main"><b>{{ $task->title }}</b><span>{{ $task->created_at->diffForHumans() }}</span></div>
                            <x-sig.lamp :state="$task->status" />
                        </div>
                    @endforeach
                </section>
            @endif

            <livewire:agents.agent-triggers target-type="agent" :target-id="$agent->id" />
            <livewire:agents.agent-versions :deployment-id="$agent->id" />
        </div>

        <div class="grid">
            @if($this->pendingApprovalsCount > 0)
                <a class="notice hold" href="{{ route('governance.approvals') }}">{{ $this->pendingApprovalsCount }} pending {{ Str::plural('approval', $this->pendingApprovalsCount) }}</a>
            @endif

            <section class="card" aria-label="Can use">
                <div class="card-head"><h2>Can use</h2></div>
                @forelse($settings['tools'] as $tool)
                    <div class="row"><div class="row-main"><b>{{ str_replace('_', ' ', $tool['key']) }}</b></div><span class="chip">{{ $tool['mode'] === 'approve' ? 'Asks first' : 'Allowed' }}</span></div>
                @empty
                    <div class="empty">No tools.</div>
                @endforelse
            </section>

            <section class="card" aria-label="Can read">
                <div class="card-head"><h2>Can read</h2></div>
                @forelse($this->knowledge as $base)
                    <div class="row"><div class="row-main"><b>{{ $base->name }}</b></div></div>
                @empty
                    <div class="empty">No knowledge.</div>
                @endforelse
            </section>

            @if($this->enabledSkills->isNotEmpty())
                <section class="card" aria-label="Skills">
                    <div class="card-head"><h2>Skills</h2></div>
                    @foreach($this->enabledSkills as $assignment)
                        <div class="row"><div class="row-main"><b>{{ $assignment->skill?->name }}</b></div></div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</x-sig.page>
