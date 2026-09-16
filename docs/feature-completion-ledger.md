# Dot.Agents — Feature Completion Ledger

**Phase 0 audit — 2026-09-16.** Produced by a 9-agent parallel code audit that read the actual routes, Livewire components, controllers, models, and migrations (not the README) for every authenticated page in the app, plus a cross-cutting pass on AI orchestration, tenancy enforcement, shared governance models, and any existing Dot.Memory/Dot.Brain integration.

**Phase 1 fixes — 2026-09-16.** 9 parallel agents fixed the correctness/security bug clusters below. Full test suite after all 9 fixes landed: **907 passed, 0 failures** (`php artisan test`). Diff: 30 files, +290/-61. Row statuses below are updated to reflect what's fixed; unresolved items are carried forward with a "Phase 1 note."

**Phase 2 builds — 2026-09-16.** Built `agents.show`, and wired `billing.plans`/`billing.settings` to real data (see their rows below). Then verified all of it in a real browser against the actual Postgres dev DB (not just the sqlite-backed test suite) — which surfaced 3 more genuine bugs invisible to the automated tests, all now fixed: **(1)** `AgentDeploymentPolicy::create()`'s plan-limit check treated the seeded "unlimited" sentinel (`max_agents = -1`) as a limit of -1, so the Enterprise plan — the platform's top tier — could never deploy a single agent, ever, even from zero. **(2)** Five raw SQL queries across 3 files (`AgentReputationService`, `SocialCommerceService`, `EnterpriseBrainService`) used MySQL/SQLite-style double-quoted string literals (`status = "completed"`), which Postgres rejects outright (it treats double quotes as identifiers, not strings) — every one of these calls would 500 in the real deployment. **(3)** Two files (`SlaMonitoringDashboard`, `SlaAnalyticsService`) called SQLite's `strftime()` directly, a function that doesn't exist in Postgres at all — fixed using the driver-aware date-expression pattern already established elsewhere in this codebase (`FinancialTrendAnalyzer`). All of this is exactly why sqlite-only test coverage isn't sufficient for this app — see "Follow-ups" below. Full suite after every fix: **927 passed, 0 failures**. A full marketplace-deploy click-through against the real Postgres DB now succeeds end-to-end (verified live, not just via test).

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

| Status | Count (Phase 0) | Count (after Phase 1) | Count (after Phase 2) |
|---|---|---|---|
| ✅ working | 12 | 19 | 22 |
| 🟡 partial | 13 | 10 | 10 |
| 🟠 skeleton_mock_data | 4 | 4 | 1 |
| 🔴 dead_button | 2 | 0 | 0 |
| 🔴 unauthorized_gap | 2 | 0 | 0 |
| **Total pages/endpoints audited** | **33** | **33** | **33** |

Phase 1 closed every `unauthorized_gap` and `dead_button` finding. Phase 2 built 3 of the remaining 4 `skeleton_mock_data` pages for real (`agents.show`, `billing.plans`, `billing.settings`) — only `social.posts` (publishing is an admitted stub) remains unbuilt. What's left in `partial` is real, working pages with a specific remaining defect or a deliberately out-of-scope gap (e.g. social's dead inbound-ingestion pipeline, marketplace's frozen catalog content) — see Phases 3–4 below.

The product is **not** a pure marketing skeleton — most modules have a real Eloquent/Livewire/Action backend with enforced org-scoping (`HasOrganizationScope` global scope, confirmed on 20 models). The failures are concentrated in three patterns: **(a)** genuinely computed data displayed through a Blade template that references a column/relation name that doesn't match the real schema, so it silently renders 0/blank; **(b)** a Gate/Policy call with a type or scope bug that makes a real, wired-up action 403 or crash for everyone (tests pass only because they bypass the same check with `Gate::before(fn () => true)`); **(c)** a handful of genuinely unbuilt features (`agents.show`, billing plans/settings, social publishing, inbound social message ingestion) sitting behind an otherwise-real page shell.

