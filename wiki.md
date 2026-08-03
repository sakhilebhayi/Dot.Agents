---
title: Dot.Agents — Platform Wiki
version: 0.2.0
status: active
owners: [Agents Platform Lead]
platform-id: dot-agents
last-review: 2026-08-01
---

# Dot.Agents

Purpose: this is Dot.Agents's own knowledge home — owned and maintained by the Dot.Agents team. It describes what this platform is, what it runs, and how it connects to the wider Dot Ecosystem. Dot.Brain never edits this file; it only reads what we choose to publish.

> **Related:** [Dot.Brain's ingested view of this platform](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-agents.md)

---

## 1. What Dot.Agents Is

Dot.Agents is a multi-tenant enterprise SaaS platform (Laravel 12 / PHP 8.4 / Livewire 3) that lets organisations hire, deploy, monitor, and govern specialised AI agents as digital workforce members. An organisation browses a marketplace of agent types (`Agent`), deploys one as an `AgentDeployment` — their own configured instance of that agent — assigns it work as `AgentTask` records, and watches it operate under a governance stack that logs, scores, and can halt it.

This is not a thin wrapper around a chat API. The platform's center of gravity is governance: every AI decision is auditable, every agent runs inside a deployment mode with an enforced confidence threshold, and a background "Digital Immune System" watches for drift, failure spikes, and security anomalies and can suspend a deployment automatically. That governance stack — not the chat surface — is what most of the codebase exists to serve.

**Status:** shipped and actively developed. This wiki describes real, implemented behavior — models, events, and services that exist in this repository today — not a roadmap sketch. Section 8 (Roadmap) calls out what is still open.

## 2. Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        DOT.AGENTS PLATFORM                          │
│                                                                     │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────────┐  │
│  │   Web UI     │    │   REST API   │    │   Background Jobs    │  │
│  │ (Livewire 3) │    │ (Sanctum v1) │    │   (Redis + Horizon)  │  │
│  └──────┬───────┘    └──────┬───────┘    └──────────┬───────────┘  │
│         │                   │                        │              │
│  ┌──────▼───────────────────▼────────────────────────▼──────────┐  │
│  │                    Application Layer                          │  │
│  │  Actions ─────► DTOs ─────► Services ─────► Events           │  │
│  │                                                               │  │
│  │  ┌──────────────────────────────────────────────────────┐    │  │
│  │  │              AI Governance Stack                      │    │  │
│  │  │  DelusionDetection │ OutputModeration │ Approvals     │    │  │
│  │  │  CircuitBreaker    │ ToolPermissions  │ AuditLogs     │    │  │
│  │  └──────────────────────────────────────────────────────┘    │  │
│  └────────────────────────────────────────┬──────────────────────┘  │
│                                           │                         │
│  ┌─────────────────────────────────────── ▼──────────────────────┐  │
│  │                     Data Layer                                 │  │
│  │         SQLite (dev) / MySQL 8+ (prod) / Redis (cache+queue)  │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                 ▼
         ┌─────────┐    ┌───────────┐    ┌──────────────┐
         │ OpenAI  │    │ Anthropic │    │  Google AI   │
         │ GPT-4o  │    │  Claude   │    │   Gemini     │
         └─────────┘    └───────────┘    └──────────────┘
                              + Ollama (self-hosted fallback)
```

All AI completion calls are mediated by **Prism PHP**, a provider-agnostic orchestration layer, rather than any single vendor SDK — see [ADR-003](docs/adr/ADR-003-prism-over-openai-sdk.md). Application code never calls a provider directly; it goes through `AgentOrchestrationService` or a dedicated skill service, which gives the platform a uniform failover chain (OpenAI → Anthropic → Google AI → local Ollama), each leg protected by its own `CircuitBreakerService` (3 failures in 5 min → open, 30 s probe → half-open, 1 success → closed).

**Multi-tenancy** ([ADR-001](docs/adr/ADR-001-multi-tenancy-strategy.md)): organisations are Jetstream Teams; every tenant-owned model carries `organization_id`, enforced by Eloquent global scopes, `OrganizationContextMiddleware`, per-Action `Gate::authorize()` calls, and architecture-guard tests that fail the build if a new model skips tenant scoping. Platform-level catalog models (`Agent`, `AgentVersion`, `AgentSkillPermission`, `SubscriptionPlan`) are deliberately *not* tenant-scoped — they're the shared marketplace, visible to every organisation.

**Request flow:** HTTP request → `CorrelationIdMiddleware` (assigns `X-Correlation-ID`) → `auth:sanctum` → `verified` → org-context middleware → Livewire component or API controller → Form Request (validate + authorize) → Action class → `Gate::authorize()` → DTO → Service → domain event → queued listeners (governance, notifications, scoring).

**Queues:** four Redis channels — `default` (standard jobs), `governance` (audit logs, scorecards, drift detection), `notifications` (email/Slack), `ai` (reserved for heavy inference jobs).

## 3. Domain Entities

The platform is organised around four domains: Agents, Governance, Organizations, and Billing, plus a Social domain (social-account-connected agents that publish, monitor sentiment, and qualify leads) and a Workflow domain (multi-node agent pipelines).

| Entity | Table | Role |
|---|---|---|
| `Agent` | `agents` | Marketplace catalog entry — an agent *type*, not tenant-owned |
| `AgentDeployment` | `agent_deployments` | An organisation's hired instance of an agent — deployment mode, confidence threshold, custom instructions |
| `AgentTask` | `agent_tasks` | One unit of work executed by a deployment — input/output payloads, confidence score, latency, cost, tokens |
| `AgentSession` / `AgentMessage` | `agent_sessions`, `agent_messages` | Threaded chat/conversation history per deployment |
| `AgentMemory` / `MemoryEmbedding` | `agent_memories`, `memory_embeddings` | Agent long-term and vector memory |
| `AgentSkill*` (Assignment, Approval, Audit, Execution, Permission, Requirement, Score) | multiple | The skill system: capabilities an agent can invoke, with approval gates, permission checks, execution audit, and per-skill scoring |
| `AgentScorecard` / `PlatformMegaScorecard` | `agent_scorecards`, `platform_mega_scorecards` | The 10-dimension agent health score, computed per agent per period |
| `AgentApproval` / `DecisionLog` | `agent_approvals`, `decision_logs` | Human-in-the-loop approval workflow and the delusion-risk-scored decision record behind it |
| `AuditLog` | `audit_logs` | Immutable, append-only record of every user, agent, system, and security event |
| `SecurityEvent` | `security_events` | Prompt-injection attempts, DIS-detected anomalies, threat flags |
| `Organization` | maps to Jetstream `teams` | The tenant root — every resource belongs to one |
| `AgentWorkflow` / `WorkflowNode` / `WorkflowConnection` / `WorkflowExecution` | multiple | Visual multi-step agent pipelines (the Workflow Builder) |
| `SocialAccount`, `SocialPost`, `SocialConversation`, `SocialLead`, `SocialConversion` | multiple | Agents operating social channels — publishing, inbox triage, lead qualification |
| `AgentPlugin` / `AgentPluginInstallation` | `agent_plugins`, `agent_plugin_installations` | Marketplace extension mechanism for third-party agent capabilities |

Full column-level schema: [`docs/architecture/database-schema.md`](docs/architecture/database-schema.md) (24 migrations, 40 models).

## 4. Governance Stack

This is the part of the platform that most distinguishes it from a generic chat wrapper — full detail in [`docs/agents/governance-spec.md`](docs/agents/governance-spec.md):

- **Audit trail** — every significant event lands in `audit_logs` (append-only, no update/delete routes), categorised `user_action | agent_action | system_event | security_event`, with a `risk_level`.
- **Delusion-risk scoring** — `DelusionDetectionService` assigns every AI decision a 0–100 hallucination-risk score; 51–75 triggers a human approval request, 76–100 blocks the task and flags the deployment.
- **Approval workflow** — deployment modes (`advisory`, `semi_autonomous`, `autonomous`, `executive_approval`) determine when a task pauses for a human decision before an `ApprovalProcessed` event lets it resume.
- **Digital Immune System (DIS)** — a scheduled service that checks task-failure rate, p95 latency, delusion-risk trend, injection attempts, confidence drift, and memory anomalies, and can suspend a deployment automatically on breach.
- **Scorecard** — `ScorecardService` computes a 10-dimension 0–100 health score per agent per period (task success, confidence accuracy, latency, delusion risk, approval rate, skill utilisation, memory quality, security posture, governance compliance, business impact).
- **Prompt-injection guard** — all user-supplied text bound for the LLM passes `AuditService::detectPromptInjection()` before it reaches the model; a hit blocks the call and logs a `SecurityEvent`.

## 4a. Security Review Findings (governance internals deep pass)

Follow-up to the 0.1.1 UI/branding pass, which deliberately did not audit governance internals (flagged S=1 in Dot.Brain's `15-MEGA-v2.md`, "not fully checked"). This pass read the actual code behind delusion-risk scoring, the approval workflow, the Digital Immune System, the prompt-injection guard, and cross-team data isolation.

**Fixed** (commit `b488978`):
- `ApprovalQueue::selectApproval($id)` (Livewire, `app/Livewire/Governance/ApprovalQueue.php`) looked up any `AgentApproval` by ID with no org check. Because the ID arrives as a Livewire *method call argument* (not a synced/checksummed public property), it is fully attacker-controlled — any authenticated user, in any organization, could call it with another org's approval ID and read that approval's agent, proposed action, confidence score, and impact assessment via the detail panel. (Approve/reject itself was already safe — `ProcessApprovalAction` calls `Gate::authorize('review', $approval)` — but the view-only path had nothing.) Fixed by adding `$user->can('view', $approval)` before assigning `selectedApproval`.
- The same method-argument IDOR pattern existed in `KnowledgeManager` (`app/Livewire/Organizations/KnowledgeManager.php`): `selectBase`/`activeBase`/`articles`/`editArticle` all trusted a raw client-supplied ID. Fixed by scoping each lookup to `$this->organization->id`.

**Checked and clean:**
- Policies (`AgentApprovalPolicy`, `AgentSkillApprovalPolicy`, `AgentScorecardPolicy`, `PlatformMegaScorecardPolicy`, `UsageRecordPolicy`, `AgentMessagePolicy`, `AgentSessionPolicy`, `DecisionLogPolicy`) all correctly scope `view`/`review` to organization membership, and `review`/approve-reject requires an `owner|admin|manager` pivot role. `SkillApprovalController` and `ProcessSkillApprovalAction`/`ProcessApprovalAction` both re-check `Gate::authorize('review', $approval)` server-side — a client cannot bypass the approver check by hiding a button.
- Prompt-injection guard (`AuditService::detectPromptInjection`, `AiInputSanitizer`) is invoked on every LLM-bound path found: `AgentModelCaller::callWithFailover` (the single choke point both `AgentOrchestrationService::processMessage` and `::executeTask` funnel through), `AgentChat` Livewire before dispatch, `RespondToSocialMessageAction::receiveInbound` (blocks the async AI job on detection), and `WorkflowBuilder` node-config validation. No LLM call path found that skips it.
- Digital Immune System (`DigitalImmuneSystem`) is only invoked from a scheduled console command (`routes/console.php`) — there is no user-facing controller/Livewire action that can trigger, disable, or bypass it.
- `AgentDashboard` Livewire (deployment stats, pending approvals, usage/cost stats) is consistently `organization_id`-scoped.

**Flagged, not modified (heuristic quality, not a security bug):** `DelusionDetectionService`'s scoring is regex/keyword-based (e.g. flags any `\d+%` claim as "suspicious", assumption penalty is a flat `-15` per item) and `WorkflowRiskScoringService`/`DigitalImmuneSystem` thresholds are simple fixed constants. These are unlikely to be adversarially robust — they'd resist obvious gaming for now, but a determined agent output could probably route around the pattern list. This is a scoring-quality question for whoever owns the governance model, not something to hand-edit blind.

**Assessment:** the authorization *shape* (policies, server-side Gate checks in Actions) is correctly designed and, before this pass, was already sound at the Action/Policy layer. The actual gap was the same class of bug found earlier in Dot.Tasks/Dot.Finance: Livewire method arguments bypassing the org scope that the policies exist to enforce. With both instances now fixed, I'd call the governance stack's authorization boundary trustworthy; the scoring heuristics behind it are simplistic and worth a follow-up review by someone who owns the risk model.

## 5. Events Emitted

Dot.Agents defines 57 domain events across its six sub-domains, each with a paired listener chain (audit logging, notification, scorecard/reputation update). The ones most relevant to ecosystem integration:

| Event | Trigger |
|---|---|
| `AgentDeployed` / `AgentPaused` / `AgentResumed` / `AgentDecommissioned` / `AgentUpdated` | Deployment lifecycle transitions |
| `AgentTaskCompleted` / `AgentTaskFailed` / `AgentTaskRated` | Task execution outcomes |
| `AgentChatStarted` | New conversation session opened |
| `AgentDriftDetected` | DIS flags behavioral drift on a deployment |
| `AgentCapabilityContractChanged` | An agent's declared skill/tool boundaries change |
| `ApprovalRequested` / `ApprovalProcessed` | Human-in-the-loop gate opened / resolved |
| `DecisionLogCreated` | A scored AI decision is recorded |
| `SkillExecuted` / `SkillExecutionBlocked` / `SkillAssigned` / `SkillApprovalRequested` / `SkillScoreRecorded` | Skill invocation lifecycle |
| `SecurityThreatDetected` / `SecurityEventResolved` / `KillSwitchActivated` | Security and emergency-stop path |
| `AuditLogExported` | Governance/compliance export |
| `SocialMessageReceived` / `NegativeSentimentDetected` / `ConversationEscalated` / `EscalationHandled` / `PurchaseIntentDetected` / `LeadQualified` / `SocialConversionAchieved` | Social-agent operation |
| `WorkflowCreated` / `WorkflowSaved` / `WorkflowDeleted` / `WorkflowStatusUpdated` | Workflow-builder pipeline changes |
| `PluginInstalled` / `PluginUninstalled` | Marketplace extension lifecycle |
| `OrganizationCreated` / `ConsentRecorded` / `UserDataExported` / `UserDataErased` | Tenant lifecycle and compliance actions |

Full list: `app/Events/*.php` (57 classes).

## 6. Connecting to Dot.Brain

Dot.Agents participates in the ecosystem as a registered platform (`dot-agents`). The relationship has one important distinction to keep straight: **this platform is not the same thing as Dot.Brain's own internal "Agent Colony."** Dot.Brain's `agents/` directory holds charters for Dot.Brain's *own* reasoning agents (Documentation Agent, Colony Agent, Registry Agent, and the domain agents assigned per platform) — those are Dot.Brain's internal workforce for building and maintaining the knowledge graph itself. Dot.Agents, described here, is a separate product: the runtime *this platform's customers* use to hire and run AI agents for their own businesses. The two are related — Dot.Brain's colony charters describe governance patterns that this platform's own governance stack (§4) independently implements for its tenants — but one is not a deployment target of the other, and neither owns the other's data.

Consistent with that boundary, Dot.Agents follows the same layer separation Dot.Memory uses: **the runtime executes agents and their tasks without owning what those agents concluded for a tenant; the platform publishes only its own orchestration telemetry** — deployment lifecycle counts, task success/latency/cost aggregates, delusion-risk and scorecard trends, escalation and approval outcomes, security-event summaries. Tenant task content, conversation transcripts, and agent memory stay inside Dot.Agents' own tenant-scoped tables; they are never published as Knowledge Pack content.

**Knowledge Packs we intend to publish** (payload shape defined by Dot.Brain's DKP schema):

| Payload type | Candidate cadence | Source |
|---|---|---|
| `observation` | weekly | Scorecard aggregates, task success/latency/cost rollups per agent class |
| `insight` | per finding | Routing, batching, and escalation-threshold patterns surfaced across deployments |
| `outcome` | per period | Approval-workflow decisions, DIS interventions and their results |
| `incident` | per incident | Security events, drift-triggered suspensions, kill-switch activations |

Publishing is not yet wired up from this repository — see Roadmap. Full manifest, entity/event mapping, and a worked publish→PR round-trip are maintained on the Brain side at [`platforms/dot-agents.md`](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-agents.md) — that document is Dot.Brain's ingested view and is authoritative for integration mechanics; this wiki is authoritative for what Dot.Agents actually *is* and does today.

## 7. Ecosystem SSO

Dot.Agents shares a PostgreSQL/MySQL instance and Laravel Sanctum handoff-token authentication with the wider InfoDot/Dot ecosystem: a user authenticated through the ecosystem hub gains access here automatically (`EcosystemAuthController`), without a separate signup.

## 8. Roadmap / Open Questions

- [ ] Wire the `ai` queue channel to real inference-job dispatch (currently reserved, unused)
- [ ] Implement the Knowledge Pack publisher described in §6 — no code in this repository yet emits DKP-shaped payloads
- [ ] Decide the tenancy model for Dot.Brain-facing telemetry: platform-level aggregate only, or per-organisation-consented detail
- [ ] Third-party extension-hosted agents (via `AgentPlugin`/Dot.Plug marketplace): should these run under the same governance stack, or does an externally-authored agent need a stricter default confidence threshold and mandatory approval mode?
- [ ] Reconcile this wiki's terminology with Dot.Brain's `runc:<agent-class>` "colony runtime contract" concept (§7 of Dot.Brain's ingested view) — this platform's `deployment_mode` + `confidence_threshold` pair is the closest existing equivalent, but the mapping hasn't been formally agreed

## Change Log

| Version | Date | Author | Change |
|---|---|---|---|
| 0.2.0 | 2026-08-03 | Sakhile Bhayi | Welcome-page hero background pass. The nav/footer brand marks were already the real `public/dot.logos3.png` lockup (byte-identical to `public/images/logo.png`) from an earlier platform-loop pass, so no logo change was needed here. Added a real photographic background to the hero section (`resources/views/welcome.blade.php`), which previously had only a flat `bg-[#1e1660]` fill with a subtle dot-grid pattern and blurred color accents: a data-center server-room photo by Kier in Sight Archives (@kierinsightarchives), unsplash.com/photos/a-close-up-of-a-server-room-3Nwt6w-KU3E, hotlinked via Unsplash's CDN (`images.unsplash.com/photo-1667264501379-c1537934c7ab`). Layered a `#1e1660`-tinted dark overlay plus the existing dot-grid/blur accents on top so the brand's deep-purple palette and text contrast are preserved. Verified the image URL resolves with `curl -sI` (HTTP/2 200) before committing. |
| 0.1.0 | 2026-08-01 | Agents Platform Lead | Initial wiki: architecture, domain entities, governance stack, events, and Dot.Brain connection derived from the actual codebase (Laravel 12, 40 models, 57 events, ADR-001/003, governance-spec.md, database-schema.md) |
| 0.1.1 | 2026-08-01 | Platform Loop Pass | Engineering-quality pass (UI/branding/tests/docs, bounded scope): wired the real logo (`dot.logos3.png`) into favicons and the remaining generic Jetstream logo components (`application-mark`, `application-logo`, `authentication-card-logo`); added a live notification bell (Livewire) reading the existing but previously unused `PlatformNotification` model; fixed `PlatformNotificationFactory`, whose fields (`message`/`severity`/`is_read`) didn't match the actual `platform_notifications` migration schema (`body`/`priority`/`read_at`) — factory-created rows would have failed the NOT NULL `body` column; added dark-mode persistence (localStorage) to the platform layout's existing toggle; added Feature tests for the outer HTTP/middleware layer of the dashboard/marketplace/my-agents pages (previously only covered at the Livewire-component level) and for the new notification bell; removed an unreferenced duplicate logo file at the repo root. Governance/scoring/approval-workflow internals were not touched. See commit for full diff. |
| 0.1.2 | 2026-08-01 | Security Deep Pass | Follow-up governance-internals security review (see §4a). Fixed a cross-tenant IDOR affecting `ApprovalQueue` and `KnowledgeManager` Livewire components, where an unchecksummed method-call argument let any authenticated user read another organization's approval/knowledge-base records. Approval workflow's server-side approve/reject authorization, the prompt-injection guard's coverage across all LLM call paths, and the Digital Immune System's lack of a user-triggerable bypass were all checked and found sound. Commit `b488978`.
