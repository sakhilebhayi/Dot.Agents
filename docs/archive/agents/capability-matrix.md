# Agent Capability Matrix

## Overview

Dot.Agents ships 35 built-in skills across four layers. Each skill is a self-contained class extending `BaseSkill` that declares its key, layer, version, and supported actions. Skills are assigned to agent deployments and executed via the Skill Execution API.

---

## Skill Layers

| Layer | Purpose | Skills |
|-------|---------|--------|
| `core` | Context management, memory, reasoning | 4 |
| `platform` | Domain-specific business tasks | 20 |
| `governance` | Audit, risk, compliance, confidence scoring | 4 |
| `meta` | Self-improvement, introspection, orchestration | 5 |
| `workforce` | Multi-agent collaboration and task delegation | 4 |

---

## Core Layer — Context & Memory

| Skill Key | Class | Actions | Description |
|-----------|-------|---------|-------------|
| `context-engineering` | `ContextEngineeringSkill` | `build_context`, `merge_contexts`, `prioritise` | Builds and merges structured context windows |
| `context-memory` | `ContextMemorySkill` | `prioritize`, `inject`, `store` | Manages agent memory: store, retrieve, inject into context |
| `context-optimization` | `ContextOptimizationSkill` | `optimize`, `compress` | Trims context to token budget while preserving relevance |
| `memory-management` | `MemoryManagementSkill` | `recall`, `forget`, `summarise` | Long-term memory lifecycle management |

---

## Platform Layer — Business Tasks

| Skill Key | Class | Actions | Description |
|-----------|-------|---------|-------------|
| `audience-intelligence` | `AudienceIntelligenceSkill` | `segment`, `profile`, `measure_roi` | Audience segmentation, persona building, ROI measurement |
| `campaign-intelligence` | `CampaignIntelligenceSkill` | `analyze_campaign`, `content_brief`, `optimise` | Campaign analysis, content briefs, performance optimization |
| `content-batch` | `ContentBatchSkill` | `generate_batch`, `build_template` | Template-driven bulk content generation |
| `content-quality` | `ContentQualitySkill` | `validate_quality`, `distribute` | Quality scoring and distribution manifest creation |
| `excel-analysis` | `ExcelAnalysisSkill` | `analyse`, `detect_anomalies`, `summarise` | Tabular data analysis, anomaly detection |
| `excel-data-processing` | `ExcelDataProcessingSkill` | `parse`, `clean`, `transform` | Parse, clean, and reshape spreadsheet data |
| `excel-export` | `ExcelExportSkill` | `generate_schema`, `export` | Schema generation and structured data export (CSV/JSON/Markdown) |
| `marketing-intelligence` | `MarketingIntelligenceSkill` | `analyse_market`, `competitor_analysis`, `trend_forecast` | Market analysis, competitor intelligence, trend forecasting |
| `mass-content-generation` | `MassContentGenerationSkill` | `generate`, `personalise`, `repurpose` | Large-scale content production and personalisation |
| `seo-analyser` | `SeoAnalyserSkill` | `analyse_url`, `keyword_gap`, `serp_analysis` | On-page SEO analysis, keyword gap, SERP research |
| `seo-audit` | `SeoAuditSkill` | `full_audit`, `technical_audit`, `content_audit` | Comprehensive SEO auditing (technical + content) |
| `seo-optimization` | `SeoOptimizationSkill` | `optimise_content`, `meta_tags`, `schema_markup` | Content optimization, meta tag generation, schema markup |
| `video-production` | `VideoProductionSkill` | `produce`, `edit_plan`, `storyboard` | Video production planning, editing guides, storyboards |
| `video-script-writer` | `VideoScriptWriterSkill` | `write_script`, `hooks`, `cta` | Video script writing, hook generation, call-to-action |
| `video-scripting` | `VideoScriptingSkill` | `script`, `adapt`, `localise` | Multi-format video scripting and localisation |
| `workflow-optimization` | `WorkflowOptimizationSkill` | `analyse_workflow`, `optimise`, `automate` | Workflow efficiency analysis and automation recommendations |

---

## Governance Layer — Safety & Compliance

| Skill Key | Class | Actions | Description |
|-----------|-------|---------|-------------|
| `audit-logging` | `AuditLoggingSkill` | `log_action`, `retrieve_logs`, `export` | Structured audit trail creation and retrieval |
| `confidence-scoring` | `ConfidenceScoringSkill` | `score`, `calibrate`, `explain` | Confidence scoring for agent decisions |
| `risk-assessment` | `RiskAssessmentSkill` | `assess`, `classify`, `recommend` | Task-level risk classification and mitigation |
| `self-verification` | `SelfVerificationSkill` | `verify_output`, `fact_check`, `cross_check` | Agent output self-verification and fact-checking |

---

## Meta Layer — Self-Improvement & Orchestration

| Skill Key | Class | Actions | Description |
|-----------|-------|---------|-------------|
| `agent-auditor` | `AgentAuditorSkill` | `audit`, `scorecard`, `compliance_check` | Agent behaviour auditing and compliance scoring |
| `agent-evaluator` | `AgentEvaluatorSkill` | `evaluate`, `benchmark`, `compare` | Agent performance benchmarking and comparison |
| `skill-combinator` | `SkillCombinatorSkill` | `combine`, `augment` | Combines multiple skills into a single execution pipeline |
| `skill-introspection` | `SkillIntrospectionSkill` | `introspect`, `extend` | Inspects agent skill set and suggests capability extensions |
| `superpowers` | `SuperpowersSkill` | `activate`, `chain`, `amplify` | Activates advanced capability chains for complex tasks |

---

## Workforce Layer — Multi-Agent Collaboration

| Skill Key | Class | Actions | Description |
|-----------|-------|---------|-------------|
| `collaboration` | `CollaborationSkill` | `coordinate`, `share_context`, `sync` | Cross-agent context sharing and coordination |
| `delegation` | `DelegationSkill` | `delegate`, `monitor`, `escalate` | Task delegation to sub-agents with monitoring |
| `task-decomposition` | `TaskDecompositionSkill` | `decompose`, `prioritise`, `sequence` | Breaks complex tasks into ordered sub-tasks |
| `workforce-orchestration` | `WorkforceOrchestrationSkill` | `orchestrate`, `balance_load`, `report` | Multi-agent workforce orchestration and load balancing |

---

## Skill Execution

Skills are executed via the Deployment Skill API:

```http
POST /api/v1/deployments/{id}/skills/{skill_id}/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "action": "generate_batch",
  "template": "Introducing {product} — the future of {industry}.",
  "variables": [
    { "product": "Dot.Agents", "industry": "AI workforce" }
  ]
}
```

**Rate limit**: 10 executions/minute per user, 100/minute per organization.

**Response**:
```json
{
  "status": "completed",
  "confidence": 0.92,
  "output": {
    "generated_count": 1,
    "items": ["Introducing Dot.Agents — the future of AI workforce."]
  },
  "latency_ms": 340
}
```

---

## Adding a Custom Skill

1. Extend `BaseSkill` in `app/Skills/{Layer}/YourSkill.php`
2. Implement `getKey()`, `getLayer()`, `getVersion()`, `getSupportedActions()`, and `execute()`
3. Register in `SkillRegistryBootstrapper::register()`
4. Create an `AgentSkill` database record and seed it
5. Write a unit test in `tests/Unit/Skills/YourSkillTest.php`

---

## Related

- [Agent Contracts](agent-contracts.md)
- [Deployment Guide](deployment-guide.md)
- [Governance Specification](governance-spec.md)