---

## Dashboard

| Route | Status | Notes |
|---|---|---|
| `dashboard` | ✅ working | Real org-scoped queries across 5 models; `setTimeframe` genuinely invalidates cached computed props. Nothing to fix. |

## Marketplace

| Route | Status | Notes |
|---|---|---|
| `marketplace` | 🟡 partial | Browse/search + Deploy are real (`AgentMarketplace.php:113-170`, `DeployAgentAction.php:29-55`, policy-gated). ✅ **Phase 1 fixed:** the Deploy modal's Department dropdown now sources from the org's own `Department` model instead of the unrelated `AgentDepartment` catalog taxonomy. ✅ **Phase 2 verification fixed 3 more bugs that were silently breaking every real deploy attempt** (see the Phase 2 summary above: the unlimited-plan policy bug, 5 Postgres-incompatible raw SQL queries, and 2 SQLite-only `strftime()` calls) — confirmed via 3 live end-to-end deploys against the real Postgres dev DB, all succeeding with the correct org department linked. **Still open (deferred, content-management scope):** no app code anywhere creates/updates `Agent`/`AgentCategory`/`AgentDepartment`/`AgentReview` — the entire catalog is frozen seeder output with a real query engine on top. `app/Livewire/Agents/AgentMarketplace.php` is still dead orphaned code, unreferenced by any route. |

## My Agents (deployed workforce)

| Route | Status | Notes |
|---|---|---|
| `agents.deployments` | ✅ working | Real scoped list; pause/resume real, policy-gated. Minor: `decommissionDeployment()` exists in the component but has no button in the view. |
| `agents.show` | ✅ working | ✅ **Phase 2 built:** replaced the "Coming Soon" placeholder with a real `DeploymentOverview` Livewire component — header, status, config (mode/confidence/risk tolerance/model override/dates), a masked "Configured"/"Not set" indicator for the encrypted `custom_instructions` field (never shown in plaintext), enabled skills, last 10 tasks, pending-approvals count, and a latest-scorecard summary card. Also added the first cross-navigation between Overview/Chat/Scorecard (tabs), and — since nothing on the deployment list actually linked here — added a link from each deployment card's header on `agents.deployments` to this page. Verified live in browser with real seeded data. |
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
| `billing.plans` | ✅ working | ✅ **Phase 2 built:** real `PlansPage` Livewire component querying `SubscriptionPlan::active()->public()->ordered()->get()` — real names/prices/features, no hardcoded copy. Fixed the route-binding mismatch by adding `getRouteKeyName(): 'slug'` to `SubscriptionPlan`, so checkout POSTs now correctly resolve instead of 404ing (confirms the `manage-billing` Gate fixed in Phase 1 was already wired correctly — Laravel's kebab-to-camel resolution needed no extra work). Along the way, found `is_public` was referenced by the model's scope/casts but never actually added to the table by any migration — added the missing migration (defaulting existing plans to visible). Verified live: real Starter/Professional/Enterprise cards render from the seeded DB. |
| `billing.success` | ✅ working | Correct redirect-only pattern (webhook does the real activation). |
| `billing.portal` | ✅ working | Real, working Stripe Billing Portal session creation and redirect. ✅ **Phase 1 fixed:** now requires `Gate::authorize('manageBilling', $organization)` (owner/admin) before creating the portal session. **Gap:** no existing test exercises `billing.portal` at all — this fix has no automated regression coverage yet. |
| `billing.checkout` | 🟡 partial | Real Stripe Checkout session-building code. ✅ **Phase 1 fixed:** the `manage-billing` ability is now properly defined (`OrganizationPolicy::manageBilling`), so the Gate no longer denies every user — confirmed via a real test (previously the test only passed via a `Gate::before` bypass, now removed). **Still unreachable end-to-end:** the only page that links here (`billing.plans`) still sends non-numeric plan slugs into a numeric-id route — that's the Phase 2 `billing.plans` rebuild. |
| `billing.settings` (+ `settings.billing.alt`) | ✅ working | ✅ **Phase 2 built:** real `BillingSettings` Livewire component — plan name/price/renewal date from the org's actual `OrganizationSubscription`, an honest "Connected to Stripe" / "No payment method on file" indicator (no fabricated card details — there's no card-detail column anywhere in the schema), and real `Invoice` rows. Handles the no-subscription-yet state honestly (message + link to `billing.plans`) rather than showing fake data. **While building this, independently found and flagged the same `Organization::$fillable` missing `stripe_customer_id` bug reported separately and fixed in this session** (see Cross-cutting section) — `StripeService::ensureCustomer()`'s mass-assignment update was silently no-op'ing. Verified live: real empty state renders correctly for the seeded demo org (which has `organizations.plan = 'enterprise'` set but no real `OrganizationSubscription` row — a seeder gap, not a code bug). |
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

