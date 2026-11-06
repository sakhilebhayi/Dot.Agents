<x-sig.page title="Workflows" sub="Several steps of work, by agents, tools and people, that only count as done when all of it is.">
    <x-slot:actions>
        @writable
            <button type="button" class="btn primary" wire:click="openCreateModal">New workflow</button>
        @endwritable
    </x-slot:actions>

    @if(session('status'))<div class="notice clear" role="status">{{ session('status') }}</div>@endif

    @if($showCreateModal)
        <form class="card" wire:submit="createWorkflow" style="margin-bottom: var(--gap)">
            <div class="card-head"><h2>New workflow</h2></div>
            <div class="card-body rule-form">
                <label class="field"><span>Name</span><input class="input" wire:model="newName" maxlength="100">@error('newName')<p class="error">{{ $message }}</p>@enderror</label>
                <label class="field"><span>What it is for</span><input class="input" wire:model="newDescription" maxlength="300"></label>
                <button type="submit" class="btn primary">Create</button>
            </div>
        </form>
    @endif

    <div class="grid two">
        <section class="card" aria-label="Your workflows">
            <div class="card-head"><h2>Your workflows</h2></div>
            @forelse($workflows as $workflow)
                <div class="row" wire:key="workflow-{{ $workflow->id }}">
                    <a class="row-main" href="{{ route('workflows.builder', $workflow) }}">
                        <b>{{ $workflow->name }}</b>
                        <span>{{ $workflow->nodes_count }} {{ Str::plural('step', $workflow->nodes_count) }}</span>
                    </a>
                    <x-sig.lamp :state="$workflow->status" />
                    @writable
                        <button type="button" class="btn quiet small" wire:click="deleteWorkflow({{ $workflow->id }})" wire:confirm="Delete {{ $workflow->name }}? This cannot be undone.">Delete</button>
                    @endwritable
                </div>
            @empty
                <div class="empty"><b>No workflows yet.</b> Start from a template, or build your own.</div>
            @endforelse
        </section>

        <section class="card" aria-label="Templates">
            <div class="card-head"><h2>Start from a template</h2></div>
            @foreach($this->templates as $key => $template)
                <div class="row template" wire:key="template-{{ $key }}">
                    <div class="row-main"><b>{{ $template['name'] }}</b><p class="muted">{{ $template['summary'] }}</p></div>
                    @writable<button type="button" class="btn small" wire:click="useTemplate('{{ $key }}')">Use</button>@endwritable
                </div>
            @endforeach
        </section>
    </div>
</x-sig.page>
