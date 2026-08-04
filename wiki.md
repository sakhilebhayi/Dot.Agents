---
title: Dot.Agents — Platform Wiki
version: 0.4.1
status: active
owners: [Agents Platform Lead]
platform-id: dot-agents
last-review: 2026-08-04
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
| 0.4.1 | 2026-08-04 | Platform-loop pass | Ecosystem sweep for the null-`currentTeam`/null-org-context crash pattern fixed in Dot.Mines commit `0cc4362`. This platform has no Jetstream Teams usage in practice — tenancy runs through `Organization` + `OrganizationContextMiddleware`, which stores `session('current_organization_id')`. Read the middleware carefully: on every request it auto-derives (and auto-creates, for brand-new users) an org from `$user->currentOrganization()` if the session key is unset, so a user genuinely owning zero organizations is auto-provisioned one and never reaches app code with a null context — this differs from Dot.Mines, which has no such auto-create and instead redirects to `teams.create`. However, the middleware's *revalidation* step was the real gap: if a session org id was present but the user's membership had since been revoked, it called `session()->forget('current_organization_id')` and then silently continued the request, leaving every downstream read of `session('current_organization_id')` null for the rest of that request — the exact reachable-null scenario this sweep targets. Fixed `app/Http/Middleware/OrganizationContextMiddleware.php` to `abort(403, 'Unauthorized to access this organization.')` in that case instead, mirroring `EnsureTeamContext`'s abort-on-invalid-team behavior and closing the gap at the source. Then defensively guarded every call site that would otherwise crash or corrupt data if reached with a null org context anyway (defense in depth, e.g. via direct component testing or future code that bypasses the middleware): `SocialCredentials::organization()` and `WorkflowList::createWorkflow()` called `Organization::findOrFail(session(...))` directly — `findOrFail(null)` throws `ModelNotFoundException` instead of a clean 403; `ConnectPlatformWizard::activate()` and `ConnectedAccounts::disconnect()` had the same bug via `(int) session(...)` casting null to `0` before `findOrFail`; `SocialOAuthController::callback()` and `ConnectSocialAccountRequest::authorize()` passed `session('current_organization_id')` straight into `SocialAccountPolicy::create(User $user, int $organizationId)`, whose non-nullable `int` parameter would `TypeError` (not gracefully deny) on a null org; `SocialPostManager::schedulePost()`, `SocialInbox::sendReply()`, and `SentimentMonitor::markHandled()` would silently persist rows with `organization_id => 0` instead of failing loudly. All seven were fixed by routing through a new `App\Livewire\Concerns\ResolvesCurrentOrganization` trait (`resolveCurrentOrganizationId()`, `requireCurrentOrganizationId()`, `requireCurrentOrganization()`) that centralizes what had been an ad-hoc `abort_if(! $orgId, 403, 'No active organization context.')` check already duplicated across `BillingController`, `ApiKeyManager`, `ManagesAgentDeploy`, and `OrganizationSettings` — those four were already correctly guarded and were left untouched as the reference pattern. Audited every other `session('current_organization_id')` read across `app/Livewire`, `app/Http/Controllers`, `app/Http/Requests`, `app/Policies`, and `app/Skills` (≈75 occurrences): the remainder either already guard with `abort_if`/an explicit null check (`DepartmentManager`, `OrgMemberManager`, `KnowledgeManager`, `ApiKeyManager`, `OrganizationSettings`, `ManagesAgentDeploy`, `BillingController`, `DeploymentController::store`), or read-only compare/filter against a possibly-null value in a way that safely denies/empties instead of crashing (all `app/Policies/*.php`, `where('organization_id', session(...))` filters, the `HasOrganizationScope`/`OrganizationScope` global-scope classes, which no-op — not unscope-and-leak — when no context resolves), and were confirmed safe rather than changed. Re-verified the `HasOrganizationScope` trait rollout from the 0.4.0 pass is still intact: every model with an `organization_id` column uses the trait except `AgentPlugin`, which intentionally uses a separate `PluginOrganizationScope` to support nullable-org platform-wide plugins — no gap found, no trait change made. Added `tests/Feature/Middleware/OrganizationContextMiddlewareTest.php` (auto-provisioning for a zero-org user; 403 rejection for a revoked-membership session org; normal load for a valid session org) and a `WorkflowListTest::test_create_workflow_with_no_organization_context_aborts_instead_of_crashing` regression case, mirroring Dot.Mines' `DashboardTest::test_authenticated_user_with_no_team_is_redirected_to_team_creation`. Ran the full suite against a real, isolated Postgres database (`dot_agents_audit_test`, dropped after the run): 792 passed, 45 failed, 7 skipped (1774 assertions) — confirmed via a `git stash` A/B comparison that the same 45 tests fail identically on the pre-change code (pre-existing SQLite-vs-Postgres portability bugs: missing `agent_tasks.latency_ms` column, double-quoted string literals Postgres parses as identifiers, etc., all out of scope for this pass) — zero regressions, and the new/previously-broken tests now pass cleanly. Did not touch database schema, migrations, RLS, or queue/cache/search-index config. |
| 0.4.0 | 2026-08-04 | Platform-loop pass | Finished a consistency-cleanup pass on the pre-existing `HasOrganizationScope` trait (`app/Models/Concerns/HasOrganizationScope.php`) — this trait was **not** created in this pass; it and its sibling `BelongsToOrganization`/`OrganizationScope` were already committed from an earlier session and already applied to all 51 tenant-owned models. A prior agent had started removing the now-redundant explicit `->where('organization_id', ...)` filters from Livewire read queries but was interrupted partway through; this pass finished that cleanup across all 17 in-flight files (`DeploymentManager`, `SlaMonitoringDashboard`, `ApprovalQueue`, `AuditLogViewer`, `DecisionLogViewer`, `DepartmentManager`, `KnowledgeManager`, `SocialCredentials`, `SecurityCenter`, `ConnectPlatformWizard`, `ConnectedAccounts`, `LeadPipeline`, `SentimentMonitor`, `SocialDashboard`, `SocialInbox`, `SocialPostManager`, `WorkflowList`), verifying per-file that every removed filter targeted a model already carrying the global scope and that no mass-assignment, validation-scoping, or genuinely-session-independent query was touched. Audited the full `app/Models/` tree against `HasOrganizationScope` usage: all 51 scoped models were already correctly scoped and no unscoped tenant-owned model was found missing it — the 15 models without the trait (`Agent`, `AgentCategory`, `AgentDepartment`, `AgentPersona`, `AgentPlugin`, `AgentSkill`, `AgentSkillPermission`, `AgentSkillRequirement`, `AgentVersion`, `Membership`, `Organization`, `SubscriptionPlan`, `Team`, `TeamInvitation`, `User`) are deliberately platform-wide catalog/identity models, each with an explicit "intentionally shared" comment in the model itself. No new trait, scope class, or model change was needed. Added `tests/Feature/Security/TenantIsolationTest::test_scope_alone_blocks_cross_organization_access_even_without_an_explicit_where_or_policy_check`, proving the global scope alone (no explicit `where`, no Policy) blocks cross-org reads of `AgentDeployment` via session-based org context — matching the pattern already proven in Dot.Finance (`HasUserScope`) and Dot.Notify (`HasTeamScope`). Ran the full suite against a real, isolated Postgres database (`dot_agents_pilot`) rather than the committed `phpunit.xml`'s in-memory SQLite default, both before and after this pass's changes as a regression check: baseline (pre-change) 45 failed/787 passed, after this pass's changes 42–44 failed/789–791 passed (small run-to-run variance from shared-state factory data, not from these changes) — net zero regressions, all failures are pre-existing, unrelated schema/SQL-portability bugs (e.g. `agent_tasks.latency_ms` doesn't exist as a column, MySQL-style double-quoted string literals in raw SQL that Postgres parses as identifiers, a `departments.deleted_at` column mismatch) predating this pass and out of scope for it. No 403→404 policy-vs-scope behavior change was observed this time (unlike the pattern seen in Dot.Finance) since none of these failures were `assertForbidden()` cases. Added PHPStan + Larastan config (`phpstan.neon.dist`, level 5) — unlike prior expectations, `vendor/bin/phpstan analyse --memory-limit=1G` **did run successfully** in this sandbox and completed in about a minute, finding 452 pre-existing level-5 errors across `app/`; none were introduced by this pass's changes (which only remove redundant `where` calls) and fixing them is out of this pass's scope. `composer audit` found 8 real medium-severity `guzzlehttp/guzzle` and `guzzlehttp/psr7` advisories (CVE-2026-67353/67354/67355/67339/59883, CVE-2026-59882) — fixed via `composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises --with-all-dependencies`; `composer audit` re-run confirmed clean, and the full test suite was re-run after the bump to confirm no breakage (same failure set as before the bump). |
| 0.3.0 | 2026-08-03 | Sakhile Bhayi | First real-execution verification pass against a live PHP/PostgreSQL toolchain (this codebase had never been run before). `composer install` succeeded on stock PHP 8.5 (no version downgrade needed). Fresh `migrate` on an isolated Postgres database surfaced three real bugs, all fixed: (1) `2026_06_09_400010_add_performance_indexes_to_critical_tables.php` queried `sqlite_master` directly to check for existing indexes, which only exists on SQLite and threw "Undefined table: sqlite_master" on Postgres — replaced with Laravel's driver-agnostic `Schema::getIndexes()`; (2) `2026_06_12_143354_create_webhook_deliveries_table.php` and `2026_06_12_143354_create_webhooks_table.php` shared an identical timestamp, and alphabetical filename ordering ran the deliveries table (which has a foreign key to `webhooks`) before the webhooks table existed — renamed the webhooks migration to one second earlier so it runs first. Also found and fixed real bugs in the test suite (invisible to CI because `phpunit.xml` runs entirely against in-memory SQLite, which doesn't exercise Postgres-only code paths and never reaches these failures): `tests/Feature/DashboardPageTest.php` was missing its `use Tests\TestCase;` import (fatal "Class Tests\Feature\TestCase not found"); `tests/Feature/Livewire/ApprovalResponseFormTest.php` referenced the wrong FQCN (`Livewire\ComponentRegistry` instead of `Livewire\Mechanisms\ComponentRegistry`) and then mocked the wrong constructor argument entirely (Livewire's `Form` takes a `Component`, not a `ComponentRegistry`) — fixed to stub an abstract `Livewire\Component`; `tests/Feature/Livewire/ApprovalQueueTest.php`'s `test_select_approval_loads_details` never attached the test user to the organization's membership pivot, so the `AgentApprovalPolicy::view` tenancy check correctly denied access and the assertion saw `null` — added the same `->users()->attach(...)` setup used by every other test in this suite that exercises a policy check; and `database/factories/InvoiceFactory.php` didn't exist at all despite `App\Models\Invoice` using `HasFactory` and `BillingDashboardTest` depending on it — added it following this repo's existing factory conventions. Applied the Dot.Brain ADR-0013 idempotent guard (`Schema::hasTable`/`hasColumn` checks) to this platform's six shared Jetstream-core migrations so they're safe to run in any order against the shared `infodot` database. Final test suite after all fixes: 832 passed, 7 skipped, 0 failed (1877 assertions) — identical before and after applying the ADR-0013 guard, confirming the guard is behavior-neutral on a fresh database. |
| 0.2.0 | 2026-08-03 | Sakhile Bhayi | Welcome-page hero background pass. The nav/footer brand marks were already the real `public/dot.logos3.png` lockup (byte-identical to `public/images/logo.png`) from an earlier platform-loop pass, so no logo change was needed here. Added a real photographic background to the hero section (`resources/views/welcome.blade.php`), which previously had only a flat `bg-[#1e1660]` fill with a subtle dot-grid pattern and blurred color accents: a data-center server-room photo by Kier in Sight Archives (@kierinsightarchives), unsplash.com/photos/a-close-up-of-a-server-room-3Nwt6w-KU3E, hotlinked via Unsplash's CDN (`images.unsplash.com/photo-1667264501379-c1537934c7ab`). Layered a `#1e1660`-tinted dark overlay plus the existing dot-grid/blur accents on top so the brand's deep-purple palette and text contrast are preserved. Verified the image URL resolves with `curl -sI` (HTTP/2 200) before committing. |
| 0.1.0 | 2026-08-01 | Agents Platform Lead | Initial wiki: architecture, domain entities, governance stack, events, and Dot.Brain connection derived from the actual codebase (Laravel 12, 40 models, 57 events, ADR-001/003, governance-spec.md, database-schema.md) |
| 0.1.1 | 2026-08-01 | Platform Loop Pass | Engineering-quality pass (UI/branding/tests/docs, bounded scope): wired the real logo (`dot.logos3.png`) into favicons and the remaining generic Jetstream logo components (`application-mark`, `application-logo`, `authentication-card-logo`); added a live notification bell (Livewire) reading the existing but previously unused `PlatformNotification` model; fixed `PlatformNotificationFactory`, whose fields (`message`/`severity`/`is_read`) didn't match the actual `platform_notifications` migration schema (`body`/`priority`/`read_at`) — factory-created rows would have failed the NOT NULL `body` column; added dark-mode persistence (localStorage) to the platform layout's existing toggle; added Feature tests for the outer HTTP/middleware layer of the dashboard/marketplace/my-agents pages (previously only covered at the Livewire-component level) and for the new notification bell; removed an unreferenced duplicate logo file at the repo root. Governance/scoring/approval-workflow internals were not touched. See commit for full diff. |
| 0.1.2 | 2026-08-01 | Security Deep Pass | Follow-up governance-internals security review (see §4a). Fixed a cross-tenant IDOR affecting `ApprovalQueue` and `KnowledgeManager` Livewire components, where an unchecksummed method-call argument let any authenticated user read another organization's approval/knowledge-base records. Approval workflow's server-side approve/reject authorization, the prompt-injection guard's coverage across all LLM call paths, and the Digital Immune System's lack of a user-triggerable bypass were all checked and found sound. Commit `b488978`.
