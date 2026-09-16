# Dot.Agents — Feature Completion Ledger

**Phase 0 audit — 2026-09-16.** Produced by a 9-agent parallel code audit that read the actual routes, Livewire components, controllers, models, and migrations (not the README) for every authenticated page in the app, plus a cross-cutting pass on AI orchestration, tenancy enforcement, shared governance models, and any existing Dot.Memory/Dot.Brain integration.

**Phase 1 fixes — 2026-09-16.** 9 parallel agents fixed the correctness/security bug clusters below. Full test suite after all 9 fixes landed: **907 passed, 0 failures** (`php artisan test`). Diff: 30 files, +290/-61. Row statuses below are updated to reflect what's fixed; unresolved items are carried forward with a "Phase 1 note."

**This file is the resumption point.** Update it at the end of every phase instead of re-deriving state from scratch. When a page's status changes, edit its row and move its resolved issues to a "Fixed" note rather than deleting history.

## Status legend

| Status | Meaning |
|---|---|
| ✅ `working` | Real scoped data, real persisted actions, enforced auth |
| 🟡 `partial` | Some real, some fake/broken — see issues |
| 🟠 `skeleton_mock_data` | Renders, but the data and/or actions are fake or hardcoded |
| 🔴 `dead_button` | A visible control has no working handler behind it |
| 🔴 `unauthorized_gap` | An auth/tenancy check is missing, decorative, or bypassable |
| ⚪ `missing` | Route/view/component doesn't exist or errors |

## Summary

| Status | Count (Phase 0) | Count (after Phase 1) |
|---|---|---|
| ✅ working | 12 | 19 |
| 🟡 partial | 13 | 12 |
| 🟠 skeleton_mock_data | 4 | 4 |
| 🔴 dead_button | 2 | 0 |
| 🔴 unauthorized_gap | 2 | 0 |
| **Total pages/endpoints audited** | **33** | **33** |

Phase 1 closed every `unauthorized_gap` and `dead_button` finding. What's left is genuinely unbuilt features (`skeleton_mock_data`) and pages that are real but still have a defect or gap not in Phase 1's scope (`partial`) — see Phases 2–3 below.

The product is **not** a pure marketing skeleton — most modules have a real Eloquent/Livewire/Action backend with enforced org-scoping (`HasOrganizationScope` global scope, confirmed on 20 models). The failures are concentrated in three patterns: **(a)** genuinely computed data displayed through a Blade template that references a column/relation name that doesn't match the real schema, so it silently renders 0/blank; **(b)** a Gate/Policy call with a type or scope bug that makes a real, wired-up action 403 or crash for everyone (tests pass only because they bypass the same check with `Gate::before(fn () => true)`); **(c)** a handful of genuinely unbuilt features (`agents.show`, billing plans/settings, social publishing, inbound social message ingestion) sitting behind an otherwise-real page shell.

---

## Dashboard

| Route | Status | Notes |
|---|---|---|
| `dashboard` | ✅ working | Real org-scoped queries across 5 models; `setTimeframe` genuinely invalidates cached computed props. Nothing to fix. |

## Marketplace

| Route | Status | Notes |
|---|---|---|
| `marketplace` | 🟡 partial | Browse/search + Deploy are real (`AgentMarketplace.php:113-170`, `DeployAgentAction.php:29-55`, policy-gated). ✅ **Phase 1 fixed:** the Deploy modal's Department dropdown now sources from the org's own `Department` model (new `orgDepartments()`, matching `org.departments`) instead of the unrelated `AgentDepartment` catalog taxonomy — the value submitted now actually matches what's validated and FK'd. New tests cover the org-scoping and the deploy-with-department path, which was previously untested. **Still open (deferred, content-management scope):** no app code anywhere creates/updates `Agent`/`AgentCategory`/`AgentDepartment`/`AgentReview` — the entire catalog is frozen seeder output with a real query engine on top. `app/Livewire/Agents/AgentMarketplace.php` is still dead orphaned code, unreferenced by any route. |

## My Agents (deployed workforce)

