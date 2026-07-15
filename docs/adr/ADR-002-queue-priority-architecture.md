# ADR-002: Queue Priority Architecture with Laravel Horizon

**Status:** Accepted  
**Date:** 2026-06-19  
**Authors:** Platform Engineering

---

## Context

Dot.Agents processes many types of background work with very different SLA requirements:

- Security events and kill-switch activations must be processed in milliseconds
- AI agent tasks may take seconds to minutes
- Billing webhook processing must be reliable but not necessarily fast
- Email notifications are best-effort

Using a single queue for all work means a flood of AI tasks could delay a critical security alert from being processed.

## Decision

We implement an **8-tier priority queue** hierarchy using Redis and Laravel Horizon:

```
critical    → DIS alerts, security incidents, kill-switch activations
security    → threat detection, auth anomalies
governance  → audit logging, approval processing, decision logs
agents      → AI task execution (highest concurrency: 20 workers)
billing     → Stripe webhooks, invoice generation
notifications → email, push, in-app
workflows   → graph workflow execution
default     → catch-all
```

Workers poll queues in priority order within each supervisor group. The `critical` and `security` supervisors have minimum 2 processes always running.

A **Dead-Letter Queue (DLQ) supervisor** monitors the `failed` queue. Jobs exhaust their retry budget on their primary queue before the `failed()` hook logs the permanent failure.

## Consequences

**Positive:**
- Security and governance events are never delayed by AI task volume
- Autoscaling per supervisor group (agents scale to 20, billing to 4)
- Independent failure isolation — a broken AI service doesn't block billing

**Negative:**
- Queue configuration complexity — 8 queues must be monitored
- Developer must specify the correct queue when dispatching; defaulting to `default` will still work but loses priority guarantees
- Local development uses a simplified single-supervisor config

**Decision Rule:**
> All new Jobs MUST declare `public string $queue` matching one of the 8 named queues, OR inherit from a domain base Job that sets the queue. Never rely on the `default` queue for production work.
