<x-sig.page title="Custom skills" sub="Add your own API or automation as a skill. Your code stays on your own server, and Dot.Agents calls it by web address." narrow>
    <x-slot:actions>
        @writable
            @if(! $showForm)
                <button type="button" class="btn primary" wire:click="$set('showForm', true)"><x-sig.icon name="plus" />New custom skill</button>
            @endif
        @endwritable
    </x-slot:actions>

    <div class="grid org-stack">
        @if($showForm)
            @writable
                <section class="card" aria-label="New custom skill">
                    <div class="card-head"><h2>New custom skill</h2></div>
                    <div class="card-body">
                        <div class="pair-fields">
                            <label class="field">
                                <span>Name</span>
                                <input class="input" type="text" wire:model="name" placeholder="Inventory lookup" aria-required="true">
                                @error('name')<p class="error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span>Timeout (seconds)</span>
                                <input class="input num" type="number" min="1" max="120" wire:model="webhookTimeoutSeconds">
                                @error('webhookTimeoutSeconds')<p class="error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                        <label class="field">
                            <span>Webhook URL</span>
                            <input class="input" type="url" wire:model="webhookUrl" placeholder="https://your-api.example.com/skills/inventory-lookup" aria-required="true">
                            @error('webhookUrl')<p class="error">{{ $message }}</p>@enderror
                            <small>Called with <code class="mono">{ skill_key, input, context }</code> as JSON. Expects <code class="mono">{ status, output, confidence, findings, recommendations }</code> back.</small>
                        </label>
                        <label class="field">
                            <span>Description</span>
                            <input class="input" type="text" wire:model="description" placeholder="What does this skill do?">
                        </label>
                        <div class="answer-main">
                            <button type="button" class="btn primary" wire:click="create" wire:loading.attr="disabled">Create</button>
                            <button type="button" class="btn quiet" wire:click="$set('showForm', false)">Cancel</button>
                        </div>
                    </div>
                </section>
            @endwritable
        @endif

        <section class="card" aria-label="Your custom skills">
            <div class="card-head"><h2>Your custom skills</h2></div>
            <div>
                @forelse($this->customSkills as $skill)
                    <div class="row org-row" wire:key="skill-{{ $skill->id }}">
                        <div class="row-main">
                            <b>{{ $skill->name }}</b>
                            <span class="mono" title="{{ $skill->webhook_url }}">{{ $skill->webhook_url }}</span>
                        </div>
                        <x-sig.lamp :state="$skill->is_active ? 'active' : 'disabled'" />
                        @writable
                            <div class="org-end">
                                <button type="button" class="btn quiet small" aria-label="{{ $skill->is_active ? 'Disable' : 'Enable' }} {{ $skill->name }}" wire:click="toggleActive({{ $skill->id }})">{{ $skill->is_active ? 'Disable' : 'Enable' }}</button>
                                <button type="button" class="btn quiet small" aria-label="Delete {{ $skill->name }}" wire:click="delete({{ $skill->id }})" wire:confirm="Delete this custom skill? Agents assigned this skill will no longer be able to use it.">Delete</button>
                            </div>
                        @endwritable
                    </div>
                @empty
                    <div class="empty"><b>No custom skills yet.</b> Create one to connect your own web address.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-sig.page>
