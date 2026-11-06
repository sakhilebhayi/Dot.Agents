# Developer Onboarding Guide

## Welcome to Dot.Agents

This guide will get you from zero to a running development environment and your first feature contribution in under 30 minutes.

---

## 1. Prerequisites

- PHP 8.4+ (`php -v`)
- Composer 2.x (`composer -V`)
- Node.js 20+ (`node -v`)
- Git
- A code editor (VS Code recommended — `.vscode/` settings included)

---

## 2. Local Setup

```bash
# Clone the repository
git clone https://github.com/sakhileb/Dot.Agents.git
cd Dot.Agents

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# For local dev — SQLite is preconfigured
touch database/database.sqlite

# Run migrations and seeders
php artisan migrate --seed

# Build frontend assets
npm run dev   # dev server with HMR
# or
npm run build  # production build

# Start the application
php artisan serve
```

Visit http://localhost:8000 — you should see the platform.

---

## 3. Development Environment Details

```
Stack:        Laravel 12 + Livewire 3 + Tailwind 4
DB (dev):     SQLite (file: database/database.sqlite)
DB (prod):    MySQL 8+
Cache:        Redis (or array driver in dev)
Queue:        Redis (or sync driver in dev)
Tests:        PHPUnit 11 with SQLite in-memory
Code Style:   Laravel Pint
```

**`.env` for local development (minimum required):**
```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
CACHE_STORE=array
QUEUE_CONNECTION=sync    # Jobs run synchronously in dev
OPENAI_API_KEY=sk-...   # Required for AI features
```

---

## 4. Running Tests

```bash
# Full suite
php artisan test --compact

# Specific domain
php artisan test --compact tests/Feature/Actions/

# Single test
php artisan test --filter DeployAgentActionTest

# With coverage (requires pcov)
php artisan test --coverage --min=80
```

**Expected:** 203+ passing, 0 failing, < 15s.

---

## 5. Code Style

```bash
# Format changed files (run before every commit)
vendor/bin/pint --dirty

# Format all files
vendor/bin/pint
```

CI will reject PRs that fail Pint.

---

## 6. Architecture — The 13-Step Workflow

Every new feature MUST follow this sequence:

```
1.  Analyze requirements → identify domain (Agents / Governance / Billing / Orgs)
2.  Architecture design → domain, services, events
3.  php artisan make:migration create_xxx_table
4.  php artisan make:model XxxModel --factory
5.  php artisan make:policy XxxPolicy --model=XxxModel
6.  php artisan make:request XxxRequest
7.  php artisan make:class app/DTOs/Domain/XxxData
8.  php artisan make:class app/Actions/Domain/XxxAction
9.  php artisan make:event XxxHappened
10. php artisan make:listener HandleXxx --event=XxxHappened
11. php artisan make:livewire Domain/XxxComponent (if UI needed)
12. php artisan make:test Feature/Actions/XxxActionTest
13. vendor/bin/pint --dirty && php artisan test --compact
```

---

## 7. Key Architectural Rules

### Actions
- Single public method: `execute()`
- ALWAYS start with `Gate::authorize()`
- ALWAYS end with `event(new XxxEvent(...))`
- NO database calls directly — use Models/Eloquent
- Return a typed Model or DTO

### Livewire Components
- NO business logic — delegate to Actions
- Use `#[Computed]` for derived data
- Use `#[Lazy]` for off-screen components
- Max 200 lines
- Form objects for multi-field forms

### Services
- Stateless — no instance state
- Single responsibility
- Injected via constructor (registered in `AppServiceProvider`)

### Security Rules
- Every query on org-owned data MUST include `->where('organization_id', $orgId)`
- All AI input MUST pass through `AuditService::detectPromptInjection()`
- All AI output MUST pass through `OutputModerationService::scan()`
- All form input MUST use Form Request classes

---

## 8. Making Your First Contribution

```bash
# Create a feature branch
git checkout -b feat/your-feature-name

# Work, test, format
php artisan test --compact
vendor/bin/pint --dirty

# Commit with conventional commit message
git commit -m "feat(agents): add XyzAction with tests"

# Push and open PR
git push origin feat/your-feature-name
```

**PR Requirements:**
- All tests passing
- Pint clean (no style violations)
- New Action has a Feature test
- Security-sensitive changes reviewed by security lead

---

## 9. Useful Commands

```bash
# See all registered routes
php artisan route:list

# See all registered events and listeners
php artisan event:list

# Open Tinker REPL
php artisan tinker

# Fresh migration with seed
php artisan migrate:fresh --seed

# Clear all caches
php artisan cache:clear && php artisan config:clear

# Run specific queue worker
php artisan queue:work --queue=governance
```

---

## 10. Getting Help

- **Architecture questions:** Read `docs/architecture/platform-overview.md`
- **API reference:** See `docs/api/openapi.yaml`
- **Security concerns:** See `docs/security/architecture.md`
- **Deployment issues:** See `docs/deployment/runbook.md`
- **GitHub Issues:** Open a bug/feature request on the repo
