# ADR-004: Event-Driven Architecture with Explicit EventServiceProvider Registration

**Status:** Accepted  
**Date:** 2026-06-19  
**Authors:** Platform Engineering

---

## Context

Every significant state change on the platform (agent deployed, task completed, approval processed, security threat detected) must:

1. Be recorded in the audit log
2. Trigger downstream side effects (scorecards, notifications, governance hooks)
3. Be traceable via `php artisan event:list`

Laravel supports two approaches:
- **Auto-discovery**: scans all Listener classes for `handle()` methods and registers automatically
- **Explicit registration**: all bindings declared in `EventServiceProvider::$listen`

## Decision

We use **explicit registration only** (`shouldDiscoverEvents()` returns `false` in all EventServiceProvider classes).

The `EventServiceProvider` has been split into domain-specific providers for maintainability:

| Provider | Domain |
|----------|--------|
| `EventServiceProvider` | Socialite drivers, social/SCCS events, org lifecycle |
| `AgentEventServiceProvider` | Agents, skills, workflows, approvals |
| `GovernanceEventServiceProvider` | Security, compliance, org settings, governance |
| `BillingEventServiceProvider` | Subscriptions, checkout, billing credentials |

All four providers are registered in `bootstrap/providers.php`. Laravel merges their `$listen` arrays automatically.

**Rule**: Every new Event/Listener pair MUST be added to the appropriate domain provider before merging. The architecture guard test `every_event_has_a_listener` enforces this at CI time.

## Consequences

**Positive:**
- `php artisan event:list` documents all 57 bindings with zero ambiguity
- Auto-discovery in production environments (where classes may not be pre-loaded) is unreliable — explicit registration guarantees correctness
- Domain provider split reduces the file from 397 → ~103 lines, eliminating merge conflicts

**Negative:**
- Every new event/listener pair requires a manual provider update (2 lines)
- Domain provider boundaries require judgment calls for cross-cutting events

**Convention:**
> New events that span domains (e.g., `ApprovalRequested` affects both agents and governance) belong in `AgentEventServiceProvider` since agents initiate approvals. Governance listeners can be added to agent events without moving them.