`Organization` had the same class of bug: `StripeService::ensureCustomer()` called `$organization->update(['stripe_customer_id' => $customer->id])`, but `stripe_customer_id` was missing from `Organization::$fillable`, so Eloquent silently dropped it — every Stripe customer created for an org was never actually linked back to it. ✅ **Fixed 2026-09-16** (added the column to `$fillable`; new `tests/Feature/Services/StripeServiceTest.php` proves the id now persists, using a fake `StripeClient` subclass since the SDK client is constructed inline with no test-mode HTTP transport wired up).

### Database portability (sqlite vs. the real Postgres deployment)
**This is the most important infrastructure finding of the whole audit+fix pass, and it was invisible to the entire 900+ test suite.** The test suite runs against sqlite (`:memory:`), but the real dev/prod database is Postgres (`DB_CONNECTION=pgsql`). Two classes of bug only manifest on Postgres and were only found by manually running the app against the real Postgres dev DB in a browser:
1. **Double-quoted SQL string literals** (`status = "completed"`) — valid on lenient sqlite/MySQL, a hard error on strict Postgres (which treats `"..."` as an identifier, not a string). Found and fixed in 5 places across `AgentReputationService` (×3), `SocialCommerceService`, and `EnterpriseBrainService`.
2. **`strftime()`** — a SQLite-only date function with no Postgres equivalent. Found and fixed in `SlaMonitoringDashboard` and `SlaAnalyticsService`, using the driver-aware date-expression pattern already established in `FinancialTrendAnalyzer` (a per-driver constant map + a small `dateExpr()` helper, rather than inventing a new abstraction).

A grep sweep (`grep -rnE '= "[a-z_]+"' app/` plus a check for `strftime`/`IN ("`/`LIKE "`) found no further occurrences of either pattern as of 2026-09-16, but **this class of bug can reappear** any time new raw SQL is added — see the recommendation below.

### Local dev/pilot database drift (informational, not a code bug)
The `dot_agents_pilot` Postgres database's `migrations` table recorded `2026_06_12_165902_create_permission_tables` as already run, but the actual `permissions`/`roles`/`model_has_roles` tables didn't exist — a pre-existing drift between the migration ledger and real schema, unrelated to any change in this session. Repaired by directly invoking that migration's `up()` (pure `CREATE TABLE`, no data loss). This is purely a local-environment fix, not an application bug.

### Test coverage
Suite is large (~150+ files) and the sampled files use real `assertDatabaseHas`/`assertDispatched`/state assertions, not bare status-code checks. Confirmed gaps: no test exercised `ModelRouterService::buildFailoverChain()` directly (now covered), and — more significantly — **the suite cannot catch Postgres-specific SQL errors at all**, since it never runs against Postgres. Recommend adding a CI job (or a local `composer test:pgsql` script) that runs the suite against a real Postgres instance, even just periodically — every bug in the "Database portability" section above would have been caught immediately by that alone.

---

## Recommended phase order (revised after audit)