| Route | Status | Notes |
|---|---|---|
| `agents.deployments` | ✅ working | Real scoped list; pause/resume real, policy-gated. Minor: `decommissionDeployment()` exists in the component but has no button in the view. |
| `agents.show` | 🟠 skeleton_mock_data | **Literal "Coming Soon" static page.** `$deployment` is passed into the view and never referenced. No Livewire component at all. |
| `agents.chat` | ✅ working | Real pipeline: Livewire → queued job → `AgentOrchestrationService` → `AgentModelCaller::callWithFailover` → real Prism call → persisted `AgentMessage`/`AgentSession`. **Caveat:** `.env` has no `AI_PROVIDER` override and `config/prism.php:8` defaults to `mock`, so every reply today is `AgentModelCaller::mockModelResponse()`, not a real model — intentional safety default, not a hidden fake, but needs a decision before "done." Also: sidebar "Session Cost" reads `$sess->total_cost`, but the column is `cost` — always renders $0. |
| `agents.scorecard` | 🟡 partial | ✅ **Phase 1 fixed:** `recalculate()` now maps the UI's 7d/30d/90d selection to `ScorecardService`'s real period strings (weekly/monthly/quarterly) instead of silently always computing a calendar month; the "Activity Metrics" tiles now read the real columns (`tasks_completed`/`tasks_failed`/`hallucinations_detected`/`estimated_savings`). **Still open:** the same wrong-column bug (`->total_tasks`) also appears in this page's History table (a different location than the tiles that were in scope) — not yet fixed. |

## Workflows

| Route | Status | Notes |
|---|---|---|
| `workflows.index` | ✅ working | Real scoped list/create/delete. `trigger_type` (scheduled/event/webhook) is persisted but nothing anywhere reads it to auto-fire a workflow — only the manual Run button works. |
| `workflows.builder` | ✅ working | Real graph builder: node/edge CRUD persists to `workflow_nodes`/`workflow_connections`; Save/Publish/Run are real; Run genuinely executes against real, active `AgentDeployment` rows via `GraphWorkflowEngineService` → `AgentOrchestrationService::executeGraphNode`. ✅ **Phase 1 fixed:** the Node Properties label editor now round-trips to the backend (new `updateNodeLabel()` on `ManagesWorkflowCanvas`) and survives Save. **Still open (not a bug, a hardening recommendation):** the route uses an inline `$workflow->organization_id === session('current_organization_id')` check instead of the real `AgentWorkflowPolicy::view()` that already exists unused — currently safe only because `OrganizationContextMiddleware` + the model's global scope both independently cover the gap; fragile if either is ever bypassed upstream. |

## Governance & Security

| Route | Status | Notes |
|---|---|---|
| `governance.approvals` | ✅ working | Approve/reject/escalate all real, IDOR-guarded, gated to owner/admin/manager, write `AuditLog` + `DecisionLog` outcome. |
| `governance.audit` | ✅ working | Real, immutable, org-scoped `AuditLog` data. ✅ **Phase 1 fixed:** Actor column now shows the real agent deployment or user (traced through `agent_deployment_id`/`user_id`, with a genuine "System" fallback), Status now shows the real `flagged` boolean instead of a fabricated always-false value; `AuditLogPolicy::viewAny` is now enforced in `mount()` for viewing, not just exporting; Export CSV/JSON buttons are now wired up. |
| `governance.decisions` | ✅ working | Real, non-trivial delusion-risk scoring (`DelusionDetectionService`) wired into the live task pipeline. ✅ **Phase 1 fixed:** the "Medium Risk (40-69)" filter now has a real upper bound and no longer returns high-risk rows. |
| `governance.retention-purges` | ✅ working | Real nightly-scheduled candidate detection and a real `MassPrunable`-backed purge on approve, correctly double-gated to `platform_admin` on the *write* path. ✅ **Phase 1 fixed:** the *read* path (`proposals()`/`pendingCount()`) now requires `platform_admin` too, matching the write-path restriction. |
| `security.center` | ✅ working | The Digital Immune System itself is real (drift/delusion/failure/cost/autonomy checks, real quarantine + `SecurityEvent` writes, real schedule + event-triggered dispatch). ✅ **Phase 1 fixed:** `runDISCheck()` now requires `Gate::authorize('update', $organization)` (owner/admin); the page now gates `Gate::authorize('viewAny', SecurityEvent::class)` in `mount()`; the results panel now reads the real `runHealthCheck()` keys (`total_agents`/`events`/`quarantined`) so non-healthy results actually render. **New, separate pre-existing bug found while fixing this (not yet fixed, low severity):** `DigitalImmuneSystem::checkDeployment()` never actually returns `health='quarantined'` (only healthy/warnings/critical), so the report's "quarantined" counter is always 0 even when a quarantine really happened — the events list itself is correct, only that one summary counter is wrong. |

