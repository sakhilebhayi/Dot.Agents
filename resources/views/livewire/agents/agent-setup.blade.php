<x-sig.page :title="$name ?: 'Set up agent'" sub="Changes stay in a draft until you publish them.">
    <x-slot:actions>
        <a class="btn quiet" href="{{ route('agents.show', $this->agent) }}">Back to agent</a>
    </x-slot:actions>

    @if(session('saved'))
        <div class="notice clear" role="status">{{ session('saved') }}</div>
    @endif
    @error('publish')<div class="notice stop" role="alert">{{ $message }}</div>@enderror
    @error('config')<div class="notice stop" role="alert">{{ $message }}</div>@enderror

    <form class="setup" wire:submit="save">
        <div class="grid">
            <section class="card" aria-label="The job">
                <div class="card-head"><h2>The job</h2></div>
                <div class="card-body">
                    <div class="pair-fields">
                        <label class="field"><span>Name</span><input class="input" wire:model="name" maxlength="100">@error('name')<p class="error">{{ $message }}</p>@enderror</label>
                        <label class="field"><span>Role</span><input class="input" wire:model="role" maxlength="120"></label>
                    </div>
                    <label class="field"><span>Responsible for</span><input class="input" wire:model.blur="responsibility" maxlength="500"></label>
                    <label class="field"><span>Why it matters</span><input class="input" wire:model="config.purpose" maxlength="500"></label>
                    <label class="field">
                        <span>Instructions</span>
                        <textarea class="textarea" rows="7" wire:model.blur="config.instructions"></textarea>
                        <small>How to do the work, and when to stop and ask.</small>
                    </label>
                    <div class="pair-fields">
                        <label class="field">
                            <span>Tone</span>
                            <select class="select" wire:model="config.tone">
                                @foreach(\App\Services\Agents\AgentConfig::TONES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Who answers for it</span>
                            <select class="select" wire:model.live="ownerId">
                                <option value="">Nobody yet</option>
                                @foreach($this->people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <section class="card" aria-label="What it can read">
                <div class="card-head"><h2>What it can read</h2><a class="btn quiet small" href="{{ route('org.knowledge') }}">Manage knowledge</a></div>
                @forelse($this->bases as $base)
                    <label class="row check" wire:key="base-{{ $base->id }}">
                        <input type="checkbox" value="{{ $base->id }}" wire:model.live="config.knowledge">
                        <span class="row-main"><b>{{ $base->name }}</b><span>{{ $base->description ?: 'No description' }}</span></span>
                    </label>
                @empty
                    <div class="empty">There is no knowledge yet.</div>
                @endforelse
            </section>

            <section class="card" aria-label="What it can do">
                <div class="card-head"><h2>What it can do</h2></div>
                @foreach($this->catalogue as $tool)
                    <div class="row tool-row" wire:key="tool-{{ $tool->key }}">
                        <div class="row-main">
                            <b>{{ $tool->label }}</b>
                            <p>{{ $tool->description }}</p>
                            <span>Reaches: {{ $tool->accesses }}</span>
                        </div>
                        <select class="select tool-mode" aria-label="{{ $tool->label }}" wire:model.live="tools.{{ $tool->key }}">
                            <option value="off">Not allowed</option>
                            <option value="approve">Ask first</option>
                            <option value="auto">{{ $tool->writesState ? 'As autonomy allows' : 'Allowed' }}</option>
                        </select>
                    </div>
                @endforeach
            </section>

            <section class="card" aria-label="How much it may do alone">
                <div class="card-head"><h2>How much it may do alone</h2></div>
                @foreach(\App\Services\Agents\AgentConfig::AUTONOMY as $level => [$word, $meaning])
                    <label class="row check" wire:key="level-{{ $level }}">
                        <input type="radio" value="{{ $level }}" wire:model.live="config.autonomy">
                        <span class="row-main"><b>{{ $word }}</b><span>{{ $meaning }}</span></span>
                    </label>
                @endforeach
                <p class="card-body muted small-note">Moving money, final decisions about a person, and deleting what cannot be recovered always go to a person, at every level.</p>
            </section>

            <details class="card advanced">
                <summary class="card-head"><h2>Advanced</h2></summary>
                <div class="card-body">
                    <div class="pair-fields">
                        <label class="field">
                            <span>Model</span>
                            <select class="select" wire:model="config.model.model">
                                <option value="">Platform default</option>
                                @foreach($this->models as $id => $model)<option value="{{ $id }}">{{ $model['label'] }}: {{ $model['good_for'] }}</option>@endforeach
                            </select>
                            @if($this->models === [])<small>No AI provider is set up on this platform.</small>@endif
                        </label>
                        <label class="field">
                            <span>Result format</span>
                            <select class="select" wire:model="config.output.format">
                                @foreach(\App\Services\Agents\AgentConfig::OUTPUTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>If it fails</span>
                            <select class="select" wire:model="config.errors.on_failure">
                                @foreach(\App\Services\Agents\AgentConfig::ON_FAILURE as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="field"><span>Keep memories for (days)</span><input class="input num" type="number" min="1" max="3650" wire:model="config.memory.retention_days"></label>
                    </div>

                    <label class="row check bare"><input type="checkbox" wire:model.live="config.memory.long_term"><span class="row-main"><b>Long-term memory</b><span>It can save facts for later work. You can read and remove them under Memory.</span></span></label>

                    <h3 class="sub-head">Limits for one run</h3>
                    <div class="limit-grid">
                        @foreach(['steps' => 'Model turns', 'tool_calls' => 'Tool calls', 'tokens' => 'Tokens', 'cost' => 'Spending', 'seconds' => 'Seconds', 'retries' => 'Retries', 'concurrency' => 'At once'] as $key => $label)
                            <label class="field"><span>{{ $label }}</span><input class="input num" type="number" step="{{ $key === 'cost' ? '0.01' : '1' }}" wire:model="config.limits.{{ $key }}"></label>
                        @endforeach
                    </div>
                </div>
            </details>
        </div>

        <aside class="card setup-review" aria-label="Review">
            <div class="card-head"><h2>Review</h2></div>
            <div class="card-body">
                <p @class(['verdict', 'stop' => $this->review['verdict'] === 'not_ready', 'clear' => $this->review['verdict'] === 'ready'])>{{ $this->review['words'] }}</p>

                @if($this->review['findings'] !== [])
                    <ul class="findings">
                        @foreach($this->review['findings'] as $finding)
                            <li @class(['blocker' => $finding['level'] === 'blocker'])><b>{{ $finding['title'] }}</b>{{ $finding['fix'] }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="answer-main">
                    <button type="submit" class="btn" wire:loading.attr="disabled">Save draft</button>
                    <button type="button" class="btn primary" wire:click="publish" wire:loading.attr="disabled" @disabled($this->review['verdict'] === 'not_ready')>Publish</button>
                </div>
            </div>
        </aside>
    </form>
</x-sig.page>
