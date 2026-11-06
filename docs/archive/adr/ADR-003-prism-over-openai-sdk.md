# ADR-003: AI Orchestration via Prism PHP (not direct OpenAI SDK)

**Status:** Accepted  
**Date:** 2026-06-19  
**Authors:** Platform Engineering

---

## Context

Dot.Agents requires AI completion calls across multiple providers (OpenAI GPT-4o, Anthropic Claude, future models). Initially the `openai-php/client` SDK was the obvious choice, but as the platform grew we faced:

- Provider lock-in risk (single SDK = single provider)
- No built-in structured output / tool-call abstraction
- No built-in retry, streaming, or embedding abstraction
- Inconsistent response schema across future provider additions

## Decision

We use **Prism PHP** (`prism-php/prism`) as the unified AI orchestration layer over direct SDK usage.

Prism provides:
- Provider-agnostic interface (OpenAI, Anthropic, Gemini via same API)
- Tool/function calling abstraction
- Structured output with schema validation
- Streaming support
- Retry and timeout configuration in one place

Direct `openai-php/client` is retained as a peer dependency (Prism uses it internally) and may be used for OpenAI-specific features not yet in Prism (e.g., fine-tuning management).

**All AI calls in application code MUST go through `AgentOrchestrationService` or a dedicated `*SkillService`**, never directly via Prism or the OpenAI SDK from a controller, Livewire component, or Action.

## Consequences

**Positive:**
- Adding a new AI provider requires only a Prism configuration change, no application code changes
- Structured outputs are validated before reaching business logic
- Centralized rate limiting and cost tracking in `AgentOrchestrationService`

**Negative:**
- Prism PHP is a younger ecosystem than the OpenAI SDK; some advanced features may lag
- An additional abstraction layer adds debugging complexity
- Teams must learn Prism's API instead of the familiar OpenAI SDK

**Migration Path:**
If Prism becomes unmaintained or insufficient, swap the provider in `AgentOrchestrationService`. The `AgentContract` interface ensures all skill implementations remain unaffected.
