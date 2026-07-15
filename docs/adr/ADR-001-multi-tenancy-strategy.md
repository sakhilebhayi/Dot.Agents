# ADR-001: Multi-Tenancy via Jetstream Teams + organization_id Column Scoping

**Status:** Accepted  
**Date:** 2026-06-19  
**Authors:** Platform Engineering

---

## Context

Dot.Agents is an enterprise SaaS platform where multiple independent companies (Organizations) share a single database and application instance. Each Organization must be completely isolated from all other Organizations' data.

We needed a multi-tenancy strategy that:
- Integrates with Laravel's authentication system
- Supports team-based role assignments (admin, operator, viewer)
- Is enforceable at the query level to prevent data leaks
- Scales to thousands of Organizations without schema-per-tenant overhead

## Decision

We use **Jetstream Teams** as the Organization primitive, with an **`organization_id` foreign key column on every tenant-owned model**, enforced by:

1. `OrganizationContextMiddleware` — sets `session('current_organization_id')` on every authenticated request
2. Eloquent GlobalScopes on all tenant-owned models that auto-filter by `organization_id`
3. Architecture guard tests (`tests/Architecture/`) that enforce the column exists on all relevant models
4. Every Action class calls `Gate::authorize()` which validates the actor belongs to the target Organization

**Platform-level catalog models** (`Agent`, `AgentVersion`, `AgentSkillPermission`, `SubscriptionPlan`) are intentionally NOT tenant-scoped — they represent the marketplace and are visible to all Organizations.

## Consequences

**Positive:**
- No per-tenant schema management or migrations
- Role assignments use Jetstream's built-in permission system + Spatie permissions layered on top
- Adding a new tenant is a single database row, not a schema operation
- Easy to query across all tenants for platform analytics (with explicit scope removal)

**Negative:**
- Every query against tenant resources must include the GlobalScope or an explicit `where('organization_id', $id)`
- Developer discipline required — forgetting the scope on a new model creates a potential data leak
- Shared database limits isolation guarantees for high-compliance customers (SOC 2 Type II may require row-level security or separate DBs)

**Mitigation:**
- Architecture tests enforce `organization_id` presence on tenant models
- Security tests verify cross-tenant queries return empty results
