# R-37 Dot.Agents delivery report

Brief: `requirements/dot-agents-transformation.md`. Audit and plan: `R37_AUDIT.md`. Research: `R37_RESEARCH.md`.

## The honest summary

Dot.Agents now does work. An agent runs in steps on a journal, uses only tools it was given, stops for a person when it should, and leaves a record of all of it.

**No real AI model has been called from this code.** No provider key was available. Everything below that involves a model was tested with a scripted stand-in, and the code that talks to providers has only been tested against a fake reply from the provider library. The first real run on the host is an open item, and it is the most important one.

## What "tested" means here

| Word | Meaning |
|---|---|
| Operational | Works end to end in automated tests and was used in a browser on a local server |
| Built, not connected | The code exists and passes against a fake of the other side. It has never touched the real thing |
| Not built | Does not exist |

## Existing capabilities discovered

See `R37_AUDIT.md`. In short: a large, tested surface with almost nothing at its centre. Agents made one model call per message, no tool had ever fired in a run, nothing recorded a run, and approving a task left it stuck. Kept and built on: tenant scoping, deployment lifecycle actions, chat through the queue, the audit service, departments and graph persistence.

## Competitors researched

OpenAI, Anthropic, Microsoft Copilot Studio and Agent 365, Salesforce Agentforce, Google Gemini Enterprise, AWS Bedrock and AgentCore, LangGraph and LangSmith, CrewAI, n8n, Dify and others, with sources and a tag on every claim saying how far it was checked. See `R37_RESEARCH.md`.

The gap chosen for Dot.Agents: an agent that a small business can trust without an engineer watching it. That means the check on every action lives in the platform and not in the prompt, approvals are written by the system from the real arguments, and every figure is counted from what ran.

## Skills applied

Design: impeccable (polish, audit and the detector on every new stylesheet), design-taste-frontend, awwwards (landing page, audit script), ui-ux-pro-max, emil-design-eng, design-md, dataviz for charts. Build: test-driven development, verification before completion, systematic debugging.

## Design delivered

The Signal Box, recorded in `DESIGN.md`: a signal box at night, a run as a line with stops, gold for what is held. Every signed-in page uses it apart from the social pack, which stays in its older look and off the main navigation.

Rebuilt: command centre, runs, run detail, agents, agent setup, creation, versions, test bench, triggers, chat, scorecard, approvals, approval rules, workflows, builder, teams, knowledge, memory, decisions, audit log, retention, security, people, departments, business settings, API keys, custom tools, usage, billing, plans, your account, sign-in and its neighbours, terms, policy, consent, error pages and the public landing page.

Checks on the signed-in pages: axe (WCAG 2 A and AA) found no violations in the final check of 14 pages at 1280 wide and 13 at 375 wide, after fixing what it found along the way. No page scrolls sideways at either width. That is in the dark theme. The light theme was checked on a sample of pages, not all of them.

## New and strengthened capabilities

| Area | Delivered | State |
|---|---|---|
| Agent model | Business-owned agents, versions, publish, restore, describe, templates, import, duplicate, review | Operational, with the description assistant falling back to rules when no model answers |
| Engine | Journalled steps, one published version per run, lease, sweeper, retries, cancel, limits, instant start after a web request | Operational with the scripted model |
| Tools | Default deny, schema check, taint, ceilings, custom API tools, SSRF guard, red-team suite | Operational |
| Approvals | Desk, system-written card, edit, reject, ask for changes, escalate, delegate, expiry, rules, autonomy levels | Operational |
| Knowledge | PDF, Word, Excel, CSV, text, websites, keyword search with sources | Operational. Search is keyword, not semantic |
| Memory | Scopes, review, correction, retention, erasure | Operational |
| Orchestration | Parallel, branches, failure lines, retries, approval and tool steps, teams with a supervisor, truthful status | Operational with the scripted model |
| Triggers | Schedules, signed webhooks, events, API | Operational |
| Testing | Simulated writes, saved cases, version comparison, stages | Operational |
| Command centre | Timeline, failure explanations, usage, budgets, definitions beside figures | Operational |
| Templates | Honest library with no invented ratings, private templates | Operational |
| Invitations | Email link into a business, for people with or without an account | Operational. The email itself has only been tested through a mail fake; locally the send failed and the fallback link worked |
| Privacy | Export, erasure, memories included | Operational |

## Security

From the audit, closed before or during the rebuild: tenant boundary, roles, API keys, headers, approval takeover, and the model being able to decide its own permissions.

