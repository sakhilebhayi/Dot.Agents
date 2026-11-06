# R-37 Dot.Agents audit and plan

Brief: `requirements/dot-agents-transformation.md`. Research: `R37_RESEARCH.md`. Audited 2026-11-06 at `5e9d4d7`, the code live since 2026-10-28.

Method: code read end to end, 1,276 existing tests run, scratch probes against the engine, and the seeded app used in a browser at desktop and phone width. No model has been called, because no key is available. `feature-completion-ledger.md` is history: several of its "working" rows only meant the page renders.

**This is the state found on 2026-11-06, before the rebuild. The state at delivery is in `R37_READINESS.md`.**

## The short version

Dot.Agents has a large, well-tested surface and almost nothing at its centre. It can hold a conversation with a model. It cannot do work.

- Agents make one model call per message. No tools are passed, so no tool has ever fired in a run.
- There is no record of a run. Five tables each hold part of what happened and none holds the steps.
- The task runner has no callers, so nothing creates a task except a workflow run, inside a web request.
- Approving a task leaves it stuck, and rejecting it from the page does not work.
- Workflows report `completed` when nodes failed or were waiting for approval.
- Agents cannot be created. `agents` is a seeded global catalogue, and everything else is a deployment of it.
- Knowledge articles never reach a prompt. Versions have no writer, so nothing can roll back.
- Marketplace ratings, review counts and deploy counts are random numbers from a seeder.
- 10 of the 20 authenticated pages the brief asks for do not exist.

The existing suite passes because it pins this behaviour. A green run here is not evidence that an agent works.

Worth keeping: tenant scoping once it fails closed, the deployment lifecycle actions, chat through the queue, memory write, the decision log, audit service, department CRUD, graph persistence, the notification bell, error pages, Stripe code, and the deploy pipeline, which is the strongest part of operations.

## Status of each capability

| Capability | Status |
|---|---|
| Chat with a deployed agent | Operational in mock mode; provider errors are stored as answers |
| Execution engine | Partial: single call, no loop, no run record |
| Task runner (`ProcessAgentTask`) | Not connected |
| Retries | Broken |
| Cancel, resume, recovery | Missing |
| Limits on steps, tools, cost, concurrency | Partial: methods exist, no caller in a run |
| Model providers | Partial: Prism with failover, hardcoded older rates, `mock` default |
| Tools and skills | Not connected: 5,053 lines of heuristics with no model and no caller |
| Tool permissions | Not connected, and default allow |
| Workflow builder | Unreliable: saves and runs, status untrue, nodes carry no instructions, canvas fails on a phone |
| Multi-agent | Missing beyond a depth check with no caller |
| Agent memory | Partial: written, encrypted, not governed, crosses users |
| Knowledge | Partial: CRUD works, agents never read it, no documents |
| Triggers and schedules | Missing: `trigger_type` is stored and never read |
| Approvals | Broken |
| Evals and test workspace | Missing |
| Versioning and rollback | Missing |
| Execution history | Missing as a page |
| Agent creation (all four routes) | Missing |
| Dashboard | Partial: real queries, one capped figure, invisible headings |
| Marketplace | Simulated: real query engine over random numbers |
| Usage and cost | Partial: tokens metered, chat outside the quota, every row labelled `gpt-4o` |
| Roles | Broken: three systems side by side, seven roles required |
| Audit log and Security Center | Broken for owners |
| Social suite | Unreachable from navigation; latent, kept as an optional pack |
| Billing | Partial: portal buttons fail |
| Landing page | Claims more than the code does; corrected first, rewritten last |

Security findings are kept out of this public file. They are fixed in A0, and the delivery report lists what changed.

## Production

Shared cPanel behind LiteSpeed. PHP 8.4. SQLite for data, cache, sessions and queue. No Redis, Horizon or Reverb. One cron worker a minute. Assets are built on the runner. This shapes every decision below.

`retry_after` of 90 seconds sits below the job timeouts, so a slow job can run twice. Workers overlap with no bound. SQLite has no `SKIP LOCKED`, so one worker slot for agent steps.

## Decisions

Taken under the owner's no-stops order of 2026-11-06. Each can be changed later.

