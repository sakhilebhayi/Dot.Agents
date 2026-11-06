<x-sig.page title="Templates and tools" sub="Starting points that work, and everything an agent can be given.">
    <x-slot:actions>
        @can('create', [\App\Models\AgentDeployment::class, \App\Support\TenantContext::id()])
            <a class="btn" href="{{ route('agents.create') }}">New agent</a>
        @endcan
    </x-slot:actions>

    <div class="tabs" role="tablist" aria-label="Library">
        <button type="button" class="tab" role="tab" wire:click="setTab('official')" aria-selected="{{ $tab === 'official' ? 'true' : 'false' }}">Official templates</button>
        <button type="button" class="tab" role="tab" wire:click="setTab('own')" aria-selected="{{ $tab === 'own' ? 'true' : 'false' }}">Your templates</button>
        <button type="button" class="tab" role="tab" wire:click="setTab('tools')" aria-selected="{{ $tab === 'tools' ? 'true' : 'false' }}">Tools</button>
    </div>

    @if($tab === 'official')
        <section class="card" aria-label="Official templates">
            <div class="card-head roster-tools">
                <select class="select" aria-label="Category" wire:model.live="category">
                    <option value="">Every category</option>
                    @foreach($this->categories as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
                </select>
            </div>
            @foreach($this->templates as $key => $template)
                <div class="row template" wire:key="template-{{ $key }}">
                    <div class="row-main">
                        <b>{{ $template['name'] }}</b>
                        <p>{{ $template['summary'] }}</p>
                        <span>{{ $template['category'] }}. Needs: {{ $template['needs'] }}</span>
                        <span>Uses: {{ collect($template['tools'])->pluck('key')->map(fn ($tool) => str_replace('_', ' ', $tool))->implode(', ') ?: 'no tools' }}. On its own: {{ \App\Services\Agents\AgentConfig::AUTONOMY[$template['autonomy']][0] }}.</span>
                    </div>
                    <a class="btn small" href="{{ route('agents.create', ['way' => 'template']) }}">Use</a>
                </div>
            @endforeach
        </section>
    @elseif($tab === 'own')
        <section class="card" aria-label="Your templates">
            @forelse($this->own as $template)
                <div class="row template" wire:key="own-{{ $template->id }}">
                    <div class="row-main"><b>{{ $template->name }}</b><span>Version {{ $template->version }}. {{ $template->summary }}</span></div>
                    <a class="btn small" href="{{ route('agents.create', ['way' => 'template']) }}">Use</a>
                    <button type="button" class="btn quiet small" wire:click="removeOwn(@js($template->name))" wire:confirm="Remove this template? Agents made from it stay.">Remove</button>
                </div>
            @empty
                <div class="empty"><b>No templates of your own yet.</b> Open an agent and save it as a template to reuse it across this business.</div>
            @endforelse
        </section>
    @else
        <section class="card" aria-label="Tools">
            @foreach($this->tools as ['tool' => $tool, 'missing' => $missing])
                <div class="row template" wire:key="tool-{{ $tool->key }}">
                    <div class="row-main">
                        <b>{{ $tool->label }}</b>
                        <p>{{ $tool->description }}</p>
                        <span>{{ $tool->category }}. Reaches: {{ $tool->accesses }}</span>
                        <span>{{ $tool->writesState ? 'Changes things, so it asks a person unless the agent is trusted to act alone.' : 'Only reads.' }}@if($tool->ceiling) Always goes to a person.@endif</span>
                    </div>
                    <span class="chip {{ $missing ? '' : 'clear' }}" title="{{ $missing }}">{{ $missing ? 'Not connected' : 'Available' }}</span>
                </div>
            @endforeach
        </section>
    @endif
</x-sig.page>
