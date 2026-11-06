# Platform Architecture Overview

## System Context

Dot.Agents is a multi-tenant enterprise AI workforce platform. Organizations hire, deploy, manage, monitor, and govern specialized AI Agents as digital workforce members.

```
┌─────────────────────────────────────────────────────────────────────┐
│                        DOT.AGENTS PLATFORM                          │
│                                                                     │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────────┐  │
│  │   Web UI     │    │   REST API   │    │   Background Jobs    │  │
│  │ (Livewire 3) │    │ (Sanctum v1) │    │   (Redis + Queues)  │  │
│  └──────┬───────┘    └──────┬───────┘    └──────────┬───────────┘  │
│         │                   │                        │              │
│  ┌──────▼───────────────────▼────────────────────────▼──────────┐  │
│  │                    Application Layer                          │  │
│  │  Actions ─────► DTOs ─────► Services ─────► Events           │  │
│  │                                                               │  │
│  │  ┌──────────────────────────────────────────────────────┐    │  │
│  │  │              AI Governance Stack                      │    │  │
│  │  │  DelusionDetection │ OutputModeration │ Approvals     │    │  │
│  │  │  CircuitBreaker    │ ToolPermissions  │ AuditLogs     │    │  │
│  │  └──────────────────────────────────────────────────────┘    │  │
│  └────────────────────────────────────────┬──────────────────────┘  │
│                                           │                         │
│  ┌─────────────────────────────────────── ▼──────────────────────┐  │
│  │                     Data Layer                                 │  │
│  │         SQLite (dev) / MySQL 8+ (prod) / Redis (cache+queue)  │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                 ▼
         ┌─────────┐    ┌───────────┐    ┌──────────────┐
         │ OpenAI  │    │ Anthropic │    │  Google AI   │
         │ GPT-4o  │    │  Claude   │    │   Gemini     │
         └─────────┘    └───────────┘    └──────────────┘
                              + Ollama (self-hosted fallback)
```

## Domain Boundaries

### 1. Agents Domain (`app/Actions/Agents/`, `app/Services/AI/`)
Manages the full agent lifecycle: deployment, execution, memory, scoring.

| Component | Responsibility |
|-----------|---------------|
| `DeployAgentAction` | Deploy an agent instance for an organization |
| `PauseDeploymentAction` | Suspend a deployment |
| `DecommissionDeploymentAction` | Permanently remove a deployment |
| `UpdateDeploymentAction` | Modify deployment configuration |
| `AgentOrchestrationService` | Route requests to AI providers; failover; moderation |
| `ModelRouterService` | Select provider + model; manage failover chain |
| `AgentSandboxService` | Enforce tool restrictions and permission boundaries |
| `MemoryService` | Agent long-term and short-term memory management |
| `ScorecardService` | Calculate 10-dimension agent health scores |

### 2. Governance Domain (`app/Actions/Governance/`, `app/Services/Governance/`)
Ensures every AI decision is tracked, audited, and appropriately supervised.

| Component | Responsibility |
|-----------|---------------|
| `ProcessApprovalAction` | Process human review decisions |
| `CreateDecisionLogAction` | Log every AI output with metadata |
| `AuditService` | Write immutable audit trail entries |
| `DelusionDetectionService` | Score AI outputs for hallucination risk |
| `OutputModerationService` | Scan outputs for PII, secrets, unsafe content |
| `DigitalImmuneSystemService` | Automated threat detection + self-healing |

### 3. Organizations Domain (`app/Actions/Organizations/`)
Manages multi-tenant organization hierarchy (maps to Jetstream Teams).

### 4. Billing Domain (`app/Actions/Billing/`)
Stripe-powered subscription management and usage metering.

## Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | 12.x |
| PHP | PHP | 8.4 |
| Auth | Jetstream + Livewire | 5.5 |
| UI Components | Livewire | 3.8 |
| CSS | Tailwind CSS | 4.x |
| AI Layer | OpenAI PHP + Prism PHP | latest |
| RBAC | Spatie Laravel Permission | 8.x |
| DB (dev) | SQLite | — |
| DB (prod) | MySQL 8+ | — |
| Cache/Queue | Redis | 7.x |
| Testing | PHPUnit | 11.x |
| Code Style | Laravel Pint | latest |

## AI Provider Failover Chain

```
Request
   │
   ▼
OpenAI (primary)
   │ fails / circuit open?
   ▼
Anthropic (fallback 1)
   │ fails / circuit open?
   ▼
Google AI (fallback 2)
   │ fails / circuit open?
   ▼
Ollama (local fallback)
   │ fails?
   ▼
503 Service Unavailable
```

Each provider has an independent `CircuitBreaker` with:
- 3 failures in 5 minutes → OPEN
- 30-second probe → HALF-OPEN
- 1 success → CLOSED

## Request Flow

```
HTTP Request
    │
    ├── CorrelationIdMiddleware (assigns X-Correlation-ID)
    ├── auth:sanctum
    ├── verified
    └── org.context (sets session organization)
         │
         ▼
    Livewire Component / API Controller
         │
         ├── Form Request (validates + authorizes input)
         │
         ▼
    Action Class
         ├── Gate::authorize (policy check)
         ├── DTO (typed input)
         ├── Service (business logic)
         └── event() (domain event)
              │
              ▼
         Queued Listeners (governance, notifications, scoring)
```

## Queue Architecture

```
Redis Queue Channels:
├── default       ← Standard jobs (SetupOrganizationDefaults)
├── governance    ← Audit logs, scorecard updates, drift detection
├── notifications ← Email/Slack notifications to users
└── ai            ← (future) Heavy AI inference jobs
```

## Multi-Tenant Isolation

Every request is scoped to an organization:
1. `OrganizationContextMiddleware` sets `session('current_organization_id')`
2. All models with `organization_id` enforce it at query level
3. Policies verify ownership before every mutation
4. 3 Global Scopes auto-filter org-owned resources
5. Security tests verify cross-tenant data is inaccessible
