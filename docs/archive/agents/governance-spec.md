# Agent Governance Specification

## Overview

Every AI agent deployed on Dot.Agents operates inside a multi-layered governance framework. This document describes the governance architecture, the enforcement mechanisms, and the responsibilities of each component.

---

## Governance Pillars

| Pillar | Component | Purpose |
|--------|-----------|---------|
| **Audit** | `AuditService` + `AuditLog` | Immutable record of every agent action |
| **Delusion Detection** | `DelusionDetectionService` | Scores AI output for hallucination/confabulation risk |
| **Approval Workflow** | `ApprovalRequest` + `ApprovalProcessed` event | Human-in-the-loop for low-confidence decisions |
| **Digital Immune System** | `DigitalImmuneSystem` (DIS) | Automated drift and threat detection |
| **Scorecard** | `ScorecardService` | 10-dimension health score per agent per period |
| **Prompt Injection Guard** | `AuditService::detectPromptInjection()` | Blocks adversarial input before it reaches the LLM |

---

## 1. Audit Trail

Every significant platform event is recorded in `audit_logs`:

```
audit_logs
├── uuid            — globally unique event identifier
├── organization_id — tenant isolation key
├── user_id         — who triggered the event (null for agent-originated)
├── auditable_type  — model class (AgentDeployment, AgentTask, etc.)
├── auditable_id    — model primary key
├── event           — action name (agent_deployed, task_completed, etc.)
├── event_category  — user_action | agent_action | system_event | security_event
├── description     — human-readable summary
├── old_values      — state before the change (JSON)
├── new_values      — state after the change (JSON)
├── ip_address      — source IP
├── user_agent      — client identifier
├── session_id      — session key for correlation
└── risk_level      — low | medium | high | critical
```

**Key service methods:**

```php
// Log a user-initiated action
$auditService->logUserAction(
    event: 'agent_deployed',
    description: 'Deployed Marketing Assistant in semi-autonomous mode',
    subject: $deployment,
    data: ['mode' => 'semi_autonomous', 'threshold' => 80],
);

// Log a security event
$auditService->logSecurityEvent(
    organizationId: $orgId,
    eventType: 'prompt_injection',
    severity: 'high',
    title: 'Prompt injection attempt blocked',
    description: 'User input matched injection pattern',
    data: ['pattern' => 'ignore previous instructions'],
);
```

> All audit logs are **append-only** at the application layer. No update or delete routes exist for `AuditLog` records.

---

## 2. Delusion Risk Scoring

The `DelusionDetectionService` assigns a `delusion_risk_score` (0–100) to every AI decision stored in `decision_logs`.

**Score interpretation:**

| Score | Risk Level | Platform Action |
|-------|-----------|----------------|
| 0–25 | Low | None — proceed normally |
| 26–50 | Medium | Log warning, monitor |
| 51–75 | High | Trigger human approval request |
| 76–100 | Critical | Block task, alert admins, flag deployment |

**Factors that increase delusion risk:**

- Response contradicts information in the context window
- Confidence score below deployment threshold
- Output references entities not present in source material
- Response length is anomalously long or short for the task type
- Multiple self-contradiction patterns detected

**Storage:**

```
decision_logs
├── agent_deployment_id
├── agent_task_id
├── decision_type       — task_execution | approval | skill_selection
├── input_summary       — truncated input (no PII)
├── output_summary      — truncated output
├── confidence_score    — 0.0–1.0
├── delusion_risk_score — 0–100
├── reasoning           — chain-of-thought if available
└── approved_by         — user_id of human approver (if applicable)
```

---

## 3. Approval Workflow

When an agent's confidence falls below the configured `confidence_threshold`, the platform creates an `ApprovalRequest` and halts execution until a human acts.

**Flow:**

```
Agent Task Created
       │
       ▼
Confidence Score Calculated
       │
  ┌────┴─────────┐
  │ Below Threshold│
  └────────────────┘
       │
       ▼
ApprovalRequest created (status: pending)
       │
       ▼
Admins notified via SendPlatformNotification job
       │
  ┌────┴───────────┐
  │ Admin Reviews  │
  └────────────────┘
       │
  ┌────┴────┐
  │ Approve │ → Task resumes execution → ApprovalProcessed event fired
  │ Reject  │ → Task cancelled         → ApprovalProcessed event fired
  └─────────┘
```

**Approval via API:**

```http
POST /api/v1/skill-approvals/{id}/approve
POST /api/v1/skill-approvals/{id}/reject
```

**Deployment modes and approval:**

| Mode | When Approval Required |
|------|----------------------|
| `advisory` | Always — every action |
| `semi_autonomous` | When confidence < threshold |
| `autonomous` | Never |
| `executive_approval` | For decisions flagged as executive-level |

