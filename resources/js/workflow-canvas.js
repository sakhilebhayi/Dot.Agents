/**
 * workflowCanvas — Alpine.js component for the visual graph builder.
 *
 * Registered via Alpine.data() so it is loaded as a bundled external asset.
 * This avoids the CSP nonce mismatch that occurs when Livewire's navigate
 * feature clones inline <script nonce=""> tags across page transitions:
 * the browser rejects the cloned script because the nonce from the previous
 * page response no longer matches, leaving workflowCanvas undefined.
 *
 * Usage in blade: x-data="workflowCanvas(initialNodes, initialConnections)"
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('workflowCanvas', (initialNodes = [], initialConnections = []) => ({
        nodes: [],
        connections: [],

        // Node drag (store ID, not object reference — refs go stale after canvas-synced)
        dragging: null,
        dragOffsetX: 0,
        dragOffsetY: 0,

        // Click-click connection model
        connectingMode: false,
        connectionSourceId: null,
        mouseX: 0,
        mouseY: 0,

        // Selection
        selectedNode: null,

        // Sidebar drag-and-drop
        pendingAgentKey: null,
        pendingAgentLabel: null,

        // Node dimensions (w-44 = 176px wide; header ~36px + body ~32px = 68px)
        NODE_W: 176,
        NODE_H: 68,

        init() {
            this.nodes       = initialNodes       ?? [];
            this.connections = initialConnections ?? [];

            // canvas-synced is dispatched by the server after every mutation.
            // Guard against duplicate listeners on Livewire re-render.
            if (!window.__canvasSyncedBound) {
                window.__canvasSyncedBound = true;
                window.addEventListener('canvas-synced', (e) => {
                    // Find the live Alpine component and update it
                    const el = document.querySelector('[id^="workflow-canvas"]');
                    if (el && el._x_dataStack) {
                        const data = el._x_dataStack[0];
                        if (data) {
                            data.nodes       = e.detail.nodes       ?? [];
                            data.connections = e.detail.connections ?? [];
                        }
                    }
                });
            }
            // Also update directly on init (handles Livewire re-render)
            const self = this;
            window.addEventListener('canvas-synced', function handler(e) {
                self.nodes       = e.detail.nodes       ?? [];
                self.connections = e.detail.connections ?? [];
            });
        },

        // ── Sidebar drag-and-drop ──
        startAgentDrag(e, agentKey, agentLabel) {
            this.pendingAgentKey   = agentKey;
            this.pendingAgentLabel = agentLabel;
            e.dataTransfer.effectAllowed = 'copy';
        },

        onCanvasDrop(e) {
            if (!this.pendingAgentKey) return;
            const canvas = document.getElementById('workflow-canvas');
            const rect   = canvas.getBoundingClientRect();
            const x = Math.round(e.clientX - rect.left - this.NODE_W / 2);
            const y = Math.round(e.clientY - rect.top  - this.NODE_H / 2);
            this.$wire.addNode(this.pendingAgentKey, Math.max(0, x), Math.max(0, y));
            this.pendingAgentKey   = null;
            this.pendingAgentLabel = null;
        },

        // ── Node drag-to-move ──
        startDrag(e, nodeId) {
            if (e.button !== 0 || this.connectingMode) return;
            const idx = this.nodes.findIndex(n => n.id === nodeId);
            if (idx === -1) return;
            this.dragging    = nodeId;
            this.dragOffsetX = e.clientX - this.nodes[idx].x;
            this.dragOffsetY = e.clientY - this.nodes[idx].y;
            e.preventDefault();
        },

        onMouseMove(e) {
            this.mouseX = e.clientX;
            this.mouseY = e.clientY;
            if (this.dragging) {
                const idx = this.nodes.findIndex(n => n.id === this.dragging);
                if (idx !== -1) {
                    this.nodes[idx].x = Math.max(0, Math.round(e.clientX - this.dragOffsetX));
                    this.nodes[idx].y = Math.max(0, Math.round(e.clientY - this.dragOffsetY));
                }
            }
        },

        onMouseUp(e) {
            if (this.dragging) {
                const node = this.nodes.find(n => n.id === this.dragging);
                if (node) this.$wire.moveNode(this.dragging, node.x, node.y);
                this.dragging = null;
            }
        },

        // ── Click-click connection model ──
        // Step 1: click yellow OUTPUT port → enter connect mode
        startConnecting(nodeId) {
            if (this.dragging) return;
            this.connectingMode    = true;
            this.connectionSourceId = nodeId;
        },

        // Step 2: click purple INPUT port of another node → create connection
        completeConnection(targetNodeId) {
            if (!this.connectingMode || !this.connectionSourceId) return;
            if (this.connectionSourceId !== targetNodeId) {
                this.$wire.connectNodes(this.connectionSourceId, targetNodeId, null);
            }
            this.connectingMode    = false;
            this.connectionSourceId = null;
        },

        // Cancel connect mode (canvas click, Escape key)
        cancelConnecting() {
            if (this.connectingMode) {
                this.connectingMode    = false;
                this.connectionSourceId = null;
            }
        },

        removeNode(nodeId) {
            this.$wire.removeNode(nodeId);
        },

        removeConnection(connId) {
            this.$wire.removeConnection(connId);
        },

        // ── Imperative SVG renderer ──
        // Called by x-effect on <g id="connections-group">.
        // Builds SVG path elements using createElementNS so they are always
        // in the correct SVG namespace — avoids the "conn is not defined"
        // error caused by Alpine x-for losing scope when Livewire morphs the DOM.
        renderConnections() {
            const group = document.getElementById('connections-group');
            if (!group) return;

            // Remove all existing children
            while (group.firstChild) group.removeChild(group.firstChild);

            const SVG = 'http://www.w3.org/2000/svg';
            const self = this;

            for (const conn of this.connections) {
                const d = this.connectionPath(conn);
                if (!d || d === 'M 0 0') continue;

                // Visible bezier line
                const line = document.createElementNS(SVG, 'path');
                line.setAttribute('d', d);
                line.setAttribute('fill', 'none');
                line.setAttribute('stroke', '#8b5cf6');
                line.setAttribute('stroke-width', '2');
                line.setAttribute('marker-end', 'url(#arrowhead)');
                line.style.pointerEvents = 'none';
                group.appendChild(line);

                // Wide transparent hit-zone for click-to-delete
                const hit = document.createElementNS(SVG, 'path');
                hit.setAttribute('d', d);
                hit.setAttribute('fill', 'none');
                hit.setAttribute('stroke', 'transparent');
                hit.setAttribute('stroke-width', '16');
                hit.style.pointerEvents = 'all';
                hit.style.cursor = 'pointer';
                hit.title = 'Click to delete connection';
                // Capture conn.id in closure to avoid loop variable re-binding
                (function(id) {
                    hit.addEventListener('click', function(e) {
                        e.stopPropagation();
                        self.removeConnection(id);
                    });
                })(conn.id);
                group.appendChild(hit);
            }
        },

        // ── Selection ──
        selectNode(nodeId) {
            if (this.dragging || this.connectingMode) return;
            this.selectedNode = this.selectedNode === nodeId ? null : nodeId;
        },

        selectedNodeData() {
            if (!this.selectedNode) return null;
            return this.nodes.find(n => n.id === this.selectedNode) ?? null;
        },

        updateNodeLabel(label) {
            const idx = this.nodes.findIndex(n => n.id === this.selectedNode);
            if (idx !== -1) this.nodes[idx].label = label;
            if (this.selectedNode) this.$wire.updateNodeLabel(this.selectedNode, label);
        },

        // ── Port centre helpers (canvas-relative coordinates) ──
        outputPort(node) {
            // Bottom-centre of node (where yellow port lives)
            return { x: node.x + this.NODE_W / 2, y: node.y + this.NODE_H + 2 };
        },

        inputPort(node) {
            // Top-centre of node (where purple port lives)
            return { x: node.x + this.NODE_W / 2, y: node.y - 2 };
        },

        // ── SVG bezier path generators ──
        connectionPath(conn) {
            const src = this.nodes.find(n => n.id === conn.from);
            const dst = this.nodes.find(n => n.id === conn.to);
            if (!src || !dst) return 'M 0 0';
            const from = this.outputPort(src);
            const to   = this.inputPort(dst);
            const cy   = Math.max(50, Math.abs(to.y - from.y) * 0.6);
            return `M ${from.x} ${from.y} C ${from.x} ${from.y + cy}, ${to.x} ${to.y - cy}, ${to.x} ${to.y}`;
        },

        liveConnectionPath() {
            if (!this.connectingMode || !this.connectionSourceId) return 'M 0 0';
            const src = this.nodes.find(n => n.id === this.connectionSourceId);
            if (!src) return 'M 0 0';
            const canvas = document.getElementById('workflow-canvas');
            if (!canvas) return 'M 0 0';
            const rect = canvas.getBoundingClientRect();
            const from = this.outputPort(src);
            const tx   = this.mouseX - rect.left;
            const ty   = this.mouseY - rect.top;
            const cy   = Math.max(40, Math.abs(ty - from.y) * 0.5);
            return `M ${from.x} ${from.y} C ${from.x} ${from.y + cy}, ${tx} ${ty - cy}, ${tx} ${ty}`;
        },
    }));
});
