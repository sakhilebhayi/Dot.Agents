<div
    x-data="workflowCanvas({{ Js::from($nodes) }}, {{ Js::from($connections) }})"
    x-init="init()"
    @mouseup.window="onMouseUp($event)"
    class="flex h-full bg-gray-50 dark:bg-gray-950 overflow-hidden border border-gray-200 dark:border-gray-800 select-none"
>

    {{-- ── LEFT PANEL: Agent Library ── --}}
    <aside class="w-64 shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col">

        {{-- Header --}}
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Agent Library</p>
        </div>

        {{-- Agent list (drag sources) --}}
        <div class="flex-1 overflow-y-auto p-3 space-y-1.5">
            @foreach($this->availableAgents as $agent)
                <div
                    draggable="true"
                    @dragstart="startAgentDrag($event, '{{ $agent['slug'] }}', '{{ addslashes($agent['name']) }}')"
                    class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 cursor-grab active:cursor-grabbing hover:border-brand-purple-light dark:hover:border-brand-purple-light hover:bg-brand-purple-pale dark:hover:bg-brand-purple-deeper/20 transition-all group"
                >
                    <div class="w-7 h-7 rounded-lg bg-brand-purple-pale dark:bg-brand-purple-deeper/40 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-brand-purple-mid dark:text-brand-purple-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">{{ $agent['name'] }}</span>
                </div>
            @endforeach
        </div>

        {{-- Footer actions --}}
        <div class="p-3 border-t border-gray-200 dark:border-gray-800 space-y-2">

            {{-- Status badge --}}
            <div class="flex items-center justify-between px-1 mb-1">
                <span class="text-xs text-gray-400 dark:text-gray-500">Status</span>
                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full
                    {{ $workflow->status === 'active' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full inline-block
                        {{ $workflow->status === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                    {{ ucfirst($workflow->status) }}
                </span>
            </div>

            <button
                wire:click="save"
                wire:loading.attr="disabled"
                class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-brand-purple-mid hover:bg-brand-purple text-white text-xs font-semibold transition-colors"
                aria-label="Save workflow graph"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span wire:loading.remove wire:target="save">Save Draft</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>

            @if($workflow->status !== 'active')
                <button
                    wire:click="publish"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition-colors"
                    aria-label="Publish workflow"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span wire:loading.remove wire:target="publish">Publish Workflow</span>
                    <span wire:loading wire:target="publish">Publishing…</span>
                </button>
            @else
                <button
                    wire:click="unpublish"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold transition-colors"
                    aria-label="Unpublish workflow"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                    <span wire:loading.remove wire:target="unpublish">Unpublish</span>
                    <span wire:loading wire:target="unpublish">Unpublishing…</span>
                </button>
            @endif

            <button
                wire:click="run"
                wire:loading.attr="disabled"
                class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-xs font-semibold transition-colors"
                aria-label="Execute workflow"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span wire:loading.remove wire:target="run">Run Workflow</span>
                <span wire:loading wire:target="run">Running…</span>
            </button>
        </div>
    </aside>

    {{-- ── MAIN CANVAS ── --}}
    <main
        class="relative flex-1"
        style="overflow:hidden;"
        @dragover.prevent
        @drop="onCanvasDrop($event)"
        @mousemove="onMouseMove($event)"
        @click="cancelConnecting()"
        @keydown.escape.window="cancelConnecting()"
        id="workflow-canvas"
    >

        {{-- Canvas grid background --}}
        <svg class="absolute inset-0 w-full h-full pointer-events-none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="grid" width="24" height="24" patternUnits="userSpaceOnUse">
                    <path d="M 24 0 L 0 0 0 24" fill="none" stroke="currentColor" stroke-width="0.5" class="text-gray-200 dark:text-gray-800"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)"/>
        </svg>

        {{-- ── SVG layer ──
             x-for CANNOT be used inside SVG with Livewire: when Livewire morphs
             the DOM after a server response, Alpine loses the x-for loop variable
             scope on cloned path elements → "conn is not defined".
             Solution: a single <g x-effect="renderConnections()"> that imperatively
             creates SVG path elements using document.createElementNS so they are
             always in the correct SVG namespace with no Alpine scope dependency. --}}
        <svg
            id="connections-svg"
            class="absolute inset-0 w-full h-full"
            style="z-index:20; pointer-events:none; overflow:visible;"
        >
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                    <polygon points="0 0,10 3.5,0 7" fill="#8b5cf6" />
                </marker>
                <marker id="arrowhead-live" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                    <polygon points="0 0,10 3.5,0 7" fill="#f5be1c" />
                </marker>
            </defs>

            {{-- Saved connection lines — rendered imperatively via x-effect --}}
            <g id="connections-group" x-effect="renderConnections()"></g>

            {{-- Live preview line (single path, always in DOM, toggled via display) --}}
            <path
                id="live-connection-path"
                :d="liveConnectionPath()"
                :style="connectingMode ? 'display:block' : 'display:none'"
                fill="none"
                stroke="#f5be1c"
                stroke-width="2.5"
                stroke-dasharray="8 4"
                marker-end="url(#arrowhead-live)"
                style="pointer-events:none;"
            />
        </svg>

        {{-- ── Agent Nodes ── --}}
        <template x-for="node in nodes" :key="node.id">
            <div
                :id="'node-' + node.id"
                :style="`left: ${node.x}px; top: ${node.y}px; z-index: ${dragging === node.id ? 30 : 10};`"
                class="absolute w-44 rounded-xl shadow-lg border-2 transition-shadow hover:shadow-xl select-none"
                :class="[
                    selectedNode === node.id ? 'border-yellow-400' : 'border-brand-purple-pale dark:border-gray-700',
                    dragging === node.id ? 'opacity-90 shadow-2xl' : '',
                    connectingMode && connectionSourceId !== node.id ? 'cursor-crosshair' : ''
                ]"
                style="background:white;"
                @click.stop="selectNode(node.id)"
            >
                {{-- Node header — drag handle --}}
                <div
                    class="flex items-center gap-2 px-3 py-2 bg-brand-purple-mid dark:bg-brand-purple rounded-t-xl cursor-move"
                    @mousedown.stop="startDrag($event, node.id)"
                >
                    <div class="w-5 h-5 rounded bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                        </svg>
                    </div>
                    <span class="text-white text-xs font-semibold truncate flex-1" x-text="node.label || node.agent_key"></span>
                    <button
                        @mousedown.stop
                        @click.stop="removeNode(node.id)"
                        class="w-4 h-4 rounded flex items-center justify-center text-white/60 hover:text-white hover:bg-white/20 transition-colors"
                        aria-label="Remove node"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Node body --}}
                <div class="px-3 py-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="node.agent_key"></p>
                </div>

                {{-- OUTPUT port (bottom-centre) — CLICK to start a connection --}}
                {{-- Large hit zone (w-6 h-6) so it’s easy to click --}}
                <div
                    class="absolute left-1/2 -translate-x-1/2 flex items-center justify-center rounded-full border-2 border-yellow-400 bg-white z-30 transition-all"
                    :class="connectingMode && connectionSourceId === node.id
                        ? 'w-5 h-5 -bottom-3 ring-2 ring-yellow-300 ring-offset-1 bg-yellow-400'
                        : 'w-5 h-5 -bottom-3 hover:scale-125 cursor-pointer'"
                    @click.stop="startConnecting(node.id)"
                    @mousedown.stop
                    title="Click to start a connection from this node"
                >
                    <div class="w-2 h-2 rounded-full bg-yellow-400"></div>
                </div>

                {{-- INPUT port (top-centre) — CLICK to complete a connection --}}
                {{-- Glows green and enlarges when another node is in connect-mode --}}
                <div
                    class="absolute left-1/2 -translate-x-1/2 flex items-center justify-center rounded-full border-2 border-brand-purple-light bg-white z-30 transition-all"
                    :class="connectingMode && connectionSourceId !== node.id
                        ? 'w-6 h-6 -top-3 cursor-pointer ring-2 ring-green-400 ring-offset-1 scale-125 border-green-500'
                        : 'w-5 h-5 -top-3 cursor-default'"
                    @click.stop="connectingMode && connectionSourceId !== node.id ? completeConnection(node.id) : null"
                    @mousedown.stop
                    :title="connectingMode && connectionSourceId !== node.id ? 'Click to connect here' : 'Input port'"
                >
                    <div
                        class="rounded-full transition-all"
                        :class="connectingMode && connectionSourceId !== node.id
                            ? 'w-2.5 h-2.5 bg-green-500'
                            : 'w-2 h-2 bg-brand-purple-light'"
                    ></div>
                </div>
            </div>
        </template>

        {{-- Empty state --}}
        <template x-if="nodes.length === 0">
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <div class="w-16 h-16 rounded-2xl bg-brand-purple-pale dark:bg-brand-purple-deeper/30 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-brand-purple-light dark:text-brand-purple-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">Drag agents onto the canvas</p>
                <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">Connect ports to build your AI workflow graph</p>
            </div>
        </template>

        {{-- Flash feedback --}}
        @if($flashMessage)
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 3500)"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="absolute top-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 rounded-xl shadow-lg text-sm font-medium
                    {{ $flashType === 'success' ? 'bg-green-500 text-white' : ($flashType === 'error' ? 'bg-red-600 text-white' : 'bg-yellow-400 text-gray-900') }}"
                role="status"
                aria-live="polite"
            >
                {{ $flashMessage }}
            </div>
        @endif

    </main>

    {{-- ── RIGHT PANEL: Node Properties ── --}}
    <aside
        class="w-56 shrink-0 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800 flex flex-col"
        x-show="selectedNode"
        x-cloak
    >
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Node Properties</p>
        </div>
        <div class="p-4 space-y-4 flex-1 overflow-y-auto">
            <template x-if="selectedNodeData()">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Label</label>
                        <input
                            type="text"
                            :value="selectedNodeData()?.label"
                            @input="updateNodeLabel($event.target.value)"
                            class="w-full text-xs rounded-lg border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-light"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Agent Key</label>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-mono bg-gray-50 dark:bg-gray-800 rounded-lg px-2 py-1.5" x-text="selectedNodeData()?.agent_key"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Position</label>
                        <p class="text-xs text-gray-400 dark:text-gray-600 font-mono"
                            x-text="`x: ${selectedNodeData()?.x}  y: ${selectedNodeData()?.y}`"
                        ></p>
                    </div>
                    <button
                        @click="removeNode(selectedNode); selectedNode = null"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-xs font-medium hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Remove Node
                    </button>
                </div>
            </template>
        </div>
    </aside>

</div>

<!-- workflowCanvas Alpine component is registered via Alpine.data() in
     resources/js/workflow-canvas.js (bundled by Vite). Using an external
     asset avoids the CSP nonce mismatch that blocks inline script tags
     when the Livewire navigate feature clones them across page transitions. -->