1. **An agent belongs to a business.** `agents` stays as the catalogue and gains an owner, so a null owner is official and a set owner is private. `agent_deployments` stays as the working agent.
2. **Every run pins an immutable config version.** That one table gives rollback, regression evals and history.
3. **One engine.** Chat, tasks, workflows, schedules and webhooks all create a run. `agent_runs` and `agent_run_steps` are the only record of what happened.
4. **A journalled step machine.** One model call or one effect per queue job, under 40 seconds, leased, checkpointed. Waiting for a person holds no process. A sweeper recovers what a dead worker left. Temporal-style replay and hosted harnesses are out.
5. **Effects are planned before they run.** Each side effect has a row and an idempotency key. A timeout is `unknown` and is reconciled, never retried blind.
6. **Security is enforced in PHP, not by the model.** Default-deny tool grants, trust labels at the source, run-level taint, a trifecta gate, a policy check before every tool call, closed output channels. Classifiers are a signal only.
7. **Approvals gate on consequence.** System-rendered cards from typed arguments, hash-bound to what runs. Model text never appears in the card as fact.
8. **Autonomy is per agent and action class,** with the brief's four levels. Taint lowers it. Hard ceilings cannot be lifted: moving money, final decisions about a person, unrecoverable deletion.
9. **One model gateway on Prism.** `openai-php` goes. Provider, model and price live in the database. Prism sits under a small interface, so it can be replaced.
10. **Laravel 13 and PHP 8.4,** matching Tasks and Sheets.
11. **The visual builder stays, because the brief asks for it.** Research warns that graph canvases are where products get complicated, so the builder sits on the same engine, starts from templates, and works on a phone as a list of steps.
12. **Multi-agent defaults to one agent per job.** Google's numbers show sequential multi-agent work getting worse. Teams are offered where the work is really separable, with a supervisor.
13. **Between Dot platforms: typed tools over signed HTTP, through the outbox.** No MCP between platforms. MCP server later, read-only. No customer-added MCP servers. No A2A.
14. **The community marketplace waits.** Official and business-private templates only. A third-party skill is installed software.
15. **Prices are never invented.** Rates are a dated table an admin maintains, cost is computed at write time, and failed and retried spend is shown apart from useful spend.
16. **Social becomes an optional pack,** off the main navigation until its webhooks and pages are proven.
17. **Nothing here is called working without a test,** and anything tested only against a fake says so.

## Plan

Each piece is one pull request with tests. Nothing deploys until everything is done.

| Phase | Work |
|---|---|
| **A0 Safe base** | Dependencies and audit gate, Laravel 13, tenant context that fails closed, ownership checks, webhook fix, API token scopes, the seven roles, InfoDot removal, false claims removed, SQLite settings, security headers, `/ready` |
| **A1 Design** | Competitor study, direction from the installed skills, `DESIGN.md`, tokens, components, shell, navigation |
| **A2 Agent model** | Business-owned agents, config versions, profiles, statuses, departments, creation (describe, template, manual, import, duplicate), review assistant |
| **A3 Engine** | Runs, steps, effects, usage ledger, price table, model gateway, lease, sweeper, limits, cancel, resume, instant start after a request, chat and tasks moved onto it |
| **A4 Tools and security** | Tool contract and catalogue, grants, policy gate, taint, trifecta, first real tools, custom API tools, SSRF guard, red-team suite |
| **A5 Approvals and autonomy** | Approval centre, card, resume, reject, request changes, escalate, delegate, expiry, rules, autonomy levels |
| **A6 Knowledge and memory** | Documents, chunks, retrieval with sources, memory scopes and governance |
| **A7 Orchestration** | Workflows on the engine, parallel steps, branching, handoff, supervisor, teams, truthful status, builder |
| **A8 Triggers** | Schedules, webhooks, API runs, business events |
| **A9 Testing and deployment** | Test workspace, evals, regression, staging, publish, rollback |
| **A10 Command centre** | Execution centre, timeline, failure investigation, dashboards, usage, budgets, alerts |
| **A11 Marketplace and ecosystem** | Honest catalogue, official working templates, private templates, tool catalogue, Dot tools through the outbox |
| **A12 Readiness** | Route and action sweeps, performance, concurrency, recovery, accessibility, landing page, delivery report, the 30 acceptance tests, deploy |

## The 30 acceptance tests today

| # | Test | Today |
|---|---|---|
| 1 | Create an agent manually | Missing |
| 2 | Create from natural language | Missing |
| 3 | Configure and save | Partial, five fields at deploy |
| 4 | Add knowledge sources | Missing |
| 5 | Configure tools and permissions | Not connected |
| 6 | Test before deployment | Missing |
| 7 | Deploy | Pass, straight to active |
| 8 | Run manually | Partial, chat only |
| 9 | Scheduled run | Missing |
| 10 | Event run | Missing |
| 11 | Multi-step workflow | Unreliable |
| 12 | Multi-agent workflow | Unreliable |
| 13 | Failed tool call | Missing |
| 14 | Recover an interrupted run | Missing |
| 15 | Cancel a run | Missing |
| 16 | Approve | Broken |
| 17 | Reject | Broken |
| 18 | Inspect logs | Missing |
| 19 | Review memory and sources | Missing |
| 20 | Update and restore a version | Missing |
| 21 | Usage and cost | Partial |
| 22 | Permissions and tenant isolation | Fail |
| 23 | Prompt injection | Fail |
| 24 | Rate and execution limits | Partial |
| 25 | Concurrent runs | Untested |
| 26 | Dot integrations claimed as operational | None claimed yet |
| 27 | Responsive | Fail |
| 28 | Dashboard accuracy | Partial |
| 29 | Accessibility | Fail |
| 30 | Realistic workload | Not measured |
