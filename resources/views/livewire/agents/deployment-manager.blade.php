<x-sig.page title="Agents" sub="Who works here, what each one does, and how each one is doing.">
    <x-slot:actions>
        @can('create', [\App\Models\AgentDeployment::class, \App\Support\TenantContext::id()])
            <a class="btn primary" href="{{ route('agents.create') }}">New agent</a>
        @endcan
    </x-slot:actions>

    @if(session('status'))<div class="notice clear" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="notice stop" role="alert">{{ session('error') }}</div>@endif

    <section class="card">
        <div class="card-head roster-tools">
            <input class="input" type="search" aria-label="Search agents" placeholder="Search" wire:model.live.debounce.300ms="search">
            <select class="select" aria-label="Status" wire:model.live="filterStatus">
                <option value="">Every status</option>
                @foreach(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'decommissioned' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @forelse($deployments as $dep)
            <div class="row roster" wire:key="agent-{{ $dep->id }}">
                <a class="row-main" href="{{ route('agents.show', $dep) }}">
                    <b>{{ $dep->name }}</b>
                    <span>{{ $dep->role ?: ($dep->agent?->name ?? 'No role yet') }}</span>
                </a>
                <span class="roster-owner muted">{{ $dep->owner?->name ?? 'No owner' }}</span>
                <x-sig.lamp :state="$dep->status" class="roster-lamp" />
                <span class="roster-actions">
                    @if($dep->status === 'active')
                        <a class="btn quiet small" href="{{ route('agents.chat', $dep) }}">Talk</a>
                        @can('update', $dep)
                            <button type="button" class="btn quiet small" wire:click="pauseDeployment({{ $dep->id }})" wire:confirm="Pause {{ $dep->name }}? Work under way stops at its next step.">Pause</button>
                        @endcan
                    @elseif($dep->status === 'paused')
                        @can('update', $dep)
                            <button type="button" class="btn quiet small" wire:click="resumeDeployment({{ $dep->id }})">Resume</button>
                        @endcan
                    @elseif($dep->status === 'draft')
                        <a class="btn quiet small" href="{{ route('agents.setup', $dep) }}">Set up</a>
                    @endif
                </span>
            </div>
        @empty
            <div class="empty">
                <b>{{ $search || $filterStatus ? 'No agent matches that.' : 'No agents yet.' }}</b>
                An agent has a job, an owner, and only the tools you give it.
            </div>
        @endforelse

        @if($deployments->hasPages())
            <div class="card-body">{{ $deployments->links() }}</div>
        @endif
    </section>
</x-sig.page>
