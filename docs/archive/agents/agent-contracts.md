# Agent Contracts Documentation

## Overview

Every AI agent deployed on Dot.Agents must conform to the **Agent Contract Interface** — a set of structural and behavioral requirements that ensure interoperability, governance integration, and plug-and-play deployment.

---

## Agent Contract Interface

### Required Capabilities

Every agent registered in the marketplace MUST declare:

```php
[
  'capabilities' => [string],   // What the agent can do
  'tools' => [string],          // External tools it can invoke
  'input_schema' => array,      // Expected input structure
  'output_schema' => array,     // Guaranteed output structure
  'deployment_modes' => [string], // Supported operation modes
  'max_context_tokens' => int,  // Maximum context window size
  'avg_latency_ms' => int,      // Expected response time
  'pricing_model' => string,    // per_task | subscription | usage_based
]
```

### Required Governance Hooks

Every agent execution MUST:
1. **Log every decision** via `CreateDecisionLogAction`
2. **Score every output** via `DelusionDetectionService::analyze()`
3. **Moderate every response** via `OutputModerationService::scan()`
4. **Route through approval** if `confidence < threshold` or `risk > 60`
5. **Record to audit trail** via `AuditService::logAgentAction()`

### Deployment Modes

| Mode | Description | Human Oversight |
|------|-------------|----------------|
| `advisory` | Agent recommends, human decides | Required for all actions |
| `semi-autonomous` | Agent acts, human notified | Required for high-risk actions |
| `autonomous` | Agent acts independently | Exception-based |
| `executive_approval` | All actions require C-level approval | Required for all actions |

---

## Platform Agents

### Revenue Analytics Agent
**Purpose:** Analyze financial data and surface revenue insights.

| Property | Value |
|----------|-------|
| Category | Analytics |
| Department | Finance |
| Default Mode | advisory |
| Confidence Threshold | 75% |
| Key Capabilities | Financial modeling, trend analysis, anomaly detection |
| Tools | `database_query`, `chart_generator`, `report_exporter` |

**Input Schema:**
```json
{
  "query": "string — the analytical question",
  "date_range": "object — { start, end }",
  "data_sources": "array — table/dataset identifiers",
  "output_format": "string — summary | detailed | chart"
}
```

**Output Schema:**
```json
{
  "summary": "string — executive summary",
  "confidence": "float — 0-100",
  "findings": "array — key findings",
  "data_points": "array — supporting evidence",
  "visualization": "object nullable — chart spec",
  "recommendations": "array — action items"
}
```

---

### Governance Compliance Agent
**Purpose:** Monitor platform activity for policy violations and compliance gaps.

| Property | Value |
|----------|-------|
| Category | Governance |
| Department | Legal/Compliance |
| Default Mode | semi-autonomous |
| Confidence Threshold | 85% |
| Key Capabilities | Policy checking, audit analysis, risk scoring |
| Tools | `audit_log_reader`, `policy_checker`, `risk_scorer` |

---

### Customer Support Agent
**Purpose:** Handle customer inquiries with organizational knowledge base.

| Property | Value |
|----------|-------|
| Category | Customer Success |
| Department | Support |
| Default Mode | semi-autonomous |
| Confidence Threshold | 70% |
| Key Capabilities | Knowledge retrieval, response generation, ticket routing |
| Tools | `knowledge_base_search`, `ticket_creator`, `email_sender` |

---

## Delusion Risk Score Reference

The `DelusionDetectionService` produces a risk score (0-100) for every AI output:

| Score | Risk Level | Action |
|-------|-----------|--------|
| 0-30 | Low | Auto-approve |
| 31-59 | Medium | Flag for review if `requires_human_approval=true` |
| 60-79 | High | Always require human review |
| 80-100 | Critical | Block output, escalate immediately |

**Risk factors that increase the score:**
- Hedging language ("I think", "maybe", "perhaps")
- No evidence provided
- Contradicts provided context
- Hallucination indicators ("As an AI, I should note...")
- Low confidence score from the model itself
- High assumption count

---

## Tool Permission Matrix

Default tool access by deployment mode:

| Tool | advisory | semi-autonomous | autonomous | executive_approval |
|------|----------|-----------------|------------|-------------------|
| `read_data` | ✅ | ✅ | ✅ | ✅ |
| `write_data` | ❌ | ✅ w/audit | ✅ | ✅ w/approval |
| `send_email` | ❌ | ✅ w/audit | ✅ | ✅ w/approval |
| `delete_data` | ❌ | ❌ | ✅ w/audit | ✅ w/approval |
| `call_external_api` | ❌ | ✅ w/audit | ✅ | ✅ w/approval |
| `execute_code` | ❌ | ❌ | ✅ w/audit | ✅ w/approval |

Permissions can be overridden per-deployment via `agent_tool_permissions` with deny-wins logic.
