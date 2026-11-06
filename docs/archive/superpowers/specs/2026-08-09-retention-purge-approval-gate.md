# Dot.Agents: Retention Purge Approval Gate + Overdue Approval Expiry

## Context

Dot.Agents already has a real, working Level 2 governance system — `AgentApproval`/`AgentSkillApproval`, `ProcessApprovalAction`, `ApprovalQueue` Livewire component, `AgentApprovalPolicy` — confirmed against real code, not aspirational. This spec doesn't build a Level 2 process from scratch; it closes two specific violations the platform's own autonomy audit (`Dot.Brain/platforms/dot-agents.md`, 2026-08-08) found in the otherwise-solid picture.

**Violation 1 — a scheduled command that doesn't exist.** `routes/console.php` schedules `Schedule::command('approvals:expire-overdue')->everyThirtyMinutes()`, but no `approvals:expire-overdue` command class exists anywhere in `app/Console/Commands` — confirmed via a full-tree grep. Both `AgentApproval` (whose migration comment lists `expired` as a valid `status` value) and `AgentSkillApproval` (which has a first-class `STATUS_EXPIRED` constant and a live `isExpired(): bool` computed check — `expires_at?->isPast() && status === STATUS_PENDING`) already anticipate this state. Nothing ever actually transitions a stale `pending` approval into it.

