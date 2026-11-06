# Database Schema Documentation

## Overview

Dot.Agents uses MySQL 8+ in production (SQLite for development/testing). All tables are prefixed logically by domain.

**24 migrations** | **40 models** | Multi-tenant (every org-owned table has `organization_id`)

---

## Core Domain Tables

### `organizations` (maps to Jetstream Teams)
The root multi-tenant entity. Every resource belongs to an organization.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | varchar(255) | Organization display name |
| `slug` | varchar(255) unique | URL-friendly identifier |
| `industry` | varchar(100) | Industry sector |
| `size` | varchar(50) | small / medium / large / enterprise |
| `plan` | varchar(50) | starter / professional / enterprise |
| `owner_id` | FK → users | Organization creator |
| `status` | varchar(50) | active / suspended / churned |
| `trial_ends_at` | timestamp nullable | |
| `settings` | json | Organization-level configuration |
| `metadata` | json | Custom metadata |

### `users`
Standard Laravel user model + Jetstream profile fields.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | varchar(255) | |
| `email` | varchar(255) unique | |
| `email_verified_at` | timestamp nullable | |
| `password` | varchar(255) | bcrypt hashed |
| `two_factor_secret` | text nullable | TOTP secret (encrypted) |
| `two_factor_recovery_codes` | text nullable | |
| `profile_photo_path` | varchar nullable | |

### `organization_user` (pivot)
Many-to-many: users ↔ organizations.

| Column | Type | Description |
|--------|------|-------------|
| `organization_id` | FK | |
| `user_id` | FK | |
| `role` | varchar | owner / admin / manager / member / viewer |
| `is_primary` | boolean | User's primary organization |
| `joined_at` | timestamp | |

---

## Agent Domain Tables

### `agents`
The marketplace catalog — available agent types.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | varchar(255) | |
| `slug` | varchar(255) unique | |
| `tagline` | varchar(255) | Short description |
| `description` | text | Full description |
| `category_id` | FK → agent_categories | |
| `agent_department_id` | FK → agent_departments | |
| `status` | varchar | active / beta / deprecated |
| `default_deployment_mode` | varchar | advisory / semi-autonomous / autonomous |
| `capabilities` | json | List of capability strings |
| `tools` | json | Allowed tool names |
| `pricing_model` | varchar | per_task / subscription / usage_based |
| `base_price_usd` | decimal(10,4) | |
| `average_rating` | decimal(3,2) | Computed from reviews |

### `agent_deployments`
An organization's instance of an agent — their "hired" worker.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `organization_id` | FK → organizations | Tenant scope |
| `agent_id` | FK → agents | Which agent type |
| `name` | varchar(255) | Deployment display name |
| `alias` | varchar nullable | Short name |
| `status` | varchar | active / paused / decommissioned / pending / error |
| `deployment_mode` | varchar | advisory / semi-autonomous / autonomous / executive_approval |
| `confidence_threshold` | decimal(5,2) | 0-100; below this → human review required |
| `custom_instructions` | text encrypted nullable | Org-specific prompt additions |
| `requires_human_approval` | boolean | Force approval for all tasks |
| `enable_memory` | boolean | Enable agent memory |
| `model_override` | varchar nullable | Override default AI model |
| `deployed_by` | FK → users | |
| `deployed_at` | timestamp nullable | |
| `decommissioned_at` | timestamp nullable | |

### `agent_tasks`
Individual work items executed by an agent deployment.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `organization_id` | FK | Tenant scope |
| `agent_deployment_id` | FK | |
| `title` | varchar(255) | |
| `description` | text nullable | |
| `status` | varchar | pending / in_progress / awaiting_approval / completed / failed |
| `input_data` | json | Task input payload |
| `output_data` | json nullable | Task output payload |
| `confidence_score` | decimal(5,2) nullable | AI confidence 0-100 |
| `latency_ms` | integer nullable | Execution time |
| `cost` | decimal(10,6) nullable | Provider cost in USD |
| `tokens_used` | integer nullable | |
| `metadata` | json | |
| `completed_at` | timestamp nullable | |

### `agent_sessions`
Chat/conversation sessions with an agent.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `agent_deployment_id` | FK | |
| `organization_id` | FK | Tenant scope |
| `user_id` | FK → users | Session owner |
| `title` | varchar(255) | |
| `status` | varchar | active / completed / archived |
| `session_type` | varchar | conversation / task / analysis |
| `started_at` | timestamp | |
| `ended_at` | timestamp nullable | |

### `agent_messages`
Individual messages within a session.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `session_id` | FK → agent_sessions | |
| `role` | varchar | user / assistant / system |
| `content` | text | Message content |
| `tokens` | integer nullable | |
| `metadata` | json | |

