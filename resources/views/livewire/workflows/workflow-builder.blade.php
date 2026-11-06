<div class="page wide-page" x-data="{ asking: false, request: '' }">
    <div class="page-head">
        <div>
            <h1>{{ $workflow->name }}</h1>
            <p>{{ $workflow->description ?: 'Steps run from left to right. A step starts once the steps before it have finished.' }}</p>
        </div>
    </div>

    @if($flashMessage)
        <div class="notice {{ ['success' => 'clear', 'warning' => 'hold', 'error' => 'stop'][$flashType] ?? '' }}" role="status">{{ $flashMessage }}</div>
    @endif

    <div class="flow" wire:ignore x-data="workflowCanvas(@js($nodes), @js($connections), @js($this->options))" x-on:pointermove.window="moved($event)" x-on:pointerup.window="released()">
        <div class="flow-bar">
            <div class="flow-add">
                <button type="button" class="btn small" x-on:click="addStep('agent')">Add agent</button>
                <button type="button" class="btn small" x-on:click="addStep('tool')">Add tool</button>
                <button type="button" class="btn small" x-on:click="addStep('approval')">Add approval</button>
            </div>
            <div class="flow-save">
                <span class="muted flow-dirty" x-show="dirty" x-cloak>Not saved</span>
                <button type="button" class="btn small" x-on:click="save('save')">Save</button>
                <button type="button" class="btn small" x-on:click="save('publish')">Publish</button>
                <button type="button" class="btn small primary" x-on:click="$dispatch('ask-to-run', { payload: payload() })">Run</button>
            </div>
        </div>

        <div class="flow-body">
            <div class="flow-scroll">
                <div class="flow-board" x-ref="board" aria-label="Workflow diagram">
                    <svg class="flow-lines" aria-hidden="true">
                        <defs><marker id="flow-arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="8" markerHeight="8" orient="auto"><path d="M0 0 L8 4 L0 8 z" fill="currentColor"/></marker></defs>
                        <g x-ref="lines"></g>
                        <path class="flow-line is-selected" x-show="linking" :d="linkPath"></path>
                    </svg>

                    <template x-for="edge in connections" :key="edge.id + '-label'">
                        <button type="button" class="flow-word" x-show="lineWord(edge)" :style="labelSpot(edge)" x-text="lineWord(edge)" x-on:click="select('line', edge.id)"></button>
                    </template>

                    <template x-for="node in nodes" :key="node.id">
                        <div class="flow-step" :class="{ 'is-selected': selected?.id === node.id, 'is-person': node.config.kind === 'approval' }" :style="`left:${node.x}px;top:${node.y}px`"
                             tabindex="0" role="button" :aria-label="node.label" x-on:pointerdown="startDrag($event, node)" x-on:keydown.enter="select('step', node.id)"
                             x-on:keydown.arrow-right="nudge($event, node, 1, 0)" x-on:keydown.arrow-left="nudge($event, node, -1, 0)" x-on:keydown.arrow-up="nudge($event, node, 0, -1)" x-on:keydown.arrow-down="nudge($event, node, 0, 1)">
                            <b x-text="node.label"></b>
                            <span x-text="subtitle(node)"></span>
                            <button type="button" class="flow-port" aria-label="Draw a line from this step" x-on:pointerdown="startLink($event, node)"></button>
                        </div>
                    </template>

                    <p class="flow-empty" x-show="nodes.length === 0">Add the first step, or start from a template on the Workflows page.</p>
                </div>
            </div>

            <ol class="flow-list" aria-label="Steps in order">
                <template x-for="node in ordered" :key="node.id">
                    <li><button type="button" class="row desk-row" x-on:click="select('step', node.id)"><span class="row-main"><b x-text="node.label"></b><span x-text="subtitle(node)"></span></span></button></li>
                </template>
                <li class="empty" x-show="nodes.length === 0">Add the first step.</li>
            </ol>

            <aside class="flow-side" :class="{ 'is-open': selected }" aria-label="Details">
                <template x-if="step">
                    <div class="card-body">
                        <div class="flow-side-head"><h2 x-text="step.config.kind === 'approval' ? 'Approval step' : (step.config.kind === 'tool' ? 'Tool step' : 'Agent step')"></h2><button type="button" class="btn quiet small" x-on:click="selected = null">Close</button></div>

                        <label class="field"><span>Name</span><input class="input" x-model="step.label" x-on:input="changed()"></label>

                        <template x-if="step.config.kind !== 'approval'">
                            <label class="field">
                                <span x-text="step.config.kind === 'tool' ? 'Runs as' : 'Agent'"></span>
                                <select class="select" x-model.number="step.config.deployment_id" x-on:change="changed()">
                                    <option value="">Choose an agent</option>
                                    <template x-for="agent in agents" :key="agent.id"><option :value="agent.id" :selected="agent.id === Number(step.config.deployment_id)" x-text="agent.name"></option></template>
                                </select>
                                <small x-show="step.config.kind === 'tool'">The agent must have been given this tool.</small>
                            </label>
                        </template>

                        <template x-if="step.config.kind === 'agent'">
                            <label class="field"><span>What this step should do</span><textarea class="textarea" rows="4" x-model="step.config.instruction" x-on:input="changed()"></textarea><small>It also receives the request and the results of the steps before it.</small></label>
                        </template>

                        <template x-if="step.config.kind === 'tool'">
                            <div>
                                <label class="field">
                                    <span>Tool</span>
                                    <select class="select" x-model="step.config.tool" x-on:change="changed()">
                                        <option value="">Choose a tool</option>
                                        <template x-for="tool in tools" :key="tool.key"><option :value="tool.key" :selected="tool.key === step.config.tool" x-text="tool.label"></option></template>
                                    </select>
                                </label>
                                <template x-for="field in toolArguments(step.config.tool)" :key="field">
                                    <label class="field"><span x-text="field.replace('_', ' ')"></span><input class="input" x-model="step.config.arguments[field]" x-on:input="changed()"></label>
                                </template>
                                <small class="hint">Write {previous} to use the last step's result.</small>
                            </div>
                        </template>

                        <template x-if="step.config.kind !== 'approval'">
                            <div>
                                <label class="field">
                                    <span>Result must</span>
                                    <select class="select" x-model="step.config.expect.format" x-on:change="changed()">
                                        <option value="">Be anything</option>
                                        <option value="json">Be structured data</option>
                                    </select>
                                </label>
                                <label class="field"><span>Result must mention</span><input class="input" x-model="step.config.expect.contains" x-on:input="changed()"></label>
                                <label class="field">
                                    <span>If it fails</span>
                                    <select class="select" x-model="step.config.on_failure" x-on:change="changed()">
                                        <option value="stop">Stop the workflow</option>
                                        <option value="continue">Carry on and report it</option>
                                    </select>
                                </label>
                            </div>
                        </template>

                        <fieldset class="field flow-after" x-show="nodes.length > 1">
                            <legend class="label">Runs after</legend>
                            <template x-for="other in nodes.filter((node) => node.id !== step.id)" :key="other.id">
                                <label class="check-line"><input type="checkbox" :checked="runsAfter(other.id)" x-on:change="toggleRunsAfter(other.id)"><span x-text="other.label"></span></label>
                            </template>
                        </fieldset>

                        <button type="button" class="btn danger small" x-on:click="removeSelected()">Remove step</button>
                    </div>
                </template>

                <template x-if="line">
                    <div class="card-body">
                        <div class="flow-side-head"><h2>Line</h2><button type="button" class="btn quiet small" x-on:click="selected = null">Close</button></div>
                        <label class="field">
                            <span>Take this line</span>
                            <select class="select" x-model="line.condition.op" x-on:change="changed()">
                                <template x-for="(word, op) in operators" :key="op"><option :value="op" :selected="op === line.condition.op" x-text="word"></option></template>
                            </select>
                        </label>
                        <label class="field" x-show="['contains', 'not_contains', 'equals'].includes(line.condition.op)"><span>This text</span><input class="input" x-model="line.condition.value" x-on:input="changed()"></label>
                        <button type="button" class="btn danger small" x-on:click="removeSelected()">Remove line</button>
                    </div>
                </template>

                <div class="card-body muted" x-show="!selected">Choose a step or a line to change it. Drag from the dot on a step to draw a line.</div>
            </aside>
        </div>
    </div>

    <div class="sheet-wrap" x-show="asking" x-cloak x-on:ask-to-run.window="asking = true; $wire.nodes = $event.detail.payload[0]; $wire.connections = $event.detail.payload[1]">
        <div class="veil" x-on:click="asking = false"></div>
        <form class="sheet" x-on:submit.prevent="$wire.runWith(request); asking = false">
            <div class="sheet-head"><h2>Run this workflow</h2></div>
            <div class="sheet-body">
                <label class="field"><span>What should it work on?</span><textarea class="textarea" rows="5" x-model="request"></textarea></label>
                <p class="muted">Agents use real tools. Anything that needs approval stops and waits for a person.</p>
            </div>
            <div class="sheet-foot">
                <button type="button" class="btn quiet" x-on:click="asking = false">Cancel</button>
                <button type="submit" class="btn primary">Run</button>
            </div>
        </form>
    </div>

    <livewire:workflows.workflow-executions :workflow-id="$workflow->id" />
</div>