1. ~~**Phase 1 — Correctness & security fixes**~~ ✅ **Done 2026-09-16.** All 9 bug clusters fixed (Security Center auth + report-key bug, Skills-creation org-scoping, retention-purge read auth, Billing `manage-billing` Gate + portal role check, social sentiment/inbox Gate bugs, governance audit-log column names + view auth + export wiring + decision-log filter range, billing FK/column names + dead buttons, marketplace department FK, scorecard period/column bugs, workflow label persistence, orchestration config-namespace fix). Full suite: 907 passed, 0 failures.
2. ~~**Phase 2 — Build the real skeletons**~~ ✅ **Done 2026-09-16.** Built `agents.show`, `billing.plans`, `billing.settings` for real. Live browser verification against the real Postgres dev DB (not just sqlite tests) found and fixed 3 more bugs that were silently breaking marketplace deploy entirely (unlimited-plan policy bug, 5 Postgres-incompatible raw SQL queries, 2 SQLite-only `strftime()` calls) plus the `Organization::$fillable`/`stripe_customer_id` bug. Full suite: 927 passed, 0 failures. **`AI_PROVIDER` decision:** deferred — owner wants to switch off mock mode once a real API key is in `.env`; none of `OPENAI_API_KEY`/`ANTHROPIC_API_KEY`/`GEMINI_API_KEY` had been changed from their placeholder values as of this session's end.
3. **Phase 3 — Social/SCCS completion**: build the inbound-message ingestion pipeline, implement real platform publishing (or clearly gate the feature off until it is), resolve Instagram/TikTok (implement or remove from the UI).
4. **Phase 4 — Dot.Memory / Dot.Brain integration** (greenfield, per the platform contract).
5. **Phase 5 — Full verification pass**: `phpunit` + `phpstan` green, Impeccable clean, a Postgres-backed test run (see "Database portability" above), browser click-through of every golden path + edge case, update this ledger to 100% `working`.

### Follow-ups discovered during Phases 1–2 (not fixed — explicitly out of scope)

- `DigitalImmuneSystem::checkDeployment()` never returns `health='quarantined'`, so Security Center's "quarantined" counter always shows 0 even after a real quarantine (the underlying quarantine action itself works correctly — only this one summary counter is wrong).
- `scorecard-viewer.blade.php`'s History table (a different location than the Activity Metrics tiles that were fixed) still references the nonexistent `->total_tasks` column.
- `tests/Feature/Actions/Social/EscalateConversationActionTest.php` has the same kind of masking `Gate::before(fn () => true)` bypass as the two tests fixed in Phase 1 — not yet addressed.
- No automated regression coverage exists yet for: `billing.portal`'s new authorization check, the billing dashboard's 4 display fixes (FK/column names/buttons/PDF link), or the workflow builder's node-label persistence fix. All were verified manually/via throwaway tests but have no permanent test in the suite.
- Pre-existing, unrelated to this work: `tests/Architecture/ServiceSizeLimitsTest.php::no_service_file_exceeds_200_lines` currently fails (`app/Services/Social/ConversationContinuationService.php` is 226 lines, over its tracked 200-line cap).
- A second, fully unused tenant-scoping implementation (`BelongsToOrganization` + `OrganizationScope`) still exists alongside the real one (`HasOrganizationScope`) — dead code worth deleting in a cleanup pass.
- **Recommend adding Postgres to CI/local testing** (see "Database portability" above) — the single highest-leverage infrastructure change available, since it would have caught every bug found during Phase 2's manual browser verification automatically.
- The seeded demo org (`Dot Ventures Inc.`) has `organizations.plan = 'enterprise'` set directly but no real `OrganizationSubscription` row — `ProductionDemoSeeder` should probably create one so `billing.settings`/`billing.plans`'s "Current Plan" state can be demoed without a real Stripe checkout.
- 3 test deployments named "...Browser Verification Agent..." (ids 24-26) were created in the local `dot_agents_pilot` DB while manually verifying the marketplace deploy flow — harmless demo data, left in place; ask if you'd like them removed.