---

## Governance Domain Tables

### `agent_approvals`
Human-in-the-loop approval requests.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `organization_id` | FK | Tenant scope |
| `agent_deployment_id` | FK | |
| `task_id` | FK → agent_tasks nullable | |
| `status` | varchar | pending / approved / rejected / escalated / expired |
| `approval_type` | varchar | task_execution / configuration_change / high_risk |
| `risk_level` | varchar | low / medium / high / critical |
| `decision_summary` | text | What requires approval |
| `requested_from_id` | FK → users | Who is the default reviewer |
| `reviewed_by` | FK → users nullable | Who reviewed |
| `reviewer_notes` | text nullable | |
| `reviewed_at` | timestamp nullable | |
| `expires_at` | timestamp | Auto-expires if not reviewed |

### `decision_logs`
Immutable record of every AI decision output.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `uuid` | varchar(36) unique | Externally referenceable |
| `organization_id` | FK | Tenant scope |
| `agent_deployment_id` | FK | |
| `task_id` | FK nullable | |
| `decision_type` | varchar | task_output / recommendation / classification |
| `title` | varchar(255) | |
| `decision_summary` | text | What the AI decided |
| `confidence_score` | decimal(5,2) | AI confidence 0-100 |
| `delusion_risk_score` | decimal(5,2) | Hallucination risk 0-100 |
| `requires_human_review` | boolean | Below threshold or high risk |
| `human_reviewed` | boolean | Has a human reviewed this? |
| `compliance_passed` | boolean | Passed compliance checks |

### `audit_logs`
Immutable platform-wide audit trail.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `uuid` | varchar(36) unique | |
| `organization_id` | FK | Tenant scope |
| `user_id` | FK nullable | Acting user |
| `agent_deployment_id` | FK nullable | Acting agent |
| `event` | varchar(255) | Event name (e.g. `deployment.paused`) |
| `event_category` | varchar | user_action / agent_action / security / system |
| `description` | text | Human-readable description |
| `old_values` | json nullable | Before state |
| `new_values` | json nullable | After state |
| `ip_address` | varchar(45) nullable | |
| `risk_level` | varchar | low / medium / high / critical |

### `security_events`
Security-specific incidents requiring investigation.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `organization_id` | FK | Tenant scope |
| `type` | varchar | prompt_injection / unauthorized_access / data_exfiltration |
| `severity` | varchar | low / medium / high / critical |
| `title` | varchar(255) | |
| `description` | text | |
| `details` | json | Raw event data |
| `resolved_at` | timestamp nullable | |

---

## Agent Tool Permissions Table

### `agent_tool_permissions`
Deny-wins permission system for agent tool access.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `organization_id` | FK | Tenant scope |
| `agent_deployment_id` | FK nullable | null = org-wide rule |
| `tool_name` | varchar(100) | Tool identifier |
| `permission` | varchar | allow / deny |
| `role_scope` | varchar nullable | Apply only to this role |
| `conditions` | json nullable | Conditional logic |
| `is_active` | boolean | |
| `reason` | text nullable | Why this rule exists |
| `created_by` | FK → users | |

**Indexes:** `[organization_id, tool_name]`, `[agent_deployment_id, tool_name]`

---

## Scoring & Analytics Tables

### `agent_scorecards`
10-dimension health scores per agent per time period.

| Column | Type | Dimension Measured |
|--------|------|--------------------|
| `accuracy_score` | decimal(5,2) | Output correctness |
| `reliability_score` | decimal(5,2) | Task completion rate |
| `efficiency_score` | decimal(5,2) | Speed + cost |
| `compliance_score` | decimal(5,2) | Policy adherence |
| `safety_score` | decimal(5,2) | Risk/harm avoidance |
| `transparency_score` | decimal(5,2) | Explainability |
| `collaboration_score` | decimal(5,2) | Multi-agent coordination |
| `adaptability_score` | decimal(5,2) | Learning + improvement |
| `cost_efficiency_score` | decimal(5,2) | ROI |
| `user_satisfaction_score` | decimal(5,2) | Human feedback |
| `overall_score` | decimal(5,2) | Weighted average |

---

## ERD Summary

```
organizations
    ├── users (via organization_user pivot)
    ├── agent_deployments
    │     ├── agent_tasks
    │     │     └── agent_approvals
    │     ├── agent_sessions
    │     │     └── agent_messages
    │     ├── agent_scorecards
    │     ├── decision_logs
    │     └── agent_tool_permissions
    ├── audit_logs
    └── security_events
```