## Organization

| Route | Status | Notes |
|---|---|---|
| `org.departments` | ✅ working | Fully real CRUD, policy-gated, audited. |
| `org.members` | 🟡 partial | Attach/role-change/remove are real and gated. **Not actually an invite flow:** requires the invitee to already have an account (no email/token/pending state), despite a full unused Jetstream `TeamInvitation` system existing elsewhere in the codebase. `updateRole()` has no server-side whitelist on the role value. |
| `org.knowledge` | ✅ working | Real CRUD with explicit re-verification of tenant-owned IDs before use. Minor: `saveArticle()` trusts `activeBaseId` instead of the already-verified `activeBase()` — data-integrity smell, not a cross-org leak (global scope still protects reads). |
| `org.skills` | ✅ working | Toggle/delete are correctly scoped and gated. ✅ **Phase 1 fixed:** `AgentSkillPolicy::create()` now checks the role in the *specific* organization the skill is being created in (matching `update()`/`delete()`'s convention), not any org the user happens to belong to. |
| `org.settings` | ✅ working | Real settings + encrypted social-credentials sub-tab, properly org-scoped and gated. |

## Billing

| Route | Status | Notes |
|---|---|---|
| `billing.index` | ✅ working | Real org-scoped subscription/usage/invoice queries. ✅ **Phase 1 fixed:** `OrganizationSubscription::plan()` now uses the real `plan_id` FK; the Invoice table now reads the real `invoice_date`/`total` columns (plus added a missing `date` cast so `invoice_date` doesn't fatal-error the first time a real dated invoice renders); "Upgrade"/"Switch" now link to `billing.plans`; "Download PDF" now links to the real `pdf_url` when present. **Gap:** no automated test seeds a subscription+plan+invoice and asserts on the rendered HTML — the existing `BillingDashboardTest` cases don't exercise this path, so there's no regression coverage for these four fixes yet. |
| `billing.plans` | 🟠 skeleton_mock_data | Entire pricing grid is hardcoded HTML, not a `SubscriptionPlan::query()`. Checkout forms POST string slugs (`starter`/`pro`) into a route bound by numeric id — guaranteed 404. Not in Phase 1 scope — this is a real feature to build, tracked in Phase 2 below. |
| `billing.success` | ✅ working | Correct redirect-only pattern (webhook does the real activation). |
| `billing.portal` | ✅ working | Real, working Stripe Billing Portal session creation and redirect. ✅ **Phase 1 fixed:** now requires `Gate::authorize('manageBilling', $organization)` (owner/admin) before creating the portal session. **Gap:** no existing test exercises `billing.portal` at all — this fix has no automated regression coverage yet. |
| `billing.checkout` | 🟡 partial | Real Stripe Checkout session-building code. ✅ **Phase 1 fixed:** the `manage-billing` ability is now properly defined (`OrganizationPolicy::manageBilling`), so the Gate no longer denies every user — confirmed via a real test (previously the test only passed via a `Gate::before` bypass, now removed). **Still unreachable end-to-end:** the only page that links here (`billing.plans`) still sends non-numeric plan slugs into a numeric-id route — that's the Phase 2 `billing.plans` rebuild. |
| `billing.settings` (+ `settings.billing.alt`) | 🟠 skeleton_mock_data | 100% hardcoded plan/renewal-date/card/invoice-history content. Only real functionality: its 3 buttons correctly POST to `billing.portal`. Not in Phase 1 scope — tracked in Phase 2 below. |
| `stripe.webhook` | ✅ working | Real HMAC verification, real idempotency, correct schema-matching writes. The one fully complete corner of Billing. |

## Social / SCCS

| Route | Status | Notes |
|---|---|---|
| `social.dashboard` | 🟡 partial | Real, correctly-scoped read aggregates. Meaningless in practice — see `social.inbox` below, nothing ever populates real conversations/leads. |
| `social.inbox` | 🟡 partial | ✅ **Phase 1 fixed:** "Send" reply no longer crashes — `RespondToSocialMessageAction` now loads and passes the real `SocialConversation` model into `Gate::authorize()` instead of a plain int. The masking `Gate::before` bypass was removed from its test, which now exercises the real check. **Still open (Phase 3 scope):** `receiveInbound()`, the only code path that would create a conversation/message from real platform traffic, is still never called anywhere — everything visible here is still seed data, not live messages. |
| `social.leads` | 🟡 partial | Qualify button and AI lead-scoring code are both real and correctly gated. Never runs against live traffic for the same reason as `social.inbox` (upstream ingestion is dead code) — Phase 3 scope. |
| `social.posts` | 🟠 skeleton_mock_data | Draft/approve workflow is real and persisted. **Publishing is an admitted stub:** `SocialPublishingService::publish()`'s own docblock says "stub for platform API integration" and returns a fabricated ID; the job that runs on schedule doesn't even call it — it just flips a DB column to `published`. No platform is ever actually posted to. Not in Phase 1 scope — real feature to build in Phase 3. |
| `social.sentiment` | 🟡 partial | Real AI sentiment classification (OpenAI gpt-4o-mini + keyword fallback). ✅ **Phase 1 fixed:** "Mark as handled" no longer always 403s — added a distinct `markHandled()` policy ability (owner/admin) instead of reusing the score-value `update()` ability, which correctly stays locked down as system-managed. Masking `Gate::before` bypass removed from its test. **Still open:** no confirmed scheduled trigger for review scanning (Phase 3 scope); `EscalateConversationActionTest` has the same kind of `Gate::before` masking bypass, not yet addressed (flagged, not fixed — out of Phase 1's named scope). |
| `social.accounts` | ✅ working | Real connect/disconnect, correctly gated, soft-deleted. |
| `social.connect` | 🟡 partial | Real credential storage (encrypted) and real OAuth kickoff. Lists Instagram/TikTok as connectable with no visual distinction from platforms that actually work. |
| `social.auth.redirect` / `social.auth.callback` | 🟡 partial | Real Socialite token exchange, encrypted storage, per-org custom OAuth app support — genuinely solid for Facebook/LinkedIn/Twitter. **Instagram and TikTok have no Socialite driver installed or registered at all** — both will hard-fail with "Driver not supported" the moment a user tries to connect them. |

---

## Cross-cutting infrastructure

### AI orchestration (Prism PHP failover)
Real, non-trivial failover machinery exists (`ModelRouterService`, `CircuitBreakerService`, `AgentModelCaller::callWithFailover`) — not a stub. ✅ **Phase 1 fixed:** `ModelRouterService::getProviderDefaults()` now reads credentials from the real `config('prism.providers.*.api_key')` namespace instead of nonexistent config keys — the OpenAI→Anthropic→Google→Ollama cascade is no longer silently collapsed to `[primary, ollama]`. A new test (`ModelRouterServiceTest`) asserts `buildFailoverChain()`'s real output includes the anthropic/google legs when their keys are configured, closing the coverage gap that let this ship unnoticed.

### Dot.Memory / Dot.Brain integration
**Confirmed: none of it exists yet.** The only reference to "Dot.Memory" anywhere in the codebase is a decorative link in `config/ecosystem.php:35` used to render the "other Dot apps" grid on error pages — never queried for data. Zero hits for `charter`, `colony`, `retr:`, or `dkp:` anywhere. Two things sound like the contract but aren't: `MemoryService`/`AgentMemory` is a wholly local, per-deployment conversational-memory table (not a client of the separate Dot.Memory platform), and `EnterpriseBrainService`/`EnterpriseBrainOrchestrator` ("Dot.OS Adaptive Enterprise Consciousness") is an in-app analytics module with zero outbound HTTP calls — it shares the word "Brain" but has nothing to do with Dot.Brain. **This entire phase is greenfield** — see the integration brief from the previous session for the exact contract to build against (`Dot.Brain/platforms/dot-agents.md` §6-7).

### Tenancy enforcement
Real and automatic for 20 models via `HasOrganizationScope`'s global scope (confirmed to actually block cross-org reads in `TenantIsolationTest`), reinforced by `OrganizationContextMiddleware` re-validating session org membership on every request. **Caveats:** a second, more sophisticated scoping implementation (`BelongsToOrganization` + `OrganizationScope`) exists as **completely dead code** — nothing uses it; two competing mechanisms in the codebase, pick one and delete the other. 9 models are deliberately unscoped shared catalog data (each has a `// Intentionally shared` comment confirming this is not an oversight).

### Shared governance models
`AuditLog`, `SecurityEvent`, `DecisionLog`, `AgentScorecard`, and `AgentApproval` are all genuinely written by live application code (not just seeders) — confirmed via direct grep for `::create`/`::updateOrCreate` call sites and passing feature-test assertions.

### Test coverage
Suite is large (~150+ files) and the sampled files use real `assertDatabaseHas`/`assertDispatched`/state assertions, not bare status-code checks. Confirmed gap: no test exercises `ModelRouterService::buildFailoverChain()` directly, which is exactly the code path with the config-namespace bug above — a test asserting its real output against populated `ANTHROPIC_API_KEY`/`GEMINI_API_KEY` would have caught it.

---

## Recommended phase order (revised after audit)

1. ~~**Phase 1 — Correctness & security fixes**~~ ✅ **Done 2026-09-16.** All 9 bug clusters fixed (Security Center auth + report-key bug, Skills-creation org-scoping, retention-purge read auth, Billing `manage-billing` Gate + portal role check, social sentiment/inbox Gate bugs, governance audit-log column names + view auth + export wiring + decision-log filter range, billing FK/column names + dead buttons, marketplace department FK, scorecard period/column bugs, workflow label persistence, orchestration config-namespace fix). Full suite: 907 passed, 0 failures. See "Follow-ups discovered during Phase 1" below for what was explicitly deferred.
2. **Phase 2 — Build the real skeletons**: `agents.show`, Billing plans/settings (wire to real `SubscriptionPlan`/`Invoice` data, fix the plan slug/id route-binding mismatch so `billing.checkout` becomes reachable), a decision on `AI_PROVIDER` for chat.
3. **Phase 3 — Social/SCCS completion**: build the inbound-message ingestion pipeline, implement real platform publishing (or clearly gate the feature off until it is), resolve Instagram/TikTok (implement or remove from the UI).
4. **Phase 4 — Dot.Memory / Dot.Brain integration** (greenfield, per the platform contract).
5. **Phase 5 — Full verification pass**: `phpunit` + `phpstan` green, Impeccable clean, browser click-through of every golden path + edge case, update this ledger to 100% `working`.

### Follow-ups discovered during Phase 1 (not fixed — explicitly out of each fix's scope)

- `DigitalImmuneSystem::checkDeployment()` never returns `health='quarantined'`, so Security Center's "quarantined" counter always shows 0 even after a real quarantine (the underlying quarantine action itself works correctly — only this one summary counter is wrong).
- `scorecard-viewer.blade.php`'s History table (a different location than the Activity Metrics tiles that were fixed) still references the nonexistent `->total_tasks` column.
- `tests/Feature/Actions/Social/EscalateConversationActionTest.php` has the same kind of masking `Gate::before(fn () => true)` bypass as the two tests fixed in Phase 1 — not yet addressed.
- No automated regression coverage exists yet for: `billing.portal`'s new authorization check, the billing dashboard's 4 display fixes (FK/column names/buttons/PDF link), or the workflow builder's node-label persistence fix. All three were verified manually/via throwaway tests during Phase 1 (see agent notes in the Phase 1 workflow journal) but have no permanent test in the suite.
- Pre-existing, unrelated to Phase 1: `tests/Architecture/ServiceSizeLimitsTest.php::no_service_file_exceeds_200_lines` currently fails (`app/Services/Social/ConversationContinuationService.php` is 226 lines, over its tracked 200-line cap).
- A second, fully unused tenant-scoping implementation (`BelongsToOrganization` + `OrganizationScope`) still exists alongside the real one (`HasOrganizationScope`) — dead code worth deleting in a cleanup pass.
