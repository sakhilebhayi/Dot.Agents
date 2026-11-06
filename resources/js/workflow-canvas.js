/**
 * The workflow diagram. Registered with Alpine.data so it ships as a bundled asset under the page's CSP.
 * Steps and lines live here while being edited and go to the server on save. On a phone the same data is a list.
 */
const STEP_W = 196;
const STEP_H = 68;
const GRID = 24;
const snap = (value) => Math.max(0, Math.round(value / GRID) * GRID);
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : `s-${Date.now()}-${Math.random().toString(16).slice(2)}`);

document.addEventListener('alpine:init', () => {
    Alpine.data('workflowCanvas', (initialNodes = [], initialConnections = [], options = {}) => ({
        nodes: [],
        connections: [],
        agents: options.agents ?? [],
        tools: options.tools ?? [],
        operators: options.operators ?? {},
        selected: null,
        dragging: null,
        linking: null,
        pointer: { x: 0, y: 0 },
        dirty: false,

        init() {
            this.nodes = (initialNodes ?? []).map((node) => ({ ...node, config: this.withDefaults(node.config) }));
            this.connections = (initialConnections ?? []).map((line) => ({ ...line, condition: this.conditionOf(line.condition) }));
            window.addEventListener('beforeunload', (event) => { if (this.dirty) event.preventDefault(); });
            Alpine.effect(() => this.drawLines());
        },

        /** Alpine cannot template inside an SVG, so the lines are drawn here. It reruns whenever a step or line changes. */
        drawLines() {
            const layer = this.$refs.lines;
            if (!layer) return;

            const paths = this.connections.flatMap((edge) => {
                const hit = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                const route = this.pathFor(edge);
                hit.setAttribute('d', route);
                hit.setAttribute('class', 'flow-hit');
                hit.addEventListener('click', () => this.select('line', edge.id));
                line.setAttribute('d', route);
                line.setAttribute('marker-end', 'url(#flow-arrow)');
                line.setAttribute('class', `flow-line${this.selected?.id === edge.id ? ' is-selected' : ''}${edge.condition?.op === 'failed' ? ' is-failure' : ''}`);

                return [hit, line];
            });

            layer.replaceChildren(...paths);
        },

        withDefaults(config) {
            const safe = Array.isArray(config) || !config ? {} : config;

            return { kind: 'agent', deployment_id: null, instruction: '', on_failure: 'stop', retries: 0, tool: '', arguments: {}, expect: {}, ...safe };
        },

        conditionOf(condition) {
            const parsed = typeof condition === 'string' ? JSON.parse(condition || 'null') : condition;

            return parsed && parsed.op ? parsed : { op: 'always', value: '' };
        },

        get step() { return this.selected?.type === 'step' ? this.nodes.find((node) => node.id === this.selected.id) : null; },
        get line() { return this.selected?.type === 'line' ? this.connections.find((edge) => edge.id === this.selected.id) : null; },

        /** Steps in running order, for the phone list. */
        get ordered() {
            const placed = new Set();
            const out = [];
            let remaining = [...this.nodes].sort((a, b) => a.x - b.x || a.y - b.y);

            while (remaining.length) {
                const ready = remaining.filter((node) => this.connections.filter((edge) => edge.to === node.id).every((edge) => placed.has(edge.from)));
                const next = ready.length ? ready : remaining.slice(0, 1);
                next.forEach((node) => { placed.add(node.id); out.push(node); });
                remaining = remaining.filter((node) => !placed.has(node.id));
            }

            return out;
        },

        changed() { this.dirty = true; },

        addStep(kind) {
            const labels = { agent: 'New step', tool: 'Use a tool', approval: 'A person approves' };
            const last = this.nodes[this.nodes.length - 1];
            const node = { id: uuid(), agent_key: kind, label: labels[kind], x: last ? last.x + 264 : 48, y: last ? last.y : 96, config: this.withDefaults({ kind }) };

            this.nodes.push(node);

            if (last) {
                this.connections.push({ id: uuid(), from: last.id, to: node.id, condition: { op: 'always', value: '' }, label: null });
            }

            this.select('step', node.id);
            this.changed();
        },

        select(type, id) { this.selected = { type, id }; },

        removeSelected() {
            if (this.step) {
                const id = this.step.id;
                this.nodes = this.nodes.filter((node) => node.id !== id);
                this.connections = this.connections.filter((edge) => edge.from !== id && edge.to !== id);
            } else if (this.line) {
                const id = this.line.id;
                this.connections = this.connections.filter((edge) => edge.id !== id);
            }

            this.selected = null;
            this.changed();
        },

        /** The way to connect steps without dragging: tick what a step runs after. */
        runsAfter(from) { return this.connections.some((edge) => edge.from === from && edge.to === this.step?.id); },

        toggleRunsAfter(from) {
            const to = this.step.id;
            const existing = this.connections.find((edge) => edge.from === from && edge.to === to);

            if (existing) {
                this.connections = this.connections.filter((edge) => edge !== existing);
            } else if (!this.wouldLoop(from, to)) {
                this.connections.push({ id: uuid(), from, to, condition: { op: 'always', value: '' }, label: null });
            }

            this.changed();
        },

        wouldLoop(from, to) {
            const seen = new Set();
            const walk = (id) => id === from || (!seen.has(id) && (seen.add(id), this.connections.filter((edge) => edge.from === id).some((edge) => walk(edge.to))));

            return from === to || walk(to);
        },

        startDrag(event, node) {
            event.preventDefault();
            this.select('step', node.id);
            this.dragging = { id: node.id, dx: event.clientX - node.x, dy: event.clientY - node.y };
        },

        startLink(event, node) {
            event.stopPropagation();
            event.preventDefault();
            this.linking = node.id;
            this.track(event);
        },

        track(event) {
            const box = this.$refs.board.getBoundingClientRect();
            this.pointer = { x: event.clientX - box.left, y: event.clientY - box.top };
        },

        moved(event) {
            if (this.dragging) {
                const node = this.nodes.find((item) => item.id === this.dragging.id);
                node.x = Math.max(0, event.clientX - this.dragging.dx);
                node.y = Math.max(0, event.clientY - this.dragging.dy);
            }

            if (this.linking) this.track(event);
        },

        released() {
            if (this.dragging) {
                const node = this.nodes.find((item) => item.id === this.dragging.id);
                node.x = snap(node.x);
                node.y = snap(node.y);
                this.dragging = null;
                this.changed();
            }

            if (this.linking) {
                const target = this.nodes.find((node) => this.pointer.x >= node.x && this.pointer.x <= node.x + STEP_W && this.pointer.y >= node.y && this.pointer.y <= node.y + STEP_H);

                if (target && !this.wouldLoop(this.linking, target.id) && !this.connections.some((edge) => edge.from === this.linking && edge.to === target.id)) {
                    const edge = { id: uuid(), from: this.linking, to: target.id, condition: { op: 'always', value: '' }, label: null };
                    this.connections.push(edge);
                    this.select('line', edge.id);
                    this.changed();
                }

                this.linking = null;
            }
        },

        nudge(event, node, dx, dy) {
            event.preventDefault();
            node.x = Math.max(0, node.x + dx * GRID);
            node.y = Math.max(0, node.y + dy * GRID);
            this.changed();
        },

        pathFor(edge) {
            const from = this.nodes.find((node) => node.id === edge.from);
            const to = this.nodes.find((node) => node.id === edge.to);
            if (!from || !to) return '';

            return this.route(from.x + STEP_W, from.y + STEP_H / 2, to.x, to.y + STEP_H / 2);
        },

        route(x1, y1, x2, y2) {
            const mid = x2 > x1 + 32 ? (x1 + x2) / 2 : x1 + 24;

            return x2 > x1 + 32 ? `M${x1} ${y1} H${mid} V${y2} H${x2}` : `M${x1} ${y1} H${mid} V${y1 + (y2 >= y1 ? 56 : -56)} H${x2 - 24} V${y2} H${x2}`;
        },

        get linkPath() {
            const from = this.nodes.find((node) => node.id === this.linking);

            return from ? this.route(from.x + STEP_W, from.y + STEP_H / 2, this.pointer.x, this.pointer.y) : '';
        },

        labelSpot(edge) {
            const from = this.nodes.find((node) => node.id === edge.from);
            const to = this.nodes.find((node) => node.id === edge.to);
            if (!from || !to) return '';

            return `left:${(from.x + STEP_W + to.x) / 2}px;top:${(from.y + to.y + STEP_H) / 2}px`;
        },

        lineWord(edge) {
            const { op, value } = edge.condition ?? {};
            const words = { failed: 'If it failed', contains: `Has "${value}"`, not_contains: `Not "${value}"`, equals: `Is "${value}"` };

            return words[op] ?? '';
        },

        agentName(id) { return this.agents.find((agent) => agent.id === Number(id))?.name ?? 'Choose an agent'; },

        subtitle(node) {
            if (node.config.kind === 'approval') return 'Waits for a person';
            if (node.config.kind === 'tool') return node.config.tool || 'Choose a tool';

            return this.agentName(node.config.deployment_id);
        },

        toolArguments(key) { return this.tools.find((tool) => tool.key === key)?.fields ?? []; },

        payload() {
            return [
                this.nodes.map((node) => ({ ...node, x: Math.round(node.x), y: Math.round(node.y) })),
                this.connections.map((edge) => ({ ...edge, condition: edge.condition?.op === 'always' ? null : edge.condition })),
            ];
        },

        async save(action = 'save') {
            const [nodes, connections] = this.payload();
            this.$wire.nodes = nodes;
            this.$wire.connections = connections;
            this.dirty = false;
            await this.$wire[action]();
        },
    }));
});