**Violation 2 — permanent deletion running as Level 1.** `routes/console.php` also schedules `Schedule::command('model:prune', ['--model' => [AuditLog::class, AgentMessage::class, AgentTask::class, AgentSession::class, AgentSkillExecution::class, PlatformMegaScorecard::class]])->dailyAt('02:00')`. Five of these six models (`AgentMessage` doesn't) implement `MassPrunable` with their own `prunable()` query, each with its own retention window (`AuditLog`: 730 days via `config('audit.retention_days')`; `AgentTask`: 90 days; etc.) — confirmed by reading each model's `prunable()` method. `MassPrunable::prune()` is a raw query-builder mass delete: permanent, cross-organization (each `prunable()` query explicitly calls `withoutGlobalScope('organization')`), and bypasses Eloquent events and policies entirely (the very `AuditLogPolicy` that marks `AuditLog::delete()` as always `false` for ordinary users has no say over this path). The audit's own gap summary names this directly: permanent deletion is brain.autonomy.md §2's named Level 3 example — "nothing built under this program may execute [it] autonomously" — and this process currently does exactly that, unattended, nightly.

A real, existing `platform_admin` Spatie role already governs cross-tenant, compliance-adjacent actions in this codebase (`AuditLogPolicy`, `SecurityEventPolicy`, `EmergencyKillSwitchAction` all check it) — like every reused-role case this session, it's real and checked, but provisioned only by hand (`Role::create(['name' => 'platform_admin'])` + `assignRole()`, confirmed via the exact pattern `tests/Feature/Actions/Security/EmergencyKillSwitchActionTest.php` already uses), never by application code. This spec reuses it rather than inventing a parallel mechanism.

## Goal

`approvals:expire-overdue` becomes a real command: overdue `pending` approvals (on both `AgentApproval` and `AgentSkillApproval`) become `expired` — safe, reversible-in-spirit bookkeeping matching semantics the schema already defines, no gate needed. Separately, `model:prune`'s nightly permanent deletion no longer runs unattended: a new detection command proposes each eligible model's purge (using that model's own already-defined `prunable()` retention window, unchanged), and a `platform_admin` reviews and approves (the only place deletion now happens) or rejects (rows kept; re-proposed on a later run if still eligible) on a new governance screen, matching this codebase's existing `ApprovalQueue`/`ProcessApprovalAction` architecture exactly.

## Changes

### 1. `ExpireOverdueApprovals` command

New `app/Console/Commands/ExpireOverdueApprovals.php`, signature `approvals:expire-overdue` (the exact name the schedule already references). Bulk-updates `AgentApproval::where('status', 'pending')->where('expires_at', '<', now())` and the equivalent `AgentSkillApproval` query to `status: 'expired'`. No side effect beyond the status change — no task/deployment state is touched, no notification, matching the audit's own framing of this as pure bookkeeping.

### 2. `retention_purge_proposals` table + `DetectRetentionPurgeCandidates`

New migration, `retention_purge_proposals`: `id`, `model_class` (string), `eligible_count` (unsigned int), `retention_summary` (text — a human-readable description of the model's own retention window, e.g. "AuditLog rows older than 730 days"), `status` (string, default `pending`), `reviewed_by` (nullable FK `users.id`, `nullOnDelete`), `reviewed_at` (nullable timestamp), `reviewer_notes` (nullable text), `deleted_count` (nullable unsigned int, set only on approval — records how many rows the eventual `prune()` call actually removed).

New `app/Console/Commands/DetectRetentionPurgeCandidates.php`, signature `retention:detect-purge-candidates`. For each of `AuditLog`, `AgentTask`, `AgentSession`, `AgentSkillExecution`, `PlatformMegaScorecard` (the five that implement `MassPrunable`/`Prunable` today — `AgentMessage` is skipped defensively via a `method_exists($instance, 'prunable')` check, not silently assumed): calls that model's own `prunable()` query's `count()`; if `0`, does nothing; if `> 0` and no `pending` `RetentionPurgeProposal` already exists for that `model_class`, creates one with the current count and a summary describing the model's retention window.

### 3. `RetentionPurgeProposalPolicy` + `ProcessRetentionPurgeAction`

New `app/Policies/RetentionPurgeProposalPolicy.php::review(User $user): bool { return $user->hasAnyRole(['platform_admin']); }` — reusing the real, existing role, matching `AuditLogPolicy`'s exact check. `AuditLog` itself being one of the five candidate models makes this the natural authority: the same role already gated from *reading* audit logs is the one that decides whether they get purged.

New `app/Actions/Governance/ProcessRetentionPurgeAction.php`, mirroring `ProcessApprovalAction`'s shape: `Gate::authorize('review', $proposal)`; throws `\RuntimeException` if the proposal is no longer `pending` (matching `ProcessApprovalAction`'s own error-signaling convention, not a silent no-op). On `approved`: calls `$proposal->model_class::prune()` — the only place any of these models' rows are actually deleted now — records the real deleted count, logs via `AuditService::logUserAction`, dispatches a new `RetentionPurgeProcessed` event (mirroring `ApprovalProcessed`'s shape). On `rejected`: records `reviewer_notes`, no deletion.

### 4. `RetentionPurgeQueue` Livewire component + route

New `app/Livewire/Governance/RetentionPurgeQueue.php`, mirroring `ApprovalQueue`'s shape: computed `proposals` (paginated, `status = 'pending'`) and `pendingCount`; `approve(int $id)`/`reject(int $id)` re-fetch the proposal, `abort_unless(auth()->user()->can('review', $proposal), 403)` (the same attacker-controlled-ID defense `ApprovalQueue::selectApproval` already uses), then delegate to `ProcessRetentionPurgeAction`. New route under the existing `governance/` prefix in `routes/web.php`, matching `governance/approvals`'s convention. New Blade view matching `resources/views/livewire/governance/approval-queue.blade.php`'s structure.

### 5. `routes/console.php`

The direct `Schedule::command('model:prune', [...])` entry is removed, replaced by `Schedule::command('retention:detect-purge-candidates')->dailyAt('02:00')->onOneServer()`. The existing `Schedule::command('approvals:expire-overdue')->everyThirtyMinutes()->withoutOverlapping()->onOneServer()` entry is unchanged — it already references the right command name, it just now resolves to a real class.

## Testing

- `ExpireOverdueApprovals`: an overdue `pending` `AgentApproval` becomes `expired`; one still within its window is untouched; one already `approved`/`rejected` is untouched. Same three cases for `AgentSkillApproval`.
- `DetectRetentionPurgeCandidates`: a model with rows past its own `prunable()` cutoff gets exactly one `pending` proposal with the correct `eligible_count`; a model with nothing eligible gets none; running the command twice doesn't duplicate a pending proposal for the same model; `AgentMessage` (no `MassPrunable`) never produces a proposal and the command doesn't error on it.
- `ProcessRetentionPurgeAction`/`RetentionPurgeQueue`: a `platform_admin` can approve a proposal (the target model's eligible rows are actually gone from the database, `deleted_count` recorded, `RetentionPurgeProcessed` dispatched, `AuditService` logged) and reject one (rows still present, `reviewer_notes` recorded); a regular org `admin`/`manager` (real role, wrong scope for this platform-wide decision) is blocked with a 403; acting on an already-resolved proposal throws, matching `ProcessApprovalAction`'s existing error convention; after a rejection, a later `retention:detect-purge-candidates` run (model still past its retention window) produces a fresh `pending` proposal for that model.

## Explicitly out of scope

- Fixing `AgentMessage`'s missing `MassPrunable` implementation, or inventing a retention window for it — a separate, real gap noted for a future pass, not silently patched as part of this spec (would mean deciding a retention policy nobody has specified).
- Changing any individual model's own `prunable()` retention window (`AuditLog`'s 730 days, `AgentTask`'s 90 days, etc.) — those are unchanged; this spec only gates *when* the already-defined eligible rows actually get deleted, not *which* rows are eligible.
- Any change to the existing `AgentApproval`/`AgentSkillApproval` governance flow itself (`ProcessApprovalAction`, `ApprovalQueue`, the DIS auto-remediation, the scorecard/DWCA jobs) — all confirmed already-working Level 1/Level 2 processes, untouched.
- Provisioning the `platform_admin` role via application code (a seeder, an admin UI) — per this session's established convention for every reused-role case, granted only by hand, matching how this repo's own tests already provision it.
- A UI for adjusting retention windows or the detection cadence at runtime — both stay code-level, matching the existing `config('audit.retention_days')`/schedule-declaration convention.
