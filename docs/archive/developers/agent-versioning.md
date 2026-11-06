# Agent Versioning Guide

## Overview

Dot.Agents supports versioned AI agent deployments, allowing you to roll out new agent configurations without downtime, run A/B experiments, and roll back to a stable version in seconds.

---

## Versioning Model

Each **AgentDeployment** has:

| Field | Type | Description |
|-------|------|-------------|
| `version` | string | Semantic version tag (e.g. `1.4.2`) |
| `config` | JSON | Agent configuration snapshot for this version |
| `deployment_mode` | enum | `advisory` / `semi_autonomous` / `autonomous` / `executive_approval` |
| `confidence_threshold` | float | Minimum AI confidence before escalation |
| `status` | enum | `active` / `inactive` / `decommissioned` |
| `deployed_by` | FK | User who created this deployment |
| `deployed_at` | timestamp | When this version became active |

---

## Deploying a New Version

### Via the Dashboard

1. Open the agent's deployment card
2. Click **New Version**
3. Adjust configuration (instructions, thresholds, mode)
4. Click **Deploy** — the new version becomes active; the previous version transitions to `inactive`

### Via API

```bash
POST /api/v1/agents/{agent_id}/deployments
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Revenue Analyst v2",
  "version": "2.0.0",
  "deployment_mode": "semi_autonomous",
  "confidence_threshold": 80.0,
  "custom_instructions": "Focus on APAC revenue data only.",
  "organization_id": 42
}
```

---

## Rolling Back

A rollback activates a previous `inactive` deployment and decommissions the current `active` one.

```bash
POST /api/v1/agents/{agent_id}/deployments/{deployment_id}/activate
Authorization: Bearer {token}
```

All tasks in-flight on the current deployment are allowed to complete before it is decommissioned (graceful drain, default 5 minutes).

---

## Version History

```bash
GET /api/v1/agents/{agent_id}/deployments
Authorization: Bearer {token}
```

Returns all deployments for the agent ordered by `deployed_at` descending, including:

- Version tag
- Status
- Performance scorecard summary (accuracy, confidence, delusion risk)
- Deployed by (user)
- Deployed at timestamp

---

## Canary / A/B Deployments

Run two versions simultaneously with traffic splitting:

1. Deploy the new version — it starts `inactive`
2. In deployment settings enable **Canary Mode** and set the traffic percentage (e.g. 10%)
3. Monitor the scorecard for both versions in the dashboard
4. Promote the canary to 100% or roll it back from the **Traffic Split** panel

---

## Confidence Thresholds and Deployment Modes

| Mode | Behaviour |
|------|-----------|
| `advisory` | Agent produces output; human always takes action |
| `semi_autonomous` | Agent acts when confidence ≥ threshold; otherwise escalates |
| `autonomous` | Agent acts without human review |
| `executive_approval` | Agent acts but all decisions require C-level approval |

Lower the threshold to escalate more decisions to humans. Recommended starting values:

| Use Case | Suggested Threshold |
|----------|---------------------|
| Financial decisions | 90% |
| Customer communications | 85% |
| Internal analytics | 75% |
| Low-risk data processing | 65% |

---

## Decommissioning

```bash
DELETE /api/v1/agents/{agent_id}/deployments/{deployment_id}
Authorization: Bearer {token}
```

Decommissioning is **irreversible**. All associated tasks are archived. The deployment's scorecard history is retained for auditing.

---

## Audit Trail

Every deployment, rollback, and decommission is recorded in the `audit_logs` table with event type `agent.deployed` / `agent.decommissioned`. Access the full trail from the dashboard under **Agent → History**.