Added at the end: a hash chain over the audit log with a verify command, webhook secrets encrypted, wildcard API keys refused at the sign-in handoff, API keys kept to their business, consent no longer recorded without the box ticked, health routes that wrote to disk removed in favour of `/ready`.

The chain makes a change visible. It does not prevent one, and a person with database access could rewrite the chain.

Prompt injection is treated as unsolved. The injection regex only records a signal. What protects the business is that outside content cannot reach the system prompt, a tainted run needs a person to change anything, and the gate does not read the model's reasons.

## Backend and database

Fifteen migrations dated 2026-11-08. About 1,800 lines of old engines removed in A7, then the old brain services, the OpenAI client (Prism replaces it), Docker files nothing used, and three health controllers. Old documents moved to `docs/archive`.

The first deploy moves existing memories to review, and it drops `memory_embeddings`.

A load test found a real bug: `busy_timeout` was set twice in `config/database.php`, and the second value cancelled the first, so writers failed instead of waiting. Fixed, and transactions now take the write lock at the start.

Laravel Boost stays a development tool only. No change was needed.

## Tests

| Run | Result |
|---|---|
| Full suite on SQLite | 1,596 tests, 4,645 assertions, no failures, 9 skipped, 4 PHPUnit deprecation notices |
| Agents suite on Postgres 16 | 369 tests, 949 assertions, no failures |
| Pint, PHPStan on baseline | Clean |
| Deliberate breaks | Run on the engine, tools, approvals, knowledge, setup, orchestration, triggers, command centre and readiness code. Each survivor got a test or was recorded as a second check on something already caught |

All of this uses the scripted model. The suite passing means the machinery is right. It says nothing about how good a real model's work will be.

### The 30 acceptance tests

| # | Test | Result | Evidence |
|---|---|---|---|
| 1 | Create manually | Pass | `an_agent_can_be_built_by_hand` |
| 2 | Create from words | Pass with rules and a scripted model | `a_description_becomes_a_proposal_without_creating_anything` |
| 3 | Configure and save | Pass | `the_setup_saves_to_a_draft_and_leaves_what_runs_alone` |
| 4 | Add knowledge | Pass | `the_knowledge_page_uploads_and_lists_a_file` |
| 5 | Tools and permissions | Pass | `only_granted_tools_reach_the_model_and_an_invented_one_is_refused` |
| 6 | Test before deploy | Pass | `a_test_changes_nothing_and_reaches_nothing_outside` |
| 7 | Deploy | Pass | `publishing_puts_the_new_setup_and_autonomy_into_use` |
| 8 | Run manually | Pass with scripted model | `a_run_finishes_with_the_models_answer_and_a_journal` |
| 9 | Scheduled run | Pass | `a_due_schedule_starts_one_run_and_moves_on` |
| 10 | Event run | Pass | `a_business_event_starts_the_work_set_to_follow_it` |
| 11 | Multi-step workflow | Pass | `steps_run_in_order_and_each_is_given_what_the_last_one_made` |
| 12 | Multi-agent workflow | Pass | `a_supervisor_hands_work_to_a_teammate_waits_and_uses_the_result` |
| 13 | Failed tool call | Pass | `an_api_refusal_is_a_failure_the_model_is_told_about`, `a_tool_can_be_made_to_fail_on_purpose` |
| 14 | Recover an interrupted run | Pass | `a_run_a_dead_worker_left_is_picked_up_by_the_sweeper` |
| 15 | Cancel | Pass | `a_person_can_cancel_and_the_next_step_notices` |
| 16 | Approve | Pass | `test_approving_runs_the_action_once_and_the_run_carries_on` |
| 17 | Reject | Pass | `test_rejecting_runs_nothing_and_the_agent_hears_the_persons_words` |
| 18 | Inspect logs | Pass | `a_run_shows_its_whole_story` |
| 19 | Memory and sources | Pass | `a_document_is_split_and_found_with_its_source`, `an_administrator_can_read_correct_accept_and_remove_with_an_audit_trail` |
| 20 | Update and restore a version | Pass | `restoring_brings_an_old_setup_back_as_a_new_version` |
| 21 | Usage and cost | Pass with test prices | `cost_comes_from_a_dated_price_and_stays_unknown_without_one`. No real prices are loaded |
| 22 | Permissions and isolation | Pass | `one_businesss_step_cannot_touch_anothers_run`, `tests/Feature/Security/CrossTenantLeakageTest.php` |
| 23 | Prompt injection | Pass for the checks that exist | `no_wording_gets_a_hijacked_model_past_the_gate`. Not tested against a real model |
| 24 | Rate and execution limits | Pass | `a_step_limit_stops_a_run_and_says_why`, `a_tool_call_limit_stops_a_loop` |
| 25 | Concurrent runs | Pass | Load test below, `two_workers_cannot_hold_one_run` |
| 26 | Dot integrations claimed | Pass by claiming little | See below |
| 27 | Responsive | Pass on the pages checked | No sideways scroll at 375 |
| 28 | Dashboard accuracy | Pass | `figures_are_counted_from_real_runs` |
| 29 | Accessibility | Pass on the pages checked | axe clean. No screen reader session has been done by a person |
| 30 | Realistic workload | Partly | Load test only. No real model, no real business data, not on the host |