---

## 4. Digital Immune System (DIS)

The DIS is a scheduled background service (`DigitalImmuneSystem`) that continuously monitors for drift, anomalies, and threats.

**Triggers:**

- `HandleAgentTaskFailed` listener: after 3+ failures in 1 hour → dispatches `RunDigitalImmuneSystemCheck` job
- Scheduled: runs every 15 minutes via `app/Console/Commands/`
- Manual: `EmergencyKillSwitchAction` can invoke DIS immediately

**What DIS checks:**

| Check | Threshold | Action on Breach |
|-------|-----------|-----------------|
| Task failure rate | > 20% in last hour | Suspend deployment |
| Average latency | > 30s p95 | Alert + log |
| Delusion risk trend | > 60 avg over 10 decisions | Suspend + alert |
| Prompt injection attempts | Any in last 10 min | Log security event |
| Confidence drift | Declining 15+ pts over 24h | Alert admins |
| Memory anomaly | Unauthorized memory writes | Block + audit |

**DIS outcomes:**

```
RunDigitalImmuneSystemCheck Job
         │
         ▼
DWCAAuditService::runFullAudit()
         │
    ┌────┴─────────────────────┐
    │ Threat detected?          │
    │ YES → SecurityEvent::create()  │
    │      → Notify platform admins  │
    │      → Suspend deployment      │
    │ NO  → Record clean health      │
    └──────────────────────────┘
```

---

## 5. Scorecard — 10-Dimension Health Score

The `ScorecardService` generates a 0–100 score per agent per time period across 10 dimensions:

| # | Dimension | What It Measures |
|---|-----------|-----------------|
| 1 | Task Success Rate | % of tasks completed without error |
| 2 | Confidence Accuracy | Correlation between stated and actual confidence |
| 3 | Latency | Average and p95 response time |
| 4 | Delusion Risk | Average delusion risk score over the period |
| 5 | Approval Rate | % of decisions requiring human approval |
| 6 | Skill Utilisation | Breadth of skill usage relative to assigned skills |
| 7 | Memory Quality | Accuracy and relevance of stored memories |
| 8 | Security Posture | Injection attempts + security event frequency |
| 9 | Governance Compliance | Audit log completeness + approval workflow adherence |
| 10 | Business Impact | Task output quality (where measurable) |

**Access scorecard:**

```http
GET /api/v1/deployments/{id}/skill-scores
```

> Scorecards are stored in `platform_mega_scorecards` and also available in the UI under **My Agents → {Deployment} → Scorecard**.

---

## 6. Prompt Injection Guard

All user-supplied text that reaches the AI layer must pass through `AuditService::detectPromptInjection()`.

**Enforcement points:**

| Component | Guard Location |
|-----------|---------------|
| `AgentChat::sendMessage()` | Before storing user message |
| `WorkflowBuilder::save()` | Before saving node configuration |
| `WorkflowBuilder::publish()` | Before publishing workflow |
| `SkillExecutionController` | Before executing skill |
| `SocialEngagement` (inbound) | On inbound message receipt |

**On detection:**

1. Input is blocked — not forwarded to the LLM
2. A `SecurityEvent` is logged with `event_type = prompt_injection` and `severity = high`
3. The user receives an error message: *"Suspicious input detected and logged."*
4. The incident is visible in the Governance → Audit dashboard

---

## 7. Tenant Isolation

Every piece of data owned by an organization is scoped by `organization_id`. Models use the `HasOrganizationScope` trait (or equivalent `addGlobalScope`) to automatically apply this filter to all Eloquent queries.

**Enforcement layers:**

1. **Database**: `organization_id` column on every tenant-owned model
2. **Eloquent**: Global scope applies `WHERE organization_id = ?` automatically
3. **Middleware**: `OrganizationContextMiddleware` sets `session('current_organization_id')`
4. **Actions**: All Actions call `Gate::authorize()` which verifies org ownership
5. **Tests**: `tests/Feature/Security/` suite verifies cross-org data is inaccessible

> **Zero tolerance**: any query that bypasses org scoping is a P0 security incident.

---

## Governance Dashboard

Navigate to **Governance** in the left sidebar to access:

- **Approvals** — pending and processed approval requests
- **Audit Log** — searchable, filterable event history
- **Security Events** — flagged injection attempts, DIS alerts, anomalies

---

## Related

- [Agent Contracts](agent-contracts.md)
- [Deployment Guide](deployment-guide.md)
- [Capability Matrix](capability-matrix.md)
- Architecture docs: `docs/architecture/platform-overview.md`
- Security docs: `docs/security/architecture.md`
