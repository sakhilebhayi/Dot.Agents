# Getting Started — Dot.Agents Developer Guide

## Overview

Dot.Agents is a **multi-tenant, enterprise AI workforce platform** built on Laravel 12 with Livewire 3, Jetstream Teams, and Prism PHP for AI orchestration. This guide walks a new developer from zero to running tests in under 30 minutes.

---

## Prerequisites

| Tool | Version | Purpose |
|------|---------|---------|
| PHP | 8.3+ | Runtime |
| Composer | 2.x | Dependency management |
| Node.js | 20+ | Asset bundling |
| SQLite | 3.x | Local testing |
| Docker (optional) | 24+ | Full-stack local environment |

---

## Quick Start (Docker)

```bash
# 1. Clone
git clone https://github.com/your-org/Dot.Agents.git
cd Dot.Agents

# 2. Copy environment
cp .env.example .env

# 3. Start containers (PHP, MySQL, Nginx, Horizon, Scheduler)
docker-compose up -d

# 4. Install dependencies inside container
docker-compose exec app composer install
docker-compose exec app npm install && npm run build

# 5. Generate key and migrate
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed

# 6. Open
open http://localhost
```

---

## Quick Start (Native)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev
php artisan serve
```

---

## Running Tests

```bash
# All tests (uses SQLite in-memory)
php artisan test --compact

# Specific suite
php artisan test --compact tests/Feature/Actions/

# Single class
php artisan test --compact --filter=DeployAgentActionTest
```

All tests use `RefreshDatabase` and in-memory SQLite. Never write directly to `Model::create()` in tests — use factories.

---

## Code Style

```bash
vendor/bin/pint --dirty --format agent
```

Pint is enforced on every PR via GitHub Actions.

---

## Architecture Quick Map

```
Request → Route → Livewire Component / Controller
            ↓
         Form Request (validation + authorization)
            ↓
         Action Class (Gate::authorize → business logic → fire event)
            ↓
         Event → Listener (queued) → Job
```

See [Architecture Overview](../architecture/) for full diagrams.

---

## Key Directories

| Path | Purpose |
|------|---------|
| `app/Actions/` | Single-purpose write operations |
| `app/DTOs/` | Typed request/response data transfer objects |
| `app/Livewire/` | UI components (no business logic) |
| `app/Services/` | Reusable stateless business services |
| `app/Events/` | Domain events fired after state changes |
| `tests/Feature/Actions/` | One test file per Action class |

---

## Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `OPENAI_API_KEY` | — | AI inference |
| `AUDIT_LOG_RETENTION_DAYS` | 730 | Audit log pruning window |
| `QUEUE_CONNECTION` | sync | Queue driver (use `redis` in prod) |
| `DB_CONNECTION` | sqlite | Database driver |

---

## Next Steps

- [API Reference](../api/openapi.yaml) — full OpenAPI spec
- [Webhook Integration](webhook-integration.md) — outbound event delivery
- [Agent Versioning](agent-versioning.md) — deploying and rolling back agents
- [Onboarding Guide](onboarding.md) — platform administration