## Performance

Laptop, throwaway SQLite file, scripted model, so this measures the platform and not a provider. 600 runs across ten agents, each of two queue steps:

| Workers | Runs per second | Failed jobs | Lock errors |
|---|---:|---:|---:|
| 1 | 61 | 0 | 0 |
| 4 | 56 | 0 | 0 |
| 8 | 49 | 0 | 0 |
| 16 | 39 | 0 | 0 |

Before the fix, eight workers gave lock errors and left jobs stranded for retry. The host will be slower, and it runs one agent worker a minute for 50 seconds at most, plus the step after a web request. Real runs will be limited by the model far more than by SQLite.

At the default limit of three runs per agent, extra work waits 30 seconds and tries again. 600 runs on one agent took about two minutes with four workers, and that is the limit doing its job.

The three busiest pages are tested so that their query count does not grow with rows.

## Integrations

| Connection | State |
|---|---|
| InfoDot sign-in | Operational, existing |
| Model providers through Prism | Built, not connected to a real provider |
| Dot.Files | Built, not connected. Needs `FILES_PLATFORM_SECRET` |
| Dot.Memory reporting | Built, not connected. Needs its host settings |
| Mail | Tested with a fake. Needs host settings checked |
| Stripe | Existing code, not exercised here |
| Social platforms | Existing pack, not rebuilt, not exercised here |
| Dot.Notify tool, other Dot platforms, marketplace partners | Not built |

## Known limitations

- Knowledge search is by keyword. Semantic search needs an embedding provider.
- The social pack keeps its older design.
- The scorecard page reads tasks and decisions, which the run engine mostly does not write, so most agents will show nothing there. Use the command centre and usage pages.
- A key rotation has no bulk re-encrypt, so an old key must stay in `APP_PREVIOUS_KEYS`.
- Jetstream team pages stay reachable by address because InfoDot team sync depends on that feature. Nothing links to them.
- The API still says "deployment" for an agent.
- Backups stay on the host.

## Outstanding, needs the owner

**Before relying on it**

1. Set `AI_PROVIDER` and a provider key on the host, with a monthly budget, and watch one real run.
2. Load real model prices with `agents:set-price`, with a source. Until then costs show as unknown.
3. Add the new cron line by deploying, and confirm the deploy log shows three lines.
4. Get a copy of the backups off the host, then run a restore on a copy and time it.
5. Mail settings on the host, so invitations and approval notices arrive.
6. `FILES_PLATFORM_SECRET`, Dot.Memory settings and `GUARDIAN_TOKEN` when integration secrets are done.

**Checks only the host can answer**

7. Whether seeded demo users exist in production and who has signed in as them.
8. Row counts in the old tables, and Stripe and Meta webhook history.
9. Remove `.env.bak-secrets` and the dead files from the host: `app/Http/Controllers/Api/V1/SkillController.php`, `app/Models/Concerns/BelongsToOrganization.php`, `app/Models/Scopes/OrganizationScope.php`.

**Legal text, for a person qualified to judge it**

10. Terms and policy still use `@dotagents.com` addresses. Confirm they receive mail, or give the right ones.
11. The terms describe modules that are not in the product (Executive Council, Digital Twin, Simulation Engine, Memory Cortex) and delusion detection as a safeguard. The trademark marks have no evidence behind them.
12. The policy names only OpenAI as a provider and lists Redis. It promises deletion within 90 days, an analytics opt-out, data processing agreements, standard contractual clauses and a Data Protection Officer that were not verified. It leaves out the other Dot services.
13. The consent page says all agent actions are subject to human approval. That is not true at controlled autonomy.
14. The "last updated" date on both pages was printing today's date on every request. It is now fixed at 19 September 2026, the last change to the file. Set the real date when the text is reviewed.

**Production blockers**

None in the code. Agents cannot do real work until item 1 is done.
