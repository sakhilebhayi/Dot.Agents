<x-sig.page title="New agent" sub="An agent starts as a draft. Nothing runs until you publish it." narrow>
    <div class="tabs" role="tablist" aria-label="How to start">
        @foreach(['describe' => 'Describe it', 'template' => 'Start from a template', 'build' => 'Build it yourself', 'import' => 'Import'] as $key => $label)
            <button type="button" class="tab" role="tab" wire:click="setWay('{{ $key }}')" aria-selected="{{ $way === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if($way === 'describe')
        <form class="card" wire:submit="draft">
            <div class="card-body">
                <label class="field">
                    <span>What should this agent do?</span>
                    <textarea class="textarea" rows="5" wire:model="description" placeholder="Check supplier invoices against our payment terms, add up the totals and tell accounts when something does not match."></textarea>
                    @error('description')<p class="error">{{ $message }}</p>@enderror
                </label>
                <button type="submit" class="btn primary" wire:loading.attr="disabled">Draft a setup</button>
            </div>
        </form>

        @if($proposal)
            <section class="card proposal" aria-label="Proposed setup">
                <div class="card-head">
                    <h2>{{ $proposal['name'] }}</h2>
                    <span class="chip">{{ $proposal['by'] === 'model' ? 'Drafted by AI' : 'Drafted from your words' }}</span>
                </div>
                <div class="card-body plate">
                    @if($proposal['by'] === 'rules')
                        <p class="muted">No AI provider answered, so this was built by matching the words you used. Check it closely.</p>
                    @endif
                    <dl class="pairs wide">
                        <div><dt>Role</dt><dd>{{ $proposal['role'] }}</dd></div>
                        <div><dt>Responsible for</dt><dd>{{ $proposal['responsibility'] }}</dd></div>
                        <div><dt>Instructions</dt><dd>{{ $proposal['instructions'] }}</dd></div>
                        <div><dt>Tools</dt><dd>{{ $proposal['tools'] === [] ? 'None yet' : implode(', ', $proposal['tools']) }}</dd></div>
                        <div><dt>On its own</dt><dd>{{ \App\Services\Agents\AgentConfig::AUTONOMY[$proposal['autonomy']][0] }}</dd></div>
                    </dl>
                    <div><button type="button" class="btn primary" wire:click="acceptProposal">Use this and review it</button></div>
                </div>
            </section>
        @endif
    @elseif($way === 'template')
        <section class="card" aria-label="Templates">
            @foreach($this->templates as $key => $template)
                <div class="row template" wire:key="template-{{ $key }}">
                    <div class="row-main">
                        <b>{{ $template['name'] }}</b>
                        <p>{{ $template['summary'] }}</p>
                        <span>Needs: {{ $template['needs'] }}</span>
                    </div>
                    <button type="button" class="btn small" wire:click="useTemplate('{{ $key }}')">Use</button>
                </div>
            @endforeach
        </section>
    @elseif($way === 'build')
        <form class="card" wire:submit="build">
            <div class="card-body">
                <label class="field">
                    <span>Name</span>
                    <input class="input" wire:model="name" maxlength="100">
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </label>
                <label class="field">
                    <span>Role</span>
                    <input class="input" wire:model="role" maxlength="120" placeholder="Accounts assistant">
                    @error('role')<p class="error">{{ $message }}</p>@enderror
                </label>
                <label class="field">
                    <span>Responsible for</span>
                    <input class="input" wire:model="responsibility" maxlength="500">
                </label>
                <button type="submit" class="btn primary">Create draft</button>
            </div>
        </form>
    @else
        <form class="card" wire:submit="import">
            <div class="card-body">
                <label class="field">
                    <span>Exported agent file</span>
                    <input class="input" type="file" wire:model="package" accept=".json">
                    <small>Tools come across by name. Knowledge does not, so choose it again.</small>
                    @error('package')<p class="error">{{ $message }}</p>@enderror
                </label>
                <button type="submit" class="btn primary">Import</button>
            </div>
        </form>
    @endif
</x-sig.page>
