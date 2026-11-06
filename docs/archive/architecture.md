# Security Architecture

## Overview

Dot.Agents is a multi-tenant enterprise AI platform. A security breach could expose sensitive business data across all tenants. Security is the highest-weighted dimension (20%) of the platform health score.

---

## Defense Layers

```
Layer 1: Network / Transport
  ├── HTTPS only (TLS 1.3)
  ├── HSTS headers
  └── Rate limiting (Throttle middleware)

Layer 2: Authentication
  ├── Laravel Sanctum (API tokens)
  ├── Laravel Fortify (session auth)
  ├── 2FA / TOTP support
  └── Passkey (WebAuthn) support

Layer 3: Authorization
  ├── Spatie Permission (RBAC: owner/admin/manager/member/viewer)
  ├── Laravel Policies (model-level: AgentDeploymentPolicy, etc.)
  ├── Gate::authorize() in every Action class
  └── Form Request authorize() for web/API input

Layer 4: Tenant Isolation
  ├── OrganizationContextMiddleware (sets active org)
  ├── Every query on org-owned data: ->where('organization_id', $orgId)
  ├── 3 Global Scopes auto-filter resources
  └── Policy checks verify org ownership before mutations

Layer 5: AI-Specific Security
  ├── Input: AuditService::detectPromptInjection() on ALL user input
  ├── Output: OutputModerationService::scan() on ALL AI responses
  ├── Tool: ToolPermissionService deny-wins per tool/role
  └── Sandbox: AgentSandboxService enforces tool restrictions

Layer 6: Infrastructure
  ├── .env not committed to git
  ├── No hardcoded secrets (verified by CI security workflow)
  ├── Dependency audit: composer audit in CI
  └── CorrelationIdMiddleware for distributed tracing
```

---

## Prompt Injection Protection

All user input that will be sent to an AI model passes through:

```php
// In every Livewire component or Action that handles AI input:
if ($auditService->detectPromptInjection($userInput, $deployment)) {
    // Log SecurityEvent::prompt_injection
    // Return user-facing error — never forward to AI
    return;
}
```

Detection heuristics:
- Instruction override patterns ("Ignore previous instructions")
- Role impersonation attempts ("You are now...")
- System prompt extraction attempts
- Jailbreak patterns

---

## Output Moderation

Every AI response passes through `OutputModerationService::scan()`:

| Flag Type | Detection | Verdict |
|-----------|-----------|---------|
| PII | Email, phone, SSN patterns | WARN / BLOCK |
| Secrets | OpenAI/AWS/GitHub/Stripe key patterns | BLOCK |
| Prompt leakage | "As instructed in your system prompt" | BLOCK |
| Unsafe instructions | Harmful action patterns | BLOCK |
| Hallucination indicators | Hedging on factual claims | WARN |

Verdicts:
- `PASS` — Return response to user
- `WARN` — Log flag, return response with annotation
- `BLOCK` — Never return response, log SecurityEvent

---

## Multi-Tenant Isolation Guarantee

```
Organization A                Organization B
      │                              │
      ▼                              ▼
agent_deployments             agent_deployments
WHERE org_id = A              WHERE org_id = B
      │                              │
      ▼                              ▼
  [A's agents]                  [B's agents]
```

**Verified by:** `tests/Feature/Security/TenantIsolationTest.php`

Cross-tenant queries are impossible because:
1. Every query includes `organization_id` scope
2. Policies reject access to other-org resources
3. Route model binding + Policy enforces ownership

---

## Secret Rotation Policy

| Secret | Rotation Frequency | Method |
|--------|-------------------|--------|
| `APP_KEY` | On breach / annually | `php artisan key:generate` |
| `OPENAI_API_KEY` | Quarterly | OpenAI dashboard |
| `STRIPE_SECRET` | Annually or on breach | Stripe dashboard |
| `DB_PASSWORD` | Annually | DBA rotation |
| User Sanctum tokens | 90 days (configurable) | `sanctum:prune-expired` |

---

## OWASP Top 10 Compliance

| Risk | Status | Mitigation |
|------|--------|-----------|
| A01 Broken Access Control | ✅ Mitigated | Policies + Gate on every Action |
| A02 Cryptographic Failures | ✅ Mitigated | HTTPS, hashed passwords, no plaintext secrets |
| A03 Injection | ✅ Mitigated | Eloquent ORM + bindings; no raw queries with user input |
| A04 Insecure Design | ✅ Mitigated | Defense-in-depth; multi-layer AI safety |
| A05 Security Misconfiguration | ✅ Mitigated | CI security audit; .env not committed |
| A06 Vulnerable Components | ✅ Mitigated | `composer audit` in CI; Dependabot |
| A07 Auth Failures | ✅ Mitigated | Sanctum + Fortify + 2FA; throttled login |
| A08 Software Integrity | ✅ Mitigated | Composer lock; verified package signatures |
| A09 Logging Failures | ✅ Mitigated | AuditService logs all actions; SecurityEvent table |
| A10 SSRF | ⚠️ Partial | AI tool calls validated; no full SSRF scanner yet |

---

## Security Testing

```bash
# Run security-specific tests
php artisan test --compact tests/Feature/Security/

# Run prompt injection tests
php artisan test --compact --filter=PromptInjection

# Dependency vulnerability scan
composer audit

# Static analysis (detects injection patterns)
vendor/bin/phpstan analyse --level=6
```
