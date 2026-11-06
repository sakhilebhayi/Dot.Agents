# Agent Deployment Guide

## Overview

This guide covers how to deploy, configure, and manage AI agents as digital workforce members on the Dot.Agents platform. Each deployment is an instance of an Agent bound to an Organization, governed by the `AgentDeployment` model and managed through the `DeployAgentAction`.

---

## Deployment Modes

Every deployment operates in one of four modes that control agent autonomy:

| Mode | Description | Approval Required | Use When |
|------|-------------|------------------|----------|
| `advisory` | Agent provides recommendations only; all actions require human sign-off | Always | Onboarding, high-risk domains |
| `semi_autonomous` | Agent executes low-risk tasks independently; flags high-risk tasks for approval | For high-risk tasks | Production with oversight |
| `autonomous` | Agent executes all tasks independently | Never | Trusted, well-scored agents |
| `executive_approval` | Agent executes but critical decisions escalate to exec-level reviewers | For executive decisions | C-suite workflow automation |

> **Default mode**: `advisory` — the safest starting point for new deployments.

---

## Step-by-Step Deployment

### 1. Prerequisites

- Organization must have an active subscription (`OrganizationSubscription.status = active`)
- User must hold the `deploy-agents` permission (via Spatie roles)
- Agent must be published in the marketplace (`Agent.status = active`)

### 2. Deploy via UI

1. Navigate to **My Agents → Deploy New Agent**
2. Select an Agent from the marketplace
3. Configure the deployment:
   - **Name**: Human-readable label for this deployment
   - **Mode**: Select autonomy level (see table above)
   - **Confidence Threshold**: Minimum AI confidence (0–100) below which human approval is required (default: 75)
   - **Custom Instructions**: Optional system-prompt override
4. Click **Deploy** — this calls `DeployAgentAction::execute(DeployAgentData $data)`

### 3. Deploy via API

```http
POST /api/v1/deployments
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{
  "agent_id": 42,
  "name": "Marketing Copy Assistant",
  "deployment_mode": "semi_autonomous",
  "confidence_threshold": 80,
  "custom_instructions": "Always write in British English."
}
```

**Response** (`201 Created`):
```json
{
  "id": 107,
  "uuid": "a1b2c3d4-...",
  "name": "Marketing Copy Assistant",
  "status": "active",
  "deployment_mode": "semi_autonomous",
  "confidence_threshold": 80,
  "created_at": "2026-06-13T10:00:00Z"
}
```

---

## Lifecycle States

```
pending → active → paused → decommissioned
              ↕
           suspended  (set by Digital Immune System on drift detection)
```

| Status | Meaning | Resume |
|--------|---------|--------|
| `pending` | Deployment created, not yet active | Automatic |
| `active` | Agent is running and accepting tasks | — |
| `paused` | Manually paused by org admin | Resume via UI or PATCH endpoint |
| `suspended` | Auto-suspended by governance (DIS) | Requires admin review + resume |
| `decommissioned` | Permanently retired | No resume — create new deployment |

### Pause / Resume

```http
POST /api/v1/deployments/{id}/pause
POST /api/v1/deployments/{id}/resume
```

### Decommission

```http
DELETE /api/v1/deployments/{id}
```

> Decommissioning is irreversible. All associated `AgentTask` records are retained for audit history.

---

## Confidence Threshold Explained

When an agent's confidence score for a decision falls below the configured threshold, the platform:

1. Creates an `ApprovalRequest` record
2. Notifies org admins via `SendPlatformNotification` job
3. Blocks task execution until a human approves or rejects

**Recommended thresholds by use case:**

| Use Case | Threshold |
|----------|-----------|
| Content drafting | 60 |
| Data analysis | 75 (default) |
| Customer communication | 85 |
| Financial decisions | 95 |
| Legal/compliance | 95 |

---

## Assigning Skills

Skills extend what an agent can do. Assign skills after deployment:

```http
POST /api/v1/deployments/{id}/skills
{
  "skill_id": 12,
  "config": {}
}
```

Toggle a skill on/off without removing it:

```http
PATCH /api/v1/deployments/{id}/skills/{skill_id}
{
  "is_active": false
}
```

---

## Monitoring a Deployment

- **Scorecard**: `GET /api/v1/deployments/{id}/skill-scores` — 10-dimension health score
- **Audit log**: All agent actions are recorded in `audit_logs` with `auditable_type = AgentDeployment`
- **Decision log**: Each AI decision is stored in `decision_logs` with `delusion_risk_score`
- **Dashboard**: Navigate to **My Agents → {Deployment} → Scorecard**

---

## Emergency Kill Switch

If an agent behaves unexpectedly, any platform admin can trigger an emergency stop:

```php
app(EmergencyKillSwitchAction::class)->execute(
    scope: 'deployment',   // 'deployment' | 'organization' | 'platform'
    id: $deploymentId,
    reason: 'Unexpected output detected in production',
);
```

This immediately sets `status = suspended` and aborts all in-flight tasks.

---

## Related

- [Agent Contracts](agent-contracts.md)
- [Capability Matrix](capability-matrix.md)
- [Governance Specification](governance-spec.md)
- API Reference: `docs/openapi.yaml`
