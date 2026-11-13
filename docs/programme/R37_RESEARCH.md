# Dot.Agents competitor and technical research

Research date: 2026-11-06. Prices and availability are as shown on that date unless a different date is given. All prices are in the currency the vendor published (mostly USD), and none are converted to rand.

## How to read this document

Every factual claim carries a source link. The tag beside it says how strong the evidence is.

| Tag | Meaning |
|---|---|
| `[read]` | I opened that page and summarised it with a fetch tool. |
| `[search]` | The claim came from a search-result summary. I did not open the page myself, so check it before quoting it externally. |
| `[local]` | I inspected it in the Dot.Agents repo or vendor code on 2026-11-06. |
| `[judgement]` | My own reading of the evidence, not a sourced fact. |
| **Unverified** | I tried and could not confirm it. Do not rely on it. |

Limits of this research:

- Reddit is refused by the search tool, so there is no direct Reddit evidence. User complaints come from vendor forums, GitHub issues, Microsoft Q&A, Hacker News, Trustpilot, press and analyst notes.
- G2 ratings appear only where another site quotes them. Those sites are often competitors, and I mark them.
- Hacker News results skewed to 2023 and 2024, so they show a pattern rather than current sentiment.
- `openai.com` returned 403 to the fetch tool, so OpenAI's announcement pages were only seen through search. OpenAI's developer docs opened normally.
- Nothing here is legal advice. The POPIA section summarises commentary.

---

## 1. Ten findings that matter most

1. **The market keeps retiring its own agent products, so portability is a real selling point.** OpenAI shuts down Agent Builder, Evals and the Prompts API on 2026-11-30, about 14 months after launching Agent Builder in October 2025 ([OpenAI deprecations](https://developers.openai.com/api/docs/deprecations) `[read]`, [launch coverage](https://www.marktechpost.com/2025/10/06/openai-debuts-agent-builder-and-agentkit-a-visual-first-stack-for-building-deploying-and-evaluating-ai-agents/) `[search]`). AWS closed Bedrock Agents to new customers on 2026-07-30 ([AWS](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-classic-maintenance-mode.html) `[read]`). Zapier Agents is being folded into a single step called AI by Zapier ([Zapier](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]`). Vellum closed its assistant platform on 2026-11-02 ([Vellum](https://www.vellum.ai/pricing) `[read]`).
2. **The direction of travel is deterministic control around a model, not more model freedom.** Salesforce added Agent Script because the old model could not be tested or audited reliably ([Salesforce](https://architect.salesforce.com/docs/architect/fundamentals/guide/hybrid-reasoning-agentforce-builder-agent-script.html) `[read]`). AWS Policy intercepts tool calls outside the agent's code ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/policy.html) `[read]`).
3. **Dot's connected layer creates the lethal trifecta by default.** ForcedLeak used a public web form to reach CRM data through an agent ([The Hacker News](https://thehackernews.com/2025/09/salesforce-patches-critical-forcedleak.html) `[search]`). Dot.Forms, InfoDot, Files uploads and HR applications are the same shape of input. Treat this as the top architecture risk `[judgement]`.
4. **Prompt injection is treated as unsolved, and the proven defences are architectural.** OpenAI says it is unlikely ever to be fully solved ([The Decoder](https://the-decoder.com/openai-admits-prompt-injection-may-never-be-fully-solved-casting-doubt-on-the-agentic-ai-vision/) `[search]`). CaMeL's design solved 77% of AgentDojo tasks with provable security, against 84% undefended ([arXiv](https://arxiv.org/abs/2503.18813) `[read]`). So a measured cost of about 7 points of utility is a fair trade.
5. **Per-action approval does not scale and can be spoofed.** Users accepted 93% of Claude Code prompts ([Anthropic](https://www.anthropic.com/engineering/claude-code-auto-mode) `[read]`). Lies-in-the-Loop poisons the text shown in the approval dialog ([OWASP](https://owasp.org/www-community/attacks/Lies_in_the_Loop) `[search]`). Gate by consequence, and have the system render the dialog from typed arguments.
6. **Cost confusion is the most consistent user complaint across the market.** It appears for Agentforce, ServiceNow, Lindy, Relevance AI and Copilot Studio (section 6.2). One meter, hard caps and rand display would be a clear difference.
7. **Governance for agents is priced for large enterprises.** Microsoft Agent 365 is $15 per user per month, works best with E5, and is included in the $99 E7 ([Microsoft](https://www.microsoft.com/en-us/microsoft-agent-365) `[read]`, [Microsoft Learn](https://learn.microsoft.com/en-us/microsoft-agent-365/overview) `[read]`). I found no equivalent aimed at a 20-person South African business `[judgement, absence not proven]`.
8. **Most agent work outside software is still small, and narrow tasks are what work.** Software is nearly 50% of tool calls on Anthropic's API, and business domains are a few points each ([Anthropic](https://www.anthropic.com/research/measuring-agent-autonomy) `[read]`). Salesforce's benchmark found about 58% single-turn success and about 35% multi-turn ([arXiv](https://arxiv.org/html/2505.18878v1) `[search]`).
9. **More agents often means worse results.** Google measured an 80.9% gain on parallel work and a 39 to 70% loss on sequential work, with error amplification up to 17.2x for independent agents against 4.4x with an orchestrator ([Google Research](https://research.google/blog/towards-a-science-of-scaling-agent-systems-when-and-why-agent-systems-work/) `[read]`). The Digital Workforce metaphor should not push Dot into agent teams by default `[judgement]`.
10. **A journalled step machine fits the cPanel constraint, and needs one local correction first.** One queue job per step, with a database journal and idempotency keys, gives durable behaviour without daemons (section 7.1). Dot.Agents has a default `retry_after` of 90 seconds, which is shorter than a slow LLM call (`config/queue.php` `[local]`). The stack is also Laravel 12.69 with Prism, not Laravel 13 (next section).

### Corrections to the brief

- **Stack version.** The brief says Laravel 13. Dot.Agents is locked to `laravel/framework` v12.69.2, `livewire/livewire` v3.8.9 and `prism-php/prism` v0.100.1. Neither `laravel/ai` nor `laravel/mcp` is installed. Local `main` is at `8be6097`. GitHub `main` at `5e9d4d7` (2026-10-28) also requires `laravel/framework ^12.0` and Prism `^0.100.1` `[local]`.
- **Database.** A recorded note from 2026-10-17 says the Dot.Agents host uses SQLite. Recent repo commits talk about Postgres and MySQL. Confirm which one production uses, because it decides how many workers can run at once (section 7.1) `[local]`.
- **Worker flags.** I could not confirm the live cron flags from the repo. The runbook still shows a Redis Supervisor pattern, and `.github/workflows` has no `queue:work` line in the local checkout `[local]`.

---

## 2. What changed in the last 12 months

| Date | Change | Source |
|---|---|---|
| 2026-02 | New Agentforce GA, with hybrid deterministic reasoning | [Salesforce](https://architect.salesforce.com/docs/architect/fundamentals/guide/hybrid-reasoning-agentforce-builder-agent-script.html) `[read]` |
| 2026-03 | LangChain renames Agent Builder to LangSmith Fleet | [LangChain changelog](https://docs.langchain.com/langsmith/fleet/changelog) `[search]` |
| 2026-03-09 | OpenAI announces it is acquiring Promptfoo | [TNW](https://thenextweb.com/news/openai-acquires-promptfoo-ai-security-frontier) `[search]` |
| 2026-04-08 | Claude Managed Agents launches in beta | [Search summary of third-party posts](https://www.therundown.ai/tools/claude-managed-agents) `[search]`; beta status [read](https://platform.claude.com/docs/en/managed-agents/overview) |
| 2026-04-22 | Vertex AI becomes Gemini Enterprise Agent Platform; Agent Engine becomes Agent Runtime | [Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`, [notes](https://docs.cloud.google.com/gemini-enterprise-agent-platform/release-notes) `[search]` |
| 2026-04-22 | OpenAI launches Workspace Agents | [VentureBeat](https://venturebeat.com/orchestration/openai-unveils-workspace-agents-a-successor-to-custom-gpts-for-enterprises-that-can-plug-directly-into-slack-salesforce-and-more) `[read]` |
| 2026-05-01 | Microsoft Agent 365 GA | [Microsoft Learn](https://learn.microsoft.com/en-us/microsoft-agent-365/overview) `[read]` |
| 2026-06-03 | OpenAI announces Agent Builder, Evals and Prompts API shutdown | [OpenAI](https://developers.openai.com/api/docs/deprecations) `[read]` |
| 2026-07-28 | MCP stateless specification released | [MCP](https://modelcontextprotocol.io/specification/2026-07-28/changelog) `[read]` |
| 2026-07-30 | Bedrock Agents closed to new customers | [AWS](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-classic-maintenance-mode.html) `[read]` |
| 2026-08-26 | OpenAI Assistants API shutdown date | [OpenAI](https://developers.openai.com/api/docs/deprecations) `[read]` |
| 2026-10-01 | Latest Zapier migration guide update, Agents moving into AI by Zapier, no shutdown date yet | [Zapier](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]` |
| 2026-11-02 | Vellum's Personal Assistant Platform shuts down | [Vellum](https://www.vellum.ai/vellum-assistant-platform-shutdown) `[search]`, [banner](https://www.vellum.ai/pricing) `[read]` |
| 2026-11-26 | Vertex AI Extensions shut down after this date | [Google](https://docs.cloud.google.com/vertex-ai/generative-ai/docs/release-notes) `[read]` |
| 2026-11-30 | OpenAI Agent Builder, Evals and Prompts API shut down | [OpenAI](https://developers.openai.com/api/docs/deprecations) `[read]` |

---

## 3. Platform profiles: major platforms

### 3.1 OpenAI Agent Builder, AgentKit and what replaces them

**Status warning: do not model Dot.Agents on Agent Builder. It closes on 2026-11-30.**

- **Status.** Agent Builder closes 2026-11-30. Evals was scheduled to go read-only on 2026-10-31 and closes 2026-11-30. `v1/prompts` closes 2026-11-30. Assistants API closure date was 2026-08-26. ChatKit stays. Replacements are the Agents SDK or Workspace Agents, and a Promptfoo guide for evals ([OpenAI](https://developers.openai.com/api/docs/deprecations) `[read]`).
- **Architecture now.** Three runtimes: the Agents API (managed Codex harness with saved progress, compaction, multi-agent orchestration, programmatic tool calling, MCP), the Agents SDK (runs in your app) and the Responses API (you manage everything). ChatKit is the embed option ([OpenAI](https://developers.openai.com/api/docs/guides/agents) `[read]`).
- **How an agent is created.** In code with the SDK, or by prompt in ChatGPT through Workspace Agents using designs or templates ([VentureBeat](https://venturebeat.com/orchestration/openai-unveils-workspace-agents-a-successor-to-custom-gpts-for-enterprises-that-can-plug-directly-into-slack-salesforce-and-more) `[read]`).
- **Orchestration.** The SDK runner handles the loop and handoffs ([OpenAI](https://developers.openai.com/api/docs/guides/agents) `[read]`).
- **Tools.** Workspace Agents connect to Slack, Drive, Microsoft apps, Salesforce, Notion and Atlassian Rovo, and are built on Codex ([VentureBeat](https://venturebeat.com/orchestration/openai-unveils-workspace-agents-a-successor-to-custom-gpts-for-enterprises-that-can-plug-directly-into-slack-salesforce-and-more) `[read]`).
- **Memory.** Workspace Agents retain what they learn across runs and can be corrected in conversation (same source `[read]`).
- **Observability and evals.** Hosted Evals is closing. OpenAI points to Promptfoo ([OpenAI](https://developers.openai.com/api/docs/deprecations) `[read]`).
- **Human approval.** Write actions in Workspace Agents default to always ask, and can be set per action ([VentureBeat](https://venturebeat.com/orchestration/openai-unveils-workspace-agents-a-successor-to-custom-gpts-for-enterprises-that-can-plug-directly-into-slack-salesforce-and-more) `[read]`). The SDK uses `needs_approval`, pauses with interruptions, and serialises `RunState` to a database ([OpenAI SDK](https://openai.github.io/openai-agents-python/human_in_the_loop/) `[read]`).
- **Security and governance.** Per-role settings cover who can browse, run, build and publish. Authentication can be per user or a shared agent account. A Compliance API exposes run history, and admins can suspend agents ([VentureBeat](https://venturebeat.com/orchestration/openai-unveils-workspace-agents-a-successor-to-custom-gpts-for-enterprises-that-can-plug-directly-into-slack-salesforce-and-more) `[read]`).
- **Deployment.** OpenAI hosted, or your own runtime through the SDK. Sandboxes can be hosted, self-hosted or absent ([OpenAI](https://developers.openai.com/api/docs/guides/agents) `[read]`).
- **Pricing.** Workspace Agents are billed in credits from token use, with billing from 2026-07-06 ([OpenAI notes](https://help.openai.com/en/articles/10128477-chatgpt-enterprise-and-edu-release-notes) `[search]`). The rate card lists GPT-5.6 at 100 credits per 1M input tokens and 500 per 1M output, with a typical run at 5 to 25 credits ([rate card](https://help.openai.com/en/articles/11481834-chatgpt-rate-card-business-enterpriseedu) `[search]`). **Unverified:** AgentKit list pricing.

### 3.2 Anthropic: Agent SDK, Managed Agents, Skills and MCP

- **Architecture.** Four routes: Client SDK, Agent SDK (Claude Code as a library, Python and TypeScript), Managed Agents (hosted harness) and the CLI ([Anthropic](https://code.claude.com/docs/en/agent-sdk/overview) `[read]`). Managed Agents is built from Agent, Environment, Session and Events ([Anthropic](https://platform.claude.com/docs/en/managed-agents/overview) `[read]`).
- **How an agent is created.** Through the API or CLI, defining model, prompt, tools, MCP servers and skills. Create once and reference by ID ([Anthropic](https://platform.claude.com/docs/en/managed-agents/overview) `[read]`).
- **Orchestration.** Agent SDK has subagents ([Anthropic](https://code.claude.com/docs/en/agent-sdk/overview) `[read]`). Managed Agents has multi-agent orchestration on a shared filesystem, in public beta ([Anthropic](https://claude.com/blog/new-in-claude-managed-agents) `[search]`).
- **Tools.** Bash, file tools, web search and fetch, MCP, and custom tools your app runs ([Anthropic](https://platform.claude.com/docs/en/managed-agents/tools) `[read]`). Anthropic's advice: long tool descriptions, fewer consolidated tools, and namespaced names (same source `[read]`).
- **Skills.** Directories with a `SKILL.md`, loaded in three stages: about 100 tokens of metadata, under 5k of instructions, and resources on demand. They do not sync between surfaces. Anthropic says to treat them like installing software ([Anthropic](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview) `[read]`).
- **Memory and knowledge.** Memory is file-based, exportable, and has an audit log showing which agent and session wrote each item, in public beta ([Anthropic](https://claude.com/blog/claude-managed-agents-memory) `[search]`). Dreaming reorganises a memory store into a new store, in research preview ([Anthropic](https://platform.claude.com/docs/en/managed-agents/dreams) `[search]`).
- **Observability and evals.** Event history is stored server-side ([Anthropic](https://platform.claude.com/docs/en/managed-agents/overview) `[read]`). Outcomes uses a separate grader in its own context against a rubric, in public beta ([Anthropic](https://claude.com/blog/new-in-claude-managed-agents) `[search]`).
- **Human approval.** Three policies: `always_allow`, `always_ask` and `auto`. The agent toolset defaults to allow and MCP toolsets default to ask. A paused session waits indefinitely. Every tool event records how it was evaluated. Anthropic warns that `auto` is not a human checkpoint. Custom tools are outside these policies ([Anthropic](https://platform.claude.com/docs/en/managed-agents/permission-policies) `[read]`).
- **Security and governance.** Not eligible for zero data retention or HIPAA cover, because sessions are stateful ([Anthropic](https://platform.claude.com/docs/en/managed-agents/overview) `[read]`).
- **Deployment.** Anthropic-managed sandbox or self-hosted sandbox; scheduled deployments exist ([Anthropic](https://platform.claude.com/docs/en/managed-agents/overview) `[read]`).
- **Pricing.** Model tokens plus $0.08 per running session-hour. Idle time is not billed. Web search is $10 per 1,000. Sessions have no batch mode ([Anthropic](https://platform.claude.com/docs/en/about-claude/pricing) `[read]`). Reference model prices per million tokens, input and output: Opus 5.5 $4 and $20, Sonnet 5.5 $2 and $10, Haiku 5.5 $0.10 and $0.50 for prompts up to 100k tokens. Batch API gives 50% off (same source `[read]`).

### 3.3 Microsoft Copilot Studio and Agent 365

- **Architecture.** Three harnesses: GitHub Copilot (reasoning-heavy, sandboxed, files, skills, memory), Customer Conversation (voice and chat, multi-agent with escalation) and standard (rules, topics and flows) ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/harnesses-overview) `[read]`, dated 2026-10-19). Microsoft says the GitHub Copilot harness is not the GitHub Copilot service, and data does not go there (same source `[read]`).
- **How an agent is created.** Describe it in natural language (preview on the GitHub Copilot harness), or author topics and flows on the standard harness ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/agents-experience/overview) `[search]`).
- **Orchestration.** The planner chooses child and connected agents, tools and topics from their descriptions ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/advanced-generative-actions) `[search]`). Guidance prefers native orchestration inside and A2A across platforms ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/guidance/architecture/multi-agent-patterns) `[search]`).
- **Tools.** Connectors, knowledge, MCP and connected agents ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/harnesses-overview) `[read]`).
- **Memory and knowledge.** Skills and memory on two harnesses (same source `[read]`). Tenant graph grounding uses RAG over Microsoft Graph ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/requirements-messages-management) `[read]`).
- **Observability and evals.** Agent Evaluation is GA, with test sets by CSV, by hand or from production conversations, and can run from CI through REST ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/analytics-agent-evaluation-intro) `[search]`).
- **Human approval.** Agent flows include approval steps ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/flows-overview) `[search]`).
- **Security and governance.** Agent 365 covers observe, govern and secure through a registry, Entra, Purview and Defender ([Microsoft Learn](https://learn.microsoft.com/en-us/microsoft-agent-365/overview) `[read]`). Each agent has a directory identity ([Microsoft](https://techcommunity.microsoft.com/blog/microsoftmechanicsblog/microsoft-entra-agent-id-explained/4494408) `[search]`) and a named sponsor shown in the admin view ([Microsoft](https://techcommunity.microsoft.com/blog/microsoftmechanicsblog/agent-365--identity--access-controls-in-entra/4526815) `[read]`). An external security system can approve or block planned tool actions at runtime, in public preview. If it does not answer within one second, the action proceeds ([Microsoft](https://www.microsoft.com/en-us/copilot/blog/copilot-studio/strengthen-agent-security-with-near-real-time-protection-in-microsoft-copilot-studio/) `[read]`). Dot's own policy gate should fail closed instead `[judgement]`.
- **Deployment.** Publish to Teams, Microsoft 365 Copilot, websites and others. Admins can restrict channels ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/publication-fundamentals-publish-channels) `[search]`).
- **Pricing.** 25,000-credit pack at $200 per month, or $0.01 per credit pay-as-you-go ([June 2026 licensing guide](https://cdn-dynmedia-1.microsoft.com/is/content/microsoftcorp/microsoft/bade/documents/products-and-services/en-us/bizapps/Microsoft-Copilot-Studio-Licensing-Guide-June-2026-PUB.pdf) `[search]`). Standard harness rates: classic answer 1, generative answer 2, agent action 5, tenant graph grounding 10, 100 flow actions 13, premium reasoning 10 per 1,000 tokens ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/requirements-messages-management) `[read]`, dated 2026-10-30). Enforcement begins at 125% of prepaid capacity and stops agents mid-conversation (same source `[read]`). Agent 365 is $15 per user per month, E7 $99 ([Microsoft](https://www.microsoft.com/en-us/microsoft-agent-365) `[read]`).

### 3.4 Salesforce Agentforce

- **Architecture.** Agent Script compiles to an Agent Graph. Atlas walks the graph each turn, runs deterministic nodes as code and calls the LLM only at prompt nodes ([Salesforce](https://architect.salesforce.com/docs/architect/fundamentals/guide/hybrid-reasoning-agentforce-builder-agent-script.html) `[read]`).
- **How an agent is created.** Agentforce Builder, using subagents with `before_reasoning`, `reasoning` and `after_reasoning` blocks (same source `[read]`).
- **Orchestration.** Guard clauses and `available when` conditions control which actions the LLM can see (same source `[read]`).
- **Tools.** MCP tools can be added as actions, with limits: server tools only, no user-level authentication, simple response shapes, 60 second timeout ([Salesforce](https://help.salesforce.com/s/articleView?id=ai.agent_mcp_considerations.htm&amp=&language=en_US&amp=&type=5) `[search]`).
- **Memory and knowledge.** Session trace data sits in Data 360 ([Salesforce](https://www.salesforce.com/blog/agentforce-observability/) `[search]`). **Unverified:** long-term memory model.
- **Observability and evals.** Agentforce Observability (formerly Command Center, per [Salesforce](https://www.salesforce.com/blog/command-center/) `[search]`) traces each turn, including reasoning, LLM calls and guardrail checks, and is unmetered across Agentforce SKUs ([Salesforce](https://www.salesforce.com/blog/agentforce-observability/) `[read]`). Session tracing refreshes about every 30 minutes ([Salesforce](https://help.salesforce.com/s/articleView?id=005226932&language=en_US&type=1) `[search]`). Testing Center specs are stored in Git with the agent, and each case defines input, expected actions and plain-language pass or fail criteria ([Salesforce](https://www.salesforce.com/blog/agent-development-lifecycle-headless/) `[read]`).
- **Human approval.** **Unverified** beyond escalation paths.
- **Security and governance.** Deterministic-path actions pass the trust layer with audit logging ([Salesforce](https://architect.salesforce.com/docs/architect/fundamentals/guide/hybrid-reasoning-agentforce-builder-agent-script.html) `[read]`). URL allowlisting followed ForcedLeak ([The Hacker News](https://thehackernews.com/2025/09/salesforce-patches-critical-forcedleak.html) `[search]`).
- **Deployment.** Runs on the Salesforce platform, as the Atlas description above implies `[judgement]`.
- **Pricing.** Flex Credits $500 per 100k, 20 credits per action, 30 for voice; $2 per conversation; $5 user licence plus credits; $50 Coworker licence; $125 add-ons; $150 industry add-ons; Max Edition from $550 including 2.75M credits per org per year; Foundations free ([Salesforce](https://www.salesforce.com/agentforce/pricing/) `[read]`). A search summary claimed the $2 option is now legacy. The official page still lists it, so I treat that claim as **unverified**.

### 3.5 Google Gemini Enterprise Agent Platform (formerly Vertex AI Agent Builder)

- **Architecture.** Build (Agent Studio, ADK), Scale (Agent Runtime, Memory Bank), Govern (Agent Identity, Registry, Gateway), Optimize (Simulation, Evaluation, Observability) ([Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`, 2026-04-22). Future Vertex AI work goes through this platform (same source `[read]`).
- **How an agent is created.** Agent Studio (low-code, exportable to ADK), ADK (code) or Agent Garden templates (same source `[read]`).
- **Orchestration.** ADK is graph-based with sub-agent networks. Agents delegate with generative or deterministic patterns (same source `[read]`). Agent Registry supports A2A v1 ([Google](https://docs.cloud.google.com/gemini-enterprise-agent-platform/release-notes) `[read]`).
- **Tools.** MCP and connectors through Agent Gateway, with Model Armor ([Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`).
- **Memory and knowledge.** Memory Bank generates and curates long-term memories. Memory Profiles went GA on 2026-07-15 ([Google](https://docs.cloud.google.com/gemini-enterprise-agent-platform/release-notes) `[read]`).
- **Observability and evals.** Agent Observability GA 2026-06-18 with default-on tracing ([Google](https://docs.cloud.google.com/gemini-enterprise-agent-platform/release-notes) `[read]`). Evaluation scores live traffic and Simulation uses synthetic users ([Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`). **Unverified:** GA status of Simulation and Evaluation.
- **Human approval.** **Unverified.** I found no official description.
- **Security and governance.** Agent Gateway and Registry GA 2026-06-18; Agent Identity API in Preview from the same date ([Google](https://docs.cloud.google.com/gemini-enterprise-agent-platform/release-notes) `[read]`).
- **Deployment.** Agent Runtime supports multi-day runs ([Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`).
- **Pricing.** $0.085 per vCPU-hour, $0.009 per GiB-hour, $0.30 per GiB-month storage. Waiting between turns is not billed. Monthly free tier of 50 vCPU-hours, 100 GiB-hours and 1 GiB ([Google](https://cloud.google.com/products/gemini-enterprise-agent-platform/pricing) `[search]`). Session and memory compute metering began 2026-09-01 ([Google](https://docs.cloud.google.com/gemini-enterprise-agent-platform/release-notes) `[read]`).

### 3.6 AWS Bedrock Agents Classic and AgentCore

- **Status.** Bedrock Agents is now Agents Classic, in maintenance mode, with its model catalogue frozen at 2026-07-30. New accounts receive a 403. There is no end-of-life date ([AWS](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-classic-maintenance-mode.html) `[read]`).
- **Architecture.** AgentCore is a set of modular services: Harness, Runtime, Memory, Gateway, Identity, Code Interpreter, Browser, Observability, Payments, Evaluations, Optimization, Policy and Registry. Any framework and model ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/what-is-bedrock-agentcore.html) `[read]`).
- **How an agent is created.** Harness config (model, prompt, tools) by CLI, or deploy your own code to Runtime ([AWS](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-classic-maintenance-mode.html) `[read]`).
- **Orchestration.** Harness supports agent-as-tool only. AWS says full multi-agent collaboration needs custom framework code (same source `[read]`).
- **Tools.** Gateway turns APIs and Lambda into MCP tools and connects existing MCP servers ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/what-is-bedrock-agentcore.html) `[read]`).
- **Memory.** Short and long term, shareable between agents, scoped per actor (same sources `[read]`).
- **Observability and evals.** OpenTelemetry-compatible traces, Evaluations, and Optimization with A/B tests ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/what-is-bedrock-agentcore.html) `[read]`).
- **Human approval.** Inline function tools pause and return control to the client ([AWS](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-classic-maintenance-mode.html) `[read]`).
- **Security and governance.** Policy intercepts each tool call through Gateway, outside the agent. It uses Cedar or Dogwood, and supports temporal rules such as requiring prior approval, capping repeats, or keeping a budget running total within a session ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/policy.html) `[read]`). This is the strongest governance design I found, and Dot should copy the idea rather than the product `[judgement]`.
- **Deployment.** Serverless microVM sessions on AWS ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/what-is-bedrock-agentcore.html) `[read]`).
- **Pricing.** More than a dozen line items. Examples: Runtime v1 $0.0895 per vCPU-hour, Gateway $0.005 per 1,000 calls, long-term memory $0.75 per 1,000 records per month, Evaluations $0.0024 and $0.012 per 1,000 tokens, Policy $0.000025 per request ([AWS](https://aws.amazon.com/bedrock/agentcore/pricing/) `[read]`). Short-term memory moved from per-event to per-GB on 2026-10-06 (same source `[read]`).

### 3.7 LangGraph and LangSmith

- **Architecture.** LangGraph is a graph runtime with checkpointers for thread state and stores for long-term data ([LangChain](https://docs.langchain.com/oss/python/langgraph/durable-execution) `[read]`).
- **How an agent is created.** Code, or no-code through LangSmith Fleet ([LangChain](https://www.langchain.com/blog/introducing-langsmith-fleet) `[search]`).
- **Orchestration.** Graph of nodes and edges, supporting subgraphs (same doc `[read]`).
- **Durability.** Three modes: `exit`, `async` (default) and `sync` ([LangChain](https://reference.langchain.com/python/langgraph/types/Durability) `[search]`).
- **Human approval.** `interrupt()` pauses and saves state. On resume, the node restarts from its beginning, so side effects before the interrupt must be idempotent ([LangChain](https://docs.langchain.com/oss/python/langgraph/interrupts) `[read]`). Dot should expect the same issue in any resume design `[judgement]`.
- **Tools and memory.** MCP use was not verified on a primary page. Memory is through stores ([LangChain](https://docs.langchain.com/oss/python/langgraph/durable-execution) `[read]`).
- **Observability and evals.** LangSmith tracing, tuned evaluators, and an Engine feature priced per run ([LangChain](https://www.langchain.com/pricing) `[read]`).
- **Security and governance.** Fleet has permission levels (clone, run, edit), two credential models (shared service accounts and per-user OAuth), and a central inbox ([LangChain](https://docs.langchain.com/langsmith/fleet/access-and-oversight) `[search]`). Enterprise adds ABAC, RBAC, SSO and self-hosting ([LangChain](https://www.langchain.com/pricing) `[read]`).
- **Deployment.** Serverless, dedicated or self-hosted (same source `[read]`).
- **Pricing.** Developer $0 with 5k traces. Plus $39 per seat with 10k traces. 1 LSU equals $1. Runtime 0.0675 LSU per vCPU-hour. Tuned evaluators 0.015 LSU per run. Engine roughly 7 to 45 LSU per run. Fleet includes 7 or 37 LSUs per month. Base traces keep 14 days and extended keep 180 ([LangChain](https://www.langchain.com/pricing) `[read]`).

### 3.8 CrewAI

- **Architecture.** Crews coordinate agents through tasks. Flows coordinate steps with start, listen and router, with persistence ([CrewAI](https://docs.crewai.com/en/concepts/flows) `[read]`).
- **How an agent is created.** Python, or a visual editor with copilot ([CrewAI](https://www.crewai.com/pricing) `[read]`).
- **Orchestration.** Sequential, hierarchical or hybrid ([CrewAI](https://docs.crewai.com/en/concepts/flows) `[read]`).
- **Tools and memory.** Agents take tools, memory and knowledge (same source `[read]`).
- **Observability and evals.** `tracing=True` sends traces to the AMP platform after consent ([CrewAI](https://docs.crewai.com/edge/en/observability/tracing) `[search]`). `crewai train` blends human feedback into a working file ([CrewAI](https://docs.crewai.com/en/concepts/training) `[search]`).
- **Human approval.** `human_input=True` on tasks, `@human_feedback` in Flows ([CrewAI](https://docs.crewai.com/en/learn/human-feedback-in-flows) `[search]`).
- **Security and governance.** SSO, RBAC, workload identity, PII redaction and policies on Enterprise ([CrewAI](https://www.crewai.com/pricing) `[read]`).
- **Deployment.** CrewAI cloud, VPC or own infrastructure (same source `[read]`). **Unverified:** details of AMP deployment.
- **Pricing.** Free with 50 executions per month. Enterprise custom (same source `[read]`).

### 3.9 n8n AI agents

- **Architecture.** Workflow engine with an Agent node. One workflow execution runs the agent several times ([n8n](https://docs.n8n.io/advanced-ai/examples/understand-agents/) `[read]`).
- **How an agent is created.** Visual workflow, using the Agent node in a workflow (same source `[read]`).
- **Orchestration.** Agents can call other agents and tools (same source `[read]`).
- **Tools.** Workflow nodes as tools. **Unverified:** MCP client details.
- **Memory.** Tool calls are not stored in agent memory, an issue open since 2025-04-02 ([GitHub #14361](https://github.com/n8n-io/n8n/issues/14361) `[read]`).
- **Observability and evals.** Metric-based evaluations on Pro and Enterprise ([n8n](https://docs.n8n.io/advanced-ai/evaluations/metric-based-evaluations/) `[search]`). A Guardrails node screens input and output, and can sanitise ([n8n](https://n8n.io/workflows/11025-n8n-enterprise-ai-security-firewall-guardrails-for-secure-agents/) `[search]`).
- **Human approval.** Per-tool sign-off, with approval through Slack, Telegram, Gmail and other channels. Approvers see the `$fromAI()` parameters. Denied calls return to the agent ([n8n](https://docs.n8n.io/advanced-ai/human-in-the-loop-tools) `[search]`).
- **Security and governance.** Enterprise tier. **Unverified:** details.
- **Deployment.** Cloud, self-hosted Business, or free Community Edition ([n8n](https://n8n.io/pricing/) `[read]`).
- **Pricing (annual billing, EUR).** Starter €20 with 2.5K executions, Pro €50 with 10K, Business €667 self-hosted with 40K. One execution equals one whole workflow run regardless of steps (same source `[read]`).

### 3.10 Dify

- **Architecture.** All app types run on one workflow engine. Two main types (Workflow, Chatflow) and three basic types (Chatbot, Agent, Text Generator) ([Dify](https://docs.dify.ai/en/use-dify/getting-started/key-concepts) `[read]`).
- **How an agent is created and orchestrated.** A workflow of nodes that starts from a start node ([Dify](https://docs.dify.ai/en/use-dify/getting-started/key-concepts) `[read]`).
- **Tools.** Two-way MCP from v1.6.0: consume MCP servers and publish apps as MCP servers ([Dify](https://dify.ai/blog/v1-6-0-built-in-two-way-mcp-support) `[search]`).
- **Memory and knowledge.** Chatflow memory, plus a knowledge base ([Dify](https://docs.dify.ai/en/use-dify/getting-started/key-concepts) `[read]`).
- **Observability and evals.** Run IDs with logs (same source `[read]`). **Unverified:** evals.
- **Human approval.** Human Input node from v1.13.0 with custom buttons, delivered by web or email. Default pause limit 7 days ([Dify](https://github.com/langgenius/dify/releases/tag/1.13.0) `[search]`). Pausing required moving runs into Celery workers (same source `[search]`).
- **Security and governance.** SSO and SLAs on Enterprise ([Dify](https://dify.ai/pricing) `[read]`).
- **Deployment.** Cloud, or self-hosted Community, or Enterprise (same source `[read]`).
- **Pricing.** Sandbox free. Professional $590 per workspace per year with 5,000 message credits per month. Team $1,590 with 10,000 (same source `[read]`).

### 3.11 Zapier Agents, now AI by Zapier

- **Status.** Agents is moving into one AI step in the Zap editor. No shutdown date yet. Guide updated 2026-10-01 ([Zapier](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]`).
- **Architecture.** A step combining prompt, tools, trigger and permission to act, on Zapier's infrastructure ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`).
- **How an agent is created.** Add an AI step to a Zap (same source `[read]`).
- **Orchestration.** Agent-to-agent calls now use Sub-Zaps ([Zapier](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]`).
- **Tools and knowledge.** Up to 20 knowledge sources per step, and files up to 100MB. Some sources (HubSpot, Asana, Zendesk, Zoom, Jira) are planned for Q4 2026 ([Zapier](https://help.zapier.com/hc/en-us/articles/24569690575117-Add-your-own-data-to-an-agent) `[search]`, [guide](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]`).
- **Memory.** Not mentioned in the announcement ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`).
- **Observability.** Per-run task breakdown in Zap History (same source `[read]`).
- **Human approval.** Per-tool toggle, replacing all-or-nothing, but tools run without pausing by default after migration ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`, [help](https://help.zapier.com/hc/en-us/articles/45863491098893-Add-tools-to-your-AI-by-Zapier-step) `[search]`).
- **Security and governance.** Admin Control Center can block tool calling ([Zapier](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]`).
- **Deployment.** Runs on Zapier's infrastructure ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`).
- **Pricing.** Tasks, with model multipliers of 1, 3 or 5. New steps default to Premium at 5x ([Zapier](https://help.zapier.com/hc/en-us/articles/46425475442829-AI-by-Zapier-model-tier-pricing) `[search]`). Each run has a default limit of 75 tasks, adjustable to 500, and pauses when it reaches it ([Zapier](https://help.zapier.com/hc/en-us/articles/47402591569805-Migrating-from-Agents-to-AI-by-Zapier) `[read]`). The legacy Agents page shows 400 and 1,500 activities on Free and Pro ([Zapier](https://zapier.com/l/agents-pricing) `[read]`).

### 3.12 Relevance AI

- **Architecture.** Agents with tools, grouped into Workforces on a visual canvas ([Relevance](https://relevanceai.com/docs/get-started/core-concepts/agents) `[search]`).
- **How an agent is created.** No-code builder, with pre-built or custom tools (same source `[search]`).
- **Orchestration.** AI connections (described in natural language) or next-step connections that force a handover ([Relevance](https://relevanceai.com/docs/workforce/build-an-ai-workforce/add-agents) `[search]`).
- **Tools and memory.** 2,000+ integrations ([Relevance](https://relevanceai.com/pricing) `[read]`). Knowledge is RAG. Long-term memory by project and user ([Relevance](https://relevanceai.com/product) `[search]`).
- **Observability and evals.** Agent evaluations, A/B testing and analytics on Enterprise ([Relevance](https://relevanceai.com/pricing) `[read]`).
- **Human approval.** Per-edge modes: Auto Run, Approval Required, Let Agent Decide. Escalations go to a Task View ([Relevance](https://relevanceai.com/docs/build/workforces/workforce-features/approvals-and-escalations) `[search]`).
- **Security and governance.** SSO, RBAC and audit logs on Enterprise ([Relevance](https://relevanceai.com/pricing) `[read]`).
- **Deployment.** Vendor cloud. **Unverified:** self-hosting.
- **Pricing.** The marketing page shows only Enterprise ([Relevance](https://relevanceai.com/pricing) `[read]`). The docs page shows Pro $19 annual or $29 monthly with 2,500 Actions per month, Team $234 or $349 with 7,000, top-ups $80 per 1,000 Actions and $20 per 10,000 Vendor Credits, and the Free plan retired ([Relevance](https://relevanceai.com/docs/get-started/pricing) `[read]`). The docs page contradicts itself on Vendor Credit amounts (same source `[read]`).

---

## 4. Platform profiles: others worth tracking

| Platform | What it is | Notable design | Pricing | Evidence quality |
|---|---|---|---|---|
| **Lindy** | Assistant with memory, 40+ skills, 1,500+ integrations and MCP | External-impact actions wait for approval; read-only lookups do not ([Lindy](https://www.lindy.ai/pricing) `[read]`) | Team $29.99 for 3,000 credits; work sized 2 to 2,500 credits; pauses when credits run out instead of billing (same `[read]`) | Good on price, review picture mixed (section 6.2) |
| **Sierra** | Customer agent platform | Releases snapshot code, prompts, models and knowledge together ([Sierra](https://sierra.ai/blog/agent-development-life-cycle) `[search]`); changes stay in reviewable sessions until merged ([Sierra](https://sierra.ai/blog/redesigning-agent-studio-around-ghostwriter) `[search]`); required approvals on release ([Sierra](https://sierra.ai/blog/release-governance-guardrails-for-agents-at-scale) `[search]`) | Outcome-based, no public rate card ([eesel](https://www.eesel.ai/blog/sierra-ai-pricing) `[search]`). Third-party figures **unverified** | Vendor's own words only |
| **Decagon** | Customer agent platform | AOPs are plain-English instructions compiled to logic ([Decagon](https://decagon.ai/blog/a-unified-platform-for-conversational-ai) `[search]`). Loop: run simulations at scale, Watchtower does QA on the runs, update AOPs or knowledge, rerun to validate ([Decagon](https://decagon.ai/blog/decagon-simulations) `[read]`) | Per conversation or per resolution, no public rate card. Estimates vary widely and some come from competitors ([Fin](https://fin.ai/learn/decagon-ai-pricing) `[search]`) **unverified** | Vendor and competitor sources |
| **Gumloop** | Agents with triggers, schedules and API; available inside Claude through MCP ([Gumloop](https://docs.gumloop.com/) `[read]`) | Overage defaulted to unlimited in March 2026, then was reversed: Pro overage is now opt-in and capped at 1,000,000 credits, and Enterprise sets its own cap ([usagepricing](https://usagepricing.com/blueprint/gumloop) `[read]`) | Pro from $37 with 20,000 credits; free plan reportedly gone from July 2026 ([jetadmin](https://www.jetadmin.io/blog/gumloop-pricing/) `[search]`). Official page gave no readable content, so **unverified** | Competitor blogs |
| **Vellum** | Was an LLM dev platform, pivoted to a personal assistant | Personal Assistant Platform closed 2026-11-02 ([Vellum](https://www.vellum.ai/pricing) `[read]`). **Unverified:** whether the Agent Builder product line continues | $30 to $200 per month before closure (same `[read]`) | A warning about pivots, not a design source |
| **Flowise** | Open-source builder: Assistant, Chatflow, Agentflow ([Flowise](https://docs.flowiseai.com/) `[read]`) | Bought by Workday on 2025-08-14 for HR and finance agents ([Workday](https://newsroom.workday.com/2025-08-14-Workday-Acquires-Flowise,-Bringing-Powerful-AI-Agent-Builder-Capabilities-to-the-Workday-Platform) `[search]`) | Open source | 2026 CVEs include unauthenticated RCE before 3.1.0 ([exploit-intel](https://exploit-intel.com/vuln/CVE-2026-41268) `[search]`), check official advisories |
| **Mastra** | TypeScript framework, Apache 2.0 | Durable typed workflows and Observational Memory in open source ([Mastra](https://mastra.ai/pricing) `[read]`) | Starter $0, Teams $250 per month, metered events and CPU (same `[read]`). **Unverified:** suspend and resume, scorers | Good on price |
| **Temporal** | Durable execution engine, not an agent builder | Agent loop lives in a Workflow, model calls are Activities ([Temporal](https://docs.temporal.io/develop/python/integrations/openai-agents) `[search]`). Python integration GA 2026-03-23 ([Temporal](https://temporal.io/blog/announcing-openai-agents-sdk-integration) `[search]`) | From $50 per million actions, Business from $500 per month ([Temporal](https://temporal.io/pricing) `[read]`) | Useful as a pattern. Its workers are long-running, so it does not fit cPanel `[judgement]` |
| **DBOS** | Library that checkpoints steps to Postgres | One write per step, no separate server ([DBOS](https://docs.dbos.dev/architecture) `[search]`) | Not checked | Closest match to Dot's constraints |

### Direct rivals to the Digital Workforce idea

| Product | Position | Pricing | Source |
|---|---|---|---|
| Microsoft Agent 365 | Registry, Entra identity, sponsors, Purview, Defender | $15 per user per month | [Microsoft](https://www.microsoft.com/en-us/microsoft-agent-365) `[read]` |
| Workday Agent System of Record | Onboard, manage, monitor and offboard agents, including third-party ones | Not published. Workday is moving to seats plus Flex Credits | [Josh Bersin](https://joshbersin.com/2026/04/the-reinvention-of-workday-from-system-of-record-to-platform-of-agents/) `[search]` |
| ServiceNow AI Control Tower | Govern, monitor and shut down agents across systems, with cost and ROI dashboards | Metered in "assists". Reported as bundled, details conflict | [Substack](https://theaieconomy.substack.com/p/servicenow-ai-control-tower-knowledge-2026-enforcement) `[search]`, [CIO](https://www.cio.com/article/4169954/servicenows-ai-control-tower-offers-hazy-view-of-spend.html) `[read]` |
| LangSmith Fleet | Shared agents, permissions, inbox, Slack identity per agent | Included LSUs, then metered | [LangChain](https://www.langchain.com/blog/introducing-langsmith-fleet) `[search]`, [pricing](https://www.langchain.com/pricing) `[read]` |

### Suite-embedded rivals to the Connected Layer

These are the most comparable products to Dot's suite position, and the weakest evidence in this document. All come from third-party blogs.

| Product | Reported model | Source |
|---|---|---|
| Notion Custom Agents | $10 per 1,000 credits, pauses at zero | [Work Management Hub](https://workmanagementhub.com/notion-ai-2026-complete-guide/) `[search]` |
| Atlassian Rovo | 10 credits per request, 100 per Deep Research. Overage unbilled for now, consumption pricing promised with 90 days notice | [heimin](https://heimin.app/en/blog/credit-metered-ai-pricing-pm-tools) `[read]` |
| Asana AI Teammates | Sources conflict on price | [Rework](https://resources.rework.com/tools/ai-agents/best-ai-agents-for-project-management-2026) `[search]` |
| ClickUp Super Agents | **Unverified.** A search summary gave 100 to 300 credits per run, but the page I checked does not state this | [heimin](https://heimin.app/en/blog/credit-metered-ai-pricing-pm-tools) `[read]` |
| Zoho Zia Agents | Building is free, prepaid AI credits for use, has an MCP server | [Macha](https://www.getmacha.com/blog/zoho-desk-ai-implementation) `[search]`, [techgeum](https://www.techgeum.com/blog/zoho/zia-agents) `[search]` |
| Odoo AI | AI features sold on top of edition, build on version 18.3+ | [usecarly](https://www.usecarly.com/blog/odoo-ai/) `[search]` |

Zoho and Odoo are probably the closest commercial comparison for an SMB suite and deserve a separate deep look `[judgement]`.

---

## 5. Comparison at a glance

| Platform | Creation style | Durable pause | Human approval granularity | Policy outside the model | Evals built in | Hard cost cap | Status risk |
|---|---|---|---|---|---|---|---|
| OpenAI (SDK, Workspace) | Code, prompt | RunState | Per tool, per action | Partial | Closing, use Promptfoo | Unverified | High, products closing |
| Anthropic Managed Agents | API, config | Server session | Per toolset, per tool, auto | `auto` is server-side | Outcomes rubric | Unverified | Beta |
| Copilot Studio | Prompt, canvas | Yes on flows | Flow steps | Runtime hook, preview | GA | Monthly limit, excludes flows | Fast-changing licensing |
| Agentforce | Builder, script | Unverified | Unverified | Agent Script | Testing Center | Credits | Weak adoption reports |
| Google | Studio, ADK | Agent Runtime | Unverified | Gateway, Model Armor | Evaluation, Simulation | Unverified | Recent rename |
| AWS AgentCore | CLI, code | Inline function return | Client handled | Yes, Cedar or Dogwood | Yes | Temporal policy can track totals | Stable, complex |
| LangGraph + LangSmith | Code, Fleet | Checkpoints + interrupt | Per tool | Your code | Yes | Fleet pauses on Developer plan | Churn reports |
| CrewAI | Code, Studio | Flows persist | Task level | Enterprise hooks | Training, tracing | Unverified | Less mature on durability per one review |
| n8n | Canvas | Yes | Per tool | Guardrails node | Pro and above | Unverified | Memory gap open |
| Dify | Canvas | From v1.13 | Node | Unverified | Unverified | Credits | Retrieval reports |
| Zapier | Zap step | Yes | Per tool, off by default | Admin block only | No | Yes, per run | Mid-migration |
| Relevance AI | Canvas | Unverified | Per edge | Unverified | Enterprise | Unverified | Plans shifting |

Cells are drawn from section 3 and 4 sources. "Unverified" means I found no clear statement, not that the feature is absent.

---

## 6. The brief's six questions

### 6.1 What they do exceptionally well

- **AWS: policy outside the agent.** Deterministic, audited, default control at the tool boundary, with session-aware rules ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/policy.html) `[read]`).
- **Salesforce: hybrid logic.** Keep logic in code by default, use the LLM only where judgement is needed ([Salesforce](https://architect.salesforce.com/docs/architect/fundamentals/guide/hybrid-reasoning-agentforce-builder-agent-script.html) `[read]`).
- **Sierra and Decagon: release engineering.** Atomic snapshots, simulation gates, production findings turned into fixes ([Sierra](https://sierra.ai/blog/agent-development-life-cycle) `[search]`, [Decagon](https://decagon.ai/blog/decagon-simulations) `[read]`).
- **Microsoft and Google: agent identity.** A named identity, a sponsor, and a registry ([Microsoft](https://techcommunity.microsoft.com/blog/microsoftmechanicsblog/agent-365--identity--access-controls-in-entra/4526815) `[read]`, [Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`).
- **LangGraph, OpenAI and Anthropic: durable pause.** Runs survive waiting for a person ([LangChain](https://docs.langchain.com/oss/python/langgraph/interrupts) `[read]`, [OpenAI](https://openai.github.io/openai-agents-python/human_in_the_loop/) `[read]`, [Anthropic](https://platform.claude.com/docs/en/managed-agents/permission-policies) `[read]`).
- **Anthropic: progressive disclosure.** Skills cost almost no context until used ([Anthropic](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview) `[read]`).
- **Salesforce: depth of trace.** Turn-level view of reasoning and guardrails ([Salesforce](https://www.salesforce.com/blog/agentforce-observability/) `[read]`).
- **Zapier, n8n, Lindy: reach and ease.** Large connector catalogues and simple starts. Zapier's per-run cap and per-tool approval are the simplest good controls in the market ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`).
- **Lindy: failure mode on budget.** Pause and notify rather than bill ([Lindy](https://www.lindy.ai/pricing) `[read]`). Gumloop moved the same way after trying unlimited overage as a default ([usagepricing](https://usagepricing.com/blueprint/gumloop) `[read]`).

### 6.2 What users find difficult

Each line names where the complaint comes from. Individual reports are anecdotes unless stated.

**Unpredictable cost**

- **Agentforce.** Commentary reports confusion over what a conversation or an action counts as, and charges for unresolved sessions ([Monetizely, commentary](https://www.getmonetizely.com/blogs/the-doomed-evolution-of-salesforces-agentforce-pricing) `[read]`). A December 2025 article said pricing was still opaque for many customers and that buyers wanted caps and predictable ROI, while also saying Flex Credits had reduced the barrier ([Salesforce Ben](https://www.salesforceben.com/where-are-we-really-at-with-agentforce-adoption/) `[read]`).
- **ServiceNow.** Analysts told CIO that consumption per interaction varies, so bills are hard to predict, and retry loops can burn credits ([CIO](https://www.cio.com/article/4169954/servicenows-ai-control-tower-offers-hazy-view-of-spend.html) `[read]`).
- **Copilot Studio.** A licensing specialist says there is no single dashboard for credit consumption ([SAMexpert](https://samexpert.com/copilot-studio-licensing-guide/) `[search]`). Microsoft's own example of one follow-up message on a reasoning model is 141 credits, or 461 in a new conversation ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/requirements-messages-management) `[read]`). At $0.01 per credit that is $1.41 to $4.61 before feature rates (my arithmetic).
- **GitHub Copilot, adjacent product.** Developers complained on forums about depleted credits after usage billing ([Visual Studio Magazine](https://visualstudiomagazine.com/articles/2026/08/07/copilot-credit-complaints-keep-coming-too-expensive-to-use.aspx?admgarea=ALM) `[search]`). This is a different product and I include it only because it is the same user reaction.
- **Lindy.** Trustpilot on 2026-11-06 shows 3.3 from 61 reviews, 52% one-star, with billing, cancellation, usability and credit spend on fixing errors among the themes ([Trustpilot](https://www.trustpilot.com/review/lindy.ai) `[read]`). One review site quotes G2 at 4.9 from 171 reviews and Trustpilot at 2.7 from 47, with credits draining during failed loops ([Crevio](https://crevio.co/blog/lindy-ai-review) `[read]`). Reviews are clearly split.
- **Relevance AI.** A competitor's page quotes G2 at 4.3 from 20 reviews, checked 2026-09-19, and lists cost on multi-step agents, limited native connections and tool-builder changes as complaints from its review sources ([Macha](https://www.getmacha.com/blog/best-relevance-ai-alternatives) `[read]`).
- **n8n.** Forum users object to self-hosted Business limits and price ([n8n forum](https://community.n8n.io/t/new-plan-no-active-workflow-limits-introducing-n8n-new-pricing/163840?page=2) `[search]`), and an October 2026 thread reports AI Assistant credit spikes ([n8n forum](https://community.n8n.io/t/ai-assistant-usage-costs-spike-concerns/321817?tl=en) `[search]`).
- **LangSmith.** A Hacker News commenter objected to paying for seats, traces and retention ([HN](https://news.ycombinator.com/item?id=44840323) `[search]`).

**Reliability and routing**

- **Copilot Studio.** Microsoft Q&A threads report topics interrupting each other and correct tool results being replaced by generic answers ([Q&A](https://learn.microsoft.com/en-us/answers/questions/5620653/copilot-studio-generative-nodes-jumping-between-to) `[search]`, [Q&A](https://learn.microsoft.com/en-us/answers/questions/5903646/inconsistent-output-behavior-in-generative-ai-acti) `[search]`). Some replies there are AI generated.
- **OpenAI Agent Builder.** The developer forum reported tool calling failing while tool listing worked, generic errors, and one-shot flows with no follow-up questions ([OpenAI forum](https://community.openai.com/t/how-to-get-started-with-agent-builder/1361280) `[read]`). Also no OAuth for MCP servers at the time ([forum](https://community.openai.com/t/mcp-oauth-support-in-agent-builder/1361921) `[search]`).
- **n8n.** Agents lose their tool history in memory, and users report agents claiming actions they never took. The issue has been open since 2025-04-02 ([GitHub](https://github.com/n8n-io/n8n/issues/14361) `[read]`).
- **Loops.** CrewAI GitHub reported tasks taking many more LLM calls after a dependency change ([GitHub #1355](https://github.com/crewAIInc/crewAI/issues/1355) `[search]`). A developer reported a bill rising from $80 to $400 in a day from 47 repeat tool calls ([DEV](https://dev.to/hidai25/my-ai-agent-cost-me-400-overnight-so-i-built-pytest-for-agents-and-open-sourced-it-492c) `[search]`).
- **Dify retrieval.** GitHub reports include quality falling over time, traced by the reporter to shared chunk IDs and closed as not planned ([GitHub #37750](https://github.com/langgenius/dify/issues/37750) `[read]`), and different result counts in a workflow and in retrieval testing ([GitHub #13622](https://github.com/langgenius/dify/issues/13622) `[search]`).

**Abstraction and debugging**

- **LangChain and LangGraph.** Forum users report opaque stack traces and breaking changes ([Latenode community](https://community.latenode.com/t/what-are-the-current-limitations-of-using-langchain-and-langgraph-frameworks/39038) `[search]`). Older HN threads argue for plain API calls and loops ([HN 2024](https://news.ycombinator.com/item?id=40739982) `[search]`). Others defend LangGraph as a sound low-level tool ([GitHub discussion](https://github.com/orgs/community/discussions/182015) `[search]`).
- **Google.** GitHub and forum reports cover serialisation failures for multi-agent setups and streaming problems ([ADK #1090](https://github.com/google/adk-python/issues/1090) `[search]`, [Google forum](https://discuss.google.dev/t/adk-agents-cloud-run-deployment-session-on-vertexai-agent-engine-issue/331376) `[search]`).
- **AWS.** One developer spent about 45 minutes on IAM permissions during setup, and a preview review noted missing IaC, though it is old ([startmit](https://startmit.com/amazon-bedrock-agentcore-review-is-multi-agent-memory-worth-the-hype/) `[read]`, [Rackspace](https://www.rackspace.com/blog/amazon-bedrock-agentcore-preview-agentic-ai-platform) `[search]`).

**Adoption stalls**

- KeyBanc said in July 2026 that customer checks were weak, that customer data is not ready, and that the product is not there yet. Salesforce had not replied at publication ([CIO](https://www.cio.com/article/4198127/salesforces-agentforce-product-maturity-questioned-as-keybanc-cites-weak-customer-traction.html) `[read]`).
- Oppenheimer's interviews at Dreamforce found 85% had tested or deployed, with nearly half still in pilots ([Salesforce Ben](https://www.salesforceben.com/85-of-customers-have-tried-agentforce-but-demand-hasnt-picked-up-why/) `[read]`).
- Gartner predicted in June 2025 that over 40% of agentic projects will be cancelled by the end of 2027 ([MarTech](https://martech.org/gartner-40-of-agentic-ai-projects-will-fail-making-humans-indispensable/) `[search]`). The 2026 adoption figures I saw ranged widely, apparently because surveys define deployed differently `[judgement]`, so I do not quote one.

**Security of the builders themselves**

- Flowise had several critical and high CVEs in 2026 ([stack.watch](https://stack.watch/product/flowiseai/flowise/) `[search]`). Check official advisories before citing.

### 6.3 Where products create unnecessary complexity

- **Too many meters.** AgentCore has over a dozen line items ([AWS](https://aws.amazon.com/bedrock/agentcore/pricing/) `[read]`). LangSmith combines seats, traces, retention tiers, LSUs, Engine, Fleet, Sandboxes and Gateway credits ([LangChain](https://www.langchain.com/pricing) `[read]`). Copilot Studio combines feature rates with premium token rates, and its limits differ for agents and flows ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/requirements-messages-management) `[read]`).
- **Limits that do not cover what buyers think.** A monthly limit on a Copilot Studio agent does not stop the agent flows it calls (same source `[read]`).
- **Several ways to build the same thing.** Copilot Studio has three harnesses plus agent flows ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/harnesses-overview) `[read]`). Flowise has three builders ([Flowise](https://docs.flowiseai.com/) `[read]`). Dify has five app types ([Dify](https://docs.dify.ai/en/use-dify/getting-started/key-concepts) `[read]`). Anthropic has four routes ([Anthropic](https://code.claude.com/docs/en/agent-sdk/overview) `[read]`).
- **Agent and workflow as separate products.** Zapier folding its agent into one workflow step suggests the split was not worth keeping ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`).
- **Governance as a stack of purchases.** Agent 365 spans the admin centre, Entra, Purview and Defender, and works best with E5 ([Microsoft Learn](https://learn.microsoft.com/en-us/microsoft-agent-365/overview) `[read]`).
- **Multi-agent as a default.** Google's results show this hurts sequential work ([Google Research](https://research.google/blog/towards-a-science-of-scaling-agent-systems-when-and-why-agent-systems-work/) `[read]`). AWS's own harness offers only agent-as-tool ([AWS](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-classic-maintenance-mode.html) `[read]`).
- **Protocols ahead of need.** MCP, A2A, AP2, x402 and MPP all appear in platform docs ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/what-is-bedrock-agentcore.html) `[read]`, [Google](https://cloud.google.com/blog/products/ai-machine-learning/introducing-gemini-enterprise-agent-platform) `[read]`). A closed first-party suite needs none of them on day one `[judgement]`.
- **Canvases for non-developers.** Graph editors expose engineering ideas to business users. Sierra and Copilot Studio are moving to describe-it-and-review builders ([Sierra](https://sierra.ai/blog/redesigning-agent-studio-around-ghostwriter) `[search]`, [Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/agents-experience/overview) `[search]`). Interpretation is `[judgement]`.

### 6.4 Which capabilities are genuinely useful to businesses

Ranked by how much evidence supports them `[judgement]`. Vendor examples are sourced in sections 3 and 4.

1. **Narrow, single-turn workflow tasks.** Top agents passed 83% on workflow execution in single-turn tests, against 35% overall in multi-turn ([arXiv](https://arxiv.org/html/2505.18878v1) `[search]`).
2. **Reading, summarising and preparing work for a person.** Only 0.8% of observed API actions looked irreversible, and most agent activity is low risk ([Anthropic](https://www.anthropic.com/research/measuring-agent-autonomy) `[read]`).
3. **Scheduled and event-triggered runs.** Offered by Workspace Agents, Managed Agents, Fleet, Lindy and Zapier.
4. **Approval on consequential actions only.** Offered per tool by Zapier, n8n, Workspace Agents and Laravel's SDK.
5. **Knowledge with citations.** Dify's issues show how much retrieval quality affects trust.
6. **Hand-off to a person with context.** Copilot Studio's customer harness and Relevance AI's escalations.
7. **Run-level budget and loop caps.** Zapier says this answered its users' runaway concerns ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[search]`).
8. **Named ownership and audit.** Sponsors in Entra, audit logs on most enterprise tiers.
9. **Regression tests from real failures.** Anthropic, Sierra, Decagon and Microsoft all converge here.

Less proven for this market: free-form agent swarms, agent-to-agent protocols, agent payments and long multi-day autonomous runs `[judgement]`.

### 6.5 What remains underserved

None of these is a proven gap. I looked and did not find them, which is different from showing they do not exist `[judgement]`.

- **Governance for small businesses.** The governance products found are sold to enterprises at seat or quote pricing.
- **An agent's performance history as an operating record.** Workday and Microsoft manage lifecycle and identity. I did not find a public product showing one record of quality, cost, approvals, overrides and incidents for an agent over time.
- **Autonomy earned from evidence.** Controls are toggles. Academics propose autonomy cases and certificates ([Knight Institute](https://knightcolumbia.org/content/levels-of-autonomy-for-ai-agents-1) `[read]`), but I found no shipped business product that promotes or demotes autonomy from measured results.
- **Approvals that show business consequence.** Approval screens usually show parameters, as in n8n ([n8n](https://docs.n8n.io/advanced-ai/human-in-the-loop-tools) `[search]`). Practitioner guidance asks for user-terms effects, and I found no validated product pattern ([ai-tldr](https://ai-tldr.dev/learn/building-ai-apps/ai-ux-patterns/human-in-the-loop-ux/) `[search]`).
- **Permission inheritance across a suite.** Salesforce's MCP cannot do user-level authentication yet ([Salesforce](https://help.salesforce.com/s/articleView?id=ai.agent_mcp_considerations.htm&amp=&language=en_US&amp=&type=5) `[search]`). Dot owns each platform's policy layer, so it can apply one rule set.
- **Memory governance.** Research finds current injection defences miss memory poisoning ([ICML 2026](https://icml.cc/virtual/2026/67964) `[search]`). Anthropic's memory audit log is the best control I found ([Anthropic](https://claude.com/blog/claude-managed-agents-memory) `[search]`).
- **Regression testing for non-engineers.** Microsoft's CSV test sets come closest ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/analytics-agent-evaluation-intro) `[search]`).
- **South African fit.** No regulator guidance on automated decisions existed as at 2026-03-05 ([ITLawCo](https://itlawco.com/information-regulator-2026-27-annual-performance-plan/) `[read]`), and no in-country inference route was confirmed (section 8).

### 6.6 What Dot.Agents can do more coherently

Dot has one advantage the big platforms lack: it owns the applications, the data model, the policy layer and the suite identity for a small-business buyer. All items below are recommendations `[judgement]`.

**Digital Workforce**

- **An employment record for each agent.** Role, owner, sponsor, authorised platforms, action classes, autonomy per class, version, start date, performance, incidents, cost, retirement. Consider showing agents in Dot.HR, clearly labelled as agents.
- **Probation by default.** New agents start at draft level. Autonomy is raised per action class on measured evidence (section 7.4).
- **Two identity modes, chosen explicitly.** On behalf of a named user, or as a service agent owned by a named person. LangSmith Fleet and Workspace Agents both separate these ([LangChain](https://www.langchain.com/blog/introducing-langsmith-fleet) `[search]`, [VentureBeat](https://venturebeat.com/orchestration/openai-unveils-workspace-agents-a-successor-to-custom-gpts-for-enterprises-that-can-plug-directly-into-slack-salesforce-and-more) `[read]`). Effective permission is the intersection of the agent's scope and the delegating user's rights.
- **Performance reviews from real data.** Task outcomes, approval and edit rates, overrides, incidents, cost per outcome, eval pass rate.
- **One agent per job.** Avoid agent org charts as a feature. Use an orchestrator only for parallel reads, which is where Google found the gain.

**Agent Command Centre**

- **One inbox for everything needing a person.** Approvals, escalations, failures, budget stops and policy denials, modelled on Fleet's inbox ([LangChain](https://www.langchain.com/blog/introducing-langsmith-fleet) `[search]`).
- **Plain-language run history.** A timeline of what happened, built from the journal, with the trace one click below.
- **Kill switches at three levels.** Agent, platform connection and tenant. OWASP lists kill switches for rogue agents ([Kratikal summary](https://kratikal.com/blog/top-ten-owasp-risk-for-agentic-applications/) `[search]`).
- **Cost beside the work.** Rand cost on every run and every agent, not on a separate billing page.

**Connected Business Intelligence Layer**

- **Typed first-party tools with a manifest.** Each tool declares platform, read or write, external reach, data sensitivity, reversibility, dry-run support and an idempotency requirement.
- **Trust labels on every piece of data.** Known at source, because Dot owns the source (section 7.3).
- **Consequence previews from the owning platform.** Finance computes the invoice effect, HR computes who is affected, Sheets returns the cell diff. Only a suite owner can do this cheaply.
- **Permission-aware retrieval.** Search runs through each platform's own policy at query time.
- **Own your prompts, evals and traces.** OpenAI's shutdown of hosted evals and prompt objects shows why ([OpenAI](https://developers.openai.com/api/docs/deprecations) `[read]`). Keep them in Dot's database, model-neutral.

---

## 7. Technical recommendations

### 7.1 Durable execution on a shared cPanel host

**Verdict: a journalled step machine in the database, one step per queue job, driven by cron. Do not adopt Temporal. Do not start with a replay engine.** `[judgement]`

Facts that shape the design:

- `queue:work` supports `--stop-when-empty`, `--max-time`, `--max-jobs` and `--once` ([Laravel](https://laravel.com/docs/13.x/queues) `[read]`).
- `--timeout` must be at least several seconds shorter than `retry_after`, or a job can run twice (same source `[read]`).
- Timeouts need the PCNTL extension, and `--timeout` has no effect with `--once` (same source `[read]`). Check `php -m` on the host's CLI. **Unverified** for Dot's host.
- `--max-time` is checked by `stopIfNecessary` between jobs, so a worker can overrun by the length of the current job (`vendor/laravel/framework/src/Illuminate/Queue/Worker.php` `[local]`).
- The database queue uses `FOR UPDATE SKIP LOCKED` on MySQL 8.0.1+, MariaDB 10.6+ and Postgres 9.5+. There is no equivalent on SQLite, so plan for one worker there (`getLockForPopping` in `vendor/laravel/framework/src/Illuminate/Queue/DatabaseQueue.php` `[local]`).
- Dot.Agents' `DB_QUEUE_RETRY_AFTER` defaults to 90 seconds `[local]`. Check the production value.
- A durable engine records results, but it cannot guarantee an external write happened once. A crash between the call and the record can duplicate it ([Particula](https://particula.tech/blog/durable-execution-ai-agents-temporal-inngest-restate) `[read]`). OpenAI says approval does not guarantee exactly-once either, and advises idempotency keys and reconciling uncertain outcomes ([OpenAI](https://openai.github.io/openai-agents-python/human_in_the_loop/) `[read]`).
- DBOS gets exactly-once only for steps that are database transactions, by committing the step and its checkpoint together ([DBOS](https://www.dbos.dev/blog/why-postgres-durable-execution) `[search]`).
- Temporal replays worker code against an event history ([comparison](https://wetheflywheel.com/en/comparisons/temporal-vs-inngest/) `[read]`), which means workflow code must be deterministic `[judgement]`. DBOS instead resumes from stored step results ([DBOS](https://docs.dbos.dev/architecture) `[search]`), which suits Dot better `[judgement]`.

**Design**

Tables (names are suggestions):

- `agent_runs`: state, agent version, tenant, owner, budget, `taint_level`, `leased_until`.
- `agent_steps`: an append-only journal. Sequence, kind (model, tool, approval, policy), request hash, result, usage, cost, timestamps.
- `agent_effects`: one row per side effect. Idempotency key, tool, canonical arguments, status (`planned`, `approved`, `executing`, `done`, `failed`, `unknown`), response, undo descriptor.
- `agent_approvals`: frozen preview, preview hash, approver, decision, expiry.

Loop:

1. `RunAgentStep` is a queued job that performs **exactly one** model call or **one** effect.
2. It takes a lease on the run, reads the journal, does one thing, and commits the result and the next state in one transaction.
3. It dispatches the next `RunAgentStep`, ideally after commit. The transactional outbox gives the same safety, with at-least-once delivery and idempotent consumers ([microservices.io](https://microservices.io/patterns/data/transactional-outbox.html) `[read]`).
4. Waiting for a person means no job exists. The run sits in `awaiting_approval`. The approval action dispatches the next step. This costs nothing while waiting. Anthropic and Google similarly do not bill idle time ([Anthropic](https://platform.claude.com/docs/en/about-claude/pricing) `[read]`, [Google](https://cloud.google.com/products/gemini-enterprise-agent-platform/pricing) `[search]`).
5. A sweeper in `schedule:run` finds expired leases and re-dispatches. It finds `unknown` effects and reconciles them before any retry.

Effect protocol:

1. Plan: write the row with key `hash(run_id, step_no, tool, canonical_args)`.
2. Gate: policy and approval.
3. Execute: call the Dot platform with an `Idempotency-Key` header.
4. Record: `done` with the response. On timeout set `unknown`.
5. Reconcile: look the key up on the target platform, then decide.

Each Dot platform needs an idempotency layer that behaves like Stripe's: save the first response, return it on retry, and error if parameters differ ([Stripe](https://docs.stripe.com/api/idempotent_requests) `[read]`). Keep keys longer than the longest approval window, not just 24 hours `[judgement]`.

Starting configuration, to be tuned:

```text
# Separate connection so other Dot jobs keep their own retry_after
'agents' connection: driver=database, retry_after=240

# Model call HTTP timeout: 120s
# Job #[Timeout(180)], #[Tries(3)], ThrottlesExceptions for provider 429s

* * * * * flock -n /tmp/dot-agents-steps.lock php artisan queue:work agents \
    --queue=agent-approved,agent-steps --stop-when-empty --max-time=50 --timeout=180
```

Notes on that configuration `[judgement]`:

- `flock -n` stops overlap, since a worker may outlive the minute. Use two lock files for two slots, only on a database with `SKIP LOCKED`.
- Keep this separate from the ten-queue drain so a slow model call does not block mail or billing.
- All Dot apps share one crontab, and concurrent deploys wiped it on 2026-10-16. Use the existing read-retry and flock approach for any crontab change (recorded project note).
- Add `--max-jobs` or a per-tenant step limit so one long run cannot take every slot.
- Keep a per-run time and step budget. Long jobs can be split by design because each tick does one step.
- For overnight work, the Anthropic Batch API is 50% cheaper and fits a submit-and-poll cron ([Anthropic](https://platform.claude.com/docs/en/about-claude/pricing) `[read]`).
- Shared-host limits such as CPU, memory and process counts were not checked. Confirm with the host.

Prompt caching needs thought because steps resume later. Keep a stable prefix and use a 1-hour cache for slower runs. Laravel's SDK supports a `'1h'` TTL on Anthropic and warns that changing prefixes waste writes ([Laravel](https://laravel.com/docs/13.x/ai-sdk) `[read]`).

Failure table:

| Failure | What happens | Recovery |
|---|---|---|
| Worker dies during model call | Lease expires | Step re-runs. Cost is wasted but no harm, and it is logged |
| Worker dies after commit, before dispatch | Run idle with expired lease | Sweeper dispatches |
| Tool call times out | Effect goes to `unknown` | Reconcile by idempotency key, never blind retry |
| Provider rate limit | Exception | `ThrottlesExceptions`, then failover provider |
| Approval never answered | Reminder, then expiry | Run parks with reason, visible in the inbox |
| Budget exceeded | Pre-step check | Park the run cleanly, never mid-effect |
| Code deploy during a run | Step versions differ | Store version on the run. OpenAI advises the same ([OpenAI](https://openai.github.io/openai-agents-python/human_in_the_loop/) `[read]`) |
| Worker crash loops | Attempts exhausted | `MaxExceptions`, `CountCrashesAsExceptions`, then fail with an inbox item ([Laravel](https://laravel.com/docs/13.x/queues) `[read]`) |

**Libraries**

- **Laravel AI SDK (`laravel/ai`).** Has agents, tools, sub-agents, failover, structured output, `Stop` middleware, `Agent::fake`, usage by token class, and human tool approval that resumes from stored conversation history ([Laravel](https://laravel.com/docs/13.x/ai-sdk) `[read]`). Watch points from those docs: `continue` does not check ownership, and sub-agents do not support approval, so an approval-needing tool must sit on the top-level agent. Its `composer.json` on the `1.x` branch requires PHP `^8.3` and Illuminate `^12.0|^13.0`, so it does not force Laravel 13 ([GitHub](https://github.com/laravel/ai) checked through the GitHub API `[read]`). Dot.Agents declares PHP `^8.2` and uses Prism `[local]`. Decide between them deliberately. I would use it, or Prism, as the single-step model client only, and keep the run loop in Dot's journal, because the owner needs control of pause, cost and policy `[judgement]`.
- **Durable Workflow (formerly Laravel Workflow).** Provides activities, timers, signals, sagas and child workflows on Laravel queues ([GitHub](https://github.com/laravel-workflow/laravel-workflow) `[search]`). Reported issue: terminating runs with many open timers can exceed a 30 second PHP limit ([#958](https://github.com/durable-workflow/workflow/issues/958) `[search]`). Worth a spike later for sagas. An agent's path is data, not code, so a journal is simpler than replay `[judgement]`.
- **Hosted harness (Managed Agents, AgentCore).** Removes the durability problem, but Managed Agents is beta and has no zero data retention ([Anthropic](https://platform.claude.com/docs/en/managed-agents/overview) `[read]`). Keep as an option behind a driver for heavy file or code tasks that PHP cannot do `[judgement]`.

### 7.2 Eval and regression patterns that are practical

Core method, from Anthropic ([Anthropic](https://www.anthropic.com/engineering/demystifying-evals-for-ai-agents) `[read]`):

- Terms: task, trial, grader, transcript, outcome.
- Start with 20 to 50 tasks taken from real failures.
- Include cases where a behaviour should and should not happen.
- Grade the outcome, not the exact path.
- Separate capability evals (low pass rate at first) from regression evals (near 100%), and graduate tasks between them.
- Read transcripts, because some failures are grader mistakes.
- Layer evals, monitoring, user feedback and manual review.

Hamel Husain's method adds error analysis first, reading real traces and writing notes before writing evals, with binary pass or fail judges validated against human labels ([chatprd](https://chatprd.ai/how-i-ai/hamel-husains-guide-to-ai-evals-with-error-analysis) `[search]`).

For Dot `[judgement]`:

1. **Three layers in CI and on schedule.**
   - Harness tests on every PR with faked models (`Agent::fake`, or Prism's fake): policy, budget, approval, resume and taint logic. No tokens spent.
   - Golden regression suite nightly against live models, with at least 3 trials per task because output varies.
   - Adversarial suite weekly (section 7.3).
2. **State-based graders first.** Run against seeded Dot data, using the Demo seeders, and assert database outcome. These are cheap and reproducible.
3. **Every incident becomes a test.** A denied approval, an edited draft, or a user report becomes a one-click eval candidate. This follows Decagon's loop of test, review, fix and validate ([Decagon](https://decagon.ai/blog/decagon-simulations) `[read]`).
4. **Versioned snapshots.** Prompt, model, tools and knowledge form one numbered version, as Sierra does ([Sierra](https://sierra.ai/blog/agent-development-life-cycle) `[search]`). Any change re-runs regression before it reaches a customer.
5. **Non-engineer test sets.** A plain table of input and expected result, like Microsoft's CSV sets ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/analytics-agent-evaluation-intro) `[search]`).
6. **Report pass rate with a floor.** Track failures per task, not just averages. Commentary on Anthropic's post warns that passing once in k tries overstates stability, so prefer all k passing for regression ([Shkurti](https://idlirshkurti.github.io/tutorials/agent_evals.html) `[read]`).
7. **Use Promptfoo optionally.** OpenAI recommends it and says it stays open source ([TNW](https://thenextweb.com/news/openai-acquires-promptfoo-ai-security-frontier) `[search]`). Keep the test data in Dot's format so the tool can be swapped.

### 7.3 Prompt-injection defences with evidence behind them

What the evidence says:

| Claim | Evidence | Source |
|---|---|---|
| Not solved | OpenAI says it is unlikely ever to be fully solved. UK NCSC says it may never be fully mitigated | [The Decoder](https://the-decoder.com/openai-admits-prompt-injection-may-never-be-fully-solved-casting-doubt-on-the-agentic-ai-vision/) `[search]`, [CyberScoop](https://cyberscoop.com/openai-chatgpt-atlas-prompt-injection-browser-agent-security-update-head-of-preparedness/) `[read]` |
| Model hardening helps but is not a boundary | Anthropic reported 1% attack success on Opus 4.5 against an adaptive attacker and said that is still meaningful risk | [Anthropic](https://www.anthropic.com/news/prompt-injection-defenses) `[search]` |
| Classifiers can be bypassed | EchoLeak got past Microsoft's XPIA classifier, link redaction and CSP, CVSS 9.3 | [arXiv](https://arxiv.org/abs/2509.10540) `[search]` |
| Classifiers miss things even when tuned | Anthropic's auto mode reports 17% false negatives on 52 real overeager actions and 5.7% on 1,000 synthetic exfiltration cases | [Anthropic](https://www.anthropic.com/engineering/claude-code-auto-mode) `[read]` |
| Architecture gives guarantees at a utility cost | CaMeL: 77% against 84% tasks with provable security. On Claude 3.5 Sonnet, zero attacks across 949 cases, against 8 for the next defence | [arXiv](https://arxiv.org/abs/2503.18813) `[read]`, [third-party summary](https://bhaskarjitsarmah.github.io/visual-summary/33-camel-prompt-injection/) `[read]` |
| Avoid the combination | Lethal trifecta: private data, untrusted content, external communication | [Willison](https://simonwillison.net/2025/Jun/16/the-lethal-trifecta/) `[read]` |
| Two of three per session | Agents Rule of Two. If all three are needed, supervise or start a fresh context | [Meta](https://ai.meta.com/blog/practical-ai-agent-security/) `[read]` |
| Constrain the agent | Six design patterns. Authors argue general-purpose agents are unlikely to give reliable guarantees | [Willison on the paper](https://simonwillison.net/2025/Jun/13/prompt-injection-design-patterns/) `[read]` |

Real attacks that map to Dot:

| Attack | Path | Dot equivalent `[judgement]` |
|---|---|---|
| ForcedLeak, CVSS 9.4: web-to-lead text, later processed by an agent, leaked through an image to an expired allowlisted domain ([THN](https://thehackernews.com/2025/09/salesforce-patches-critical-forcedleak.html) `[search]`) | Public form → CRM → agent → image URL | Dot.Forms submission or `/jobs` application → HR or Inbox agent |
| EchoLeak: crafted email to Copilot ([arXiv](https://arxiv.org/abs/2509.10540) `[search]`) | Email → assistant → URL | Dot.Notify and any email ingest |
| GitHub MCP toxic flow: public issue → private repo → public PR ([Vulert](https://vulert.com/blog/github-mcp-exploited/) `[search]`) | Public content → private data → public write | InfoDot public question → private Docs → published answer |
| Memory poisoning: planted "experiences" reused later ([arXiv](https://arxiv.org/abs/2512.16962) `[search]`, [ICML 2026](https://icml.cc/virtual/2026/67964) `[search]`) | Untrusted input → long-term memory | Agent memory, shared across runs |
| Lies-in-the-Loop: injected text writes a misleading approval dialog ([OWASP](https://owasp.org/www-community/attacks/Lies_in_the_Loop) `[search]`) | Tool output → approval screen | Command Centre approval cards |

Recommended controls, strongest first `[judgement]`:

1. **Trust labels at the source.** Each content unit carries provenance. Suggested levels:
   - Trusted: owner instructions, system config.
   - Internal: data authored by authenticated staff.
   - External: anything from a public form, email, upload, web page, InfoDot guest, CV, or third-party tool.
   Mixed documents count as external.
2. **Taint tracking at run level, enforced by PHP, not by the model.** When a run reads external content, `taint_level` rises and stays raised for the run.
3. **A trifecta gate.** Each tool declares `reads_sensitive`, `writes_state` and `external_reach`. A tainted run cannot reach an external-reach tool without a human, and a run that has read sensitive data cannot read external content without moving to a fresh context. This is the Rule of Two as code.
4. **Deterministic policy engine before every tool call.** Default deny. Laravel Gates can express this. Copy AWS's temporal idea: rules that consider what already happened in the run ([AWS](https://docs.aws.amazon.com/bedrock-agentcore/latest/devguide/policy.html) `[read]`).
5. **Quarantined reading.** For untrusted content, use a separate model call with no tools that returns a strict schema (category, entities, boolean). This is the Dual LLM and Map-Reduce idea at low cost. Pass free text onward only as an opaque reference that the planner never reads, and that a person sees rendered as data.
6. **Plan before reading where possible.** Fix the tool sequence from the trusted request first. Plan-Then-Execute prevents new actions, but not changed parameters, so keep parameter validation ([Willison](https://simonwillison.net/2025/Jun/13/prompt-injection-design-patterns/) `[read]`).
7. **Close output channels.** Both ForcedLeak and EchoLeak exfiltrated through URLs. Render agent output with no auto-loading images or remote markdown, allowlist links, and keep the enforced nonce CSP pattern used on other Dot apps. Watch allowlisted domains for expiry.
8. **Memory write gate.** Tainted runs cannot write to long-term memory without review. Record which run wrote each item, as Anthropic does.
9. **Least privilege and short-lived credentials per run.** OWASP's agentic list suggests per-agent identity with short-lived scoped credentials ([Cycode summary](https://cycode.com/blog/owasp-top-10-agentic-applications/) `[search]`).
10. **Classifiers as a signal only.** A guard model can raise risk and route to a person, and should never be the only barrier. Anthropic's design is useful here: strip assistant text and tool results from what the safety check reads, so the agent cannot argue with it ([Anthropic](https://www.anthropic.com/engineering/claude-code-auto-mode) `[read]`).
11. **Continuous red-team suite.** Include ForcedLeak-style cases against every platform that accepts public input. AgentDojo is the common benchmark in the research above.

Expect some utility loss and show it honestly in the product, for example "This run read a public form, so sending requires approval."

### 7.4 Autonomy-level frameworks

Useful sources:

- **Feng, McDonald and Zhang**: five levels by user role: operator, collaborator, consultant, approver, observer. Autonomy is a design choice separate from capability. Proposes autonomy certificates backed by evidence ([Knight Institute](https://knightcolumbia.org/content/levels-of-autonomy-for-ai-agents-1) `[read]`).
- **AWS Scoping Matrix**: scopes from no agency, through prescribed and supervised, to full agency. Monitoring and shutoff matter more as approval falls ([AWS](https://aws.amazon.com/blogs/security/the-agentic-ai-security-scoping-matrix-a-framework-for-securing-autonomous-ai-systems) `[search]`).
- **Singapore IMDA framework**: bound risks upfront, make humans meaningfully accountable, add technical controls, enable end users. Launched 2026-01-22, updated 2026-05-20 ([MDDI](https://www.mddi.gov.sg/newsroom/singapore-launches-new-model-ai-governance-framework-for-agentic-ai--/) `[search]`, [Baker McKenzie](https://www.bakermckenzie.com/en/insight/publications/2026/06/singapore-imda-updates-model-ai-governance-framework-for-agentic-ai) `[search]`).
- **Anthropic's usage study**: experienced users auto-approve more (20% rising to over 40%) and also interrupt more (5% to 9% of turns). Anthropic advises being able to intervene when it matters, not approving every action ([Anthropic](https://www.anthropic.com/research/measuring-agent-autonomy) `[read]`).

Recommended Dot model `[judgement]`. Set autonomy per **agent + action class + platform**, not per agent. An HR agent can read at a high level and draft at a low level.

| Level | Name | Behaviour | User role (Feng) |
|---|---|---|---|
| 0 | Observe | Read and report only | Operator |
| 1 | Draft | Prepare work, a person sends it | Collaborator |
| 2 | Ask | Executes after approval on each action | Approver |
| 3 | Act within limits | Executes inside set limits, notifies, offers undo | Consultant or approver |
| 4 | Act and sample | Executes, with a sample reviewed and exceptions raised | Observer with kill switch |

Rules:

- **Hard ceilings that no evidence can lift.** Moving money, final HR decisions about a person, deletion without recovery, and first contact with a new external party. Check these against the Guardian autonomy-ceiling rule so the ecosystem has one concept.
- **Promotion needs evidence.** For example: minimum N approved actions, edit rate under a threshold, eval pass, no open incidents, owner sign-off. Numbers should come from Dot's first real data.
- **Demotion is automatic.** A security event, a policy denial cluster, a material eval drop, or a prompt or model change resets to probation for the affected class.
- **Limits are concrete.** Amount, recipient list, records per run, time window.
- **Taint overrides autonomy.** A Level 4 agent on a tainted run drops to Level 2 for sensitive actions.

### 7.5 How approval UIs should show consequence

Evidence here is weaker than for security. Most is practitioner writing, plus OWASP and vendor data. I found no controlled study of agent approval screens ([summary of the search](https://aipatternbook.com/approval-fatigue) `[search]`).

Reported findings:

- Users accepted 93% of Claude Code prompts ([Anthropic](https://www.anthropic.com/engineering/claude-code-auto-mode) `[read]`). Later press cites 97% ([Techwyse](https://www.techwyse.com/news/platform-updates/anthropic-claude-code-auto-mode-default) `[read]`).
- Press reports of an Anthropic study say people caught about 17% of dangerous commands early in a session and about 5% after 50 prompts ([Techwyse](https://www.techwyse.com/news/platform-updates/anthropic-claude-code-auto-mode-default) `[read]`). **Unverified** on a primary page.
- A blog reports a 2024 Harvard Business School study of 228 evaluators who were 19 points more likely to follow AI recommendations than a control group ([tianpan](https://tianpan.co/blog/2026-04-15-human-in-the-loop-rubber-stamp) `[read]`). I did not see the study itself. If it holds, explanation text alone will not protect reviewers.
- Rule of Two admits compliant designs still fail if a user confirms blindly ([Meta](https://ai.meta.com/blog/practical-ai-agent-security/) `[read]`).

Design recommendations `[judgement unless cited]`:

1. **Ask less.** Gate on consequence: irreversible, external, financial, sensitive, or tainted. Let reversible internal work run and be reviewable. Only 0.8% of observed actions were irreversible ([Anthropic](https://www.anthropic.com/research/measuring-agent-autonomy) `[read]`).
2. **System-rendered card only.** Build it from typed arguments and platform dry-run output. Render no LLM markdown in the dialog ([OWASP](https://owasp.org/www-community/attacks/Lies_in_the_Loop) `[search]`).
3. **Show effect in business terms.**
   - What will happen, in one system-written sentence.
   - Who is affected, with named people and a count.
   - Money in rand.
   - Before and after, as a diff.
   - Can it be undone, how, and until when.
   - What data leaves Dot and where it goes.
   Practitioner guides suggest the same ([ai-tldr](https://ai-tldr.dev/learn/building-ai-apps/ai-ux-patterns/human-in-the-loop-ux/) `[search]`, [aiuxplayground](https://aiuxplayground.com/guides/how-to-design-human-in-the-loop) `[search]`).
4. **Show provenance.** "This came from a public form" should be a system label, visually separate from the agent's reasoning, which is itself labelled as model-written.
5. **Bind the approval to what runs.** Hash the frozen preview and refuse execution if arguments change ([Ganglani](https://www.kunalganglani.com/blog/tool-approval-patterns-ai-agents) `[search]`).
6. **Offer three real choices.** Approve, edit, reject with a reason. Edits and rejections feed evals. Rejected actions return to the agent with the reason, as in Claude's `deny_message` and n8n ([Anthropic](https://platform.claude.com/docs/en/managed-agents/permission-policies) `[read]`, [n8n](https://docs.n8n.io/advanced-ai/human-in-the-loop-tools) `[search]`).
7. **Handle fatigue on purpose.** Group similar actions into one decision. Offer a time-limited rule for repeat actions, like OpenAI's sticky decisions ([OpenAI](https://openai.github.io/openai-agents-python/human_in_the_loop/) `[read]`). Track time-to-approve and approval rate per approver. A rate near 100% should prompt a review of the gate.
8. **Seed checks if needed.** Occasionally include a known-bad test action and measure catch rate. This is untested in the sources I found, so try it carefully with staff who know about it.
9. **Handle denial loops.** Anthropic escalates after 3 consecutive or 20 total denials ([Anthropic](https://www.anthropic.com/engineering/claude-code-auto-mode) `[read]`).
10. **Set an expiry.** Dify defaults to 7 days ([Dify](https://github.com/langgenius/dify/releases/tag/1.13.0) `[search]`), and Claude waits indefinitely ([Anthropic](https://platform.claude.com/docs/en/managed-agents/permission-policies) `[read]`). Make expiry a business setting, and re-check policy at approval time because the world may have changed.
11. **Separation of duties.** The agent's owner should not always be able to approve its financial actions.
12. **Log everything shown.** Store dialog content with the decision and the executed action ([OWASP](https://owasp.org/www-community/attacks/Lies_in_the_Loop) `[search]`).

### 7.6 MCP: posture and recommendation

Current state:

- **Specification.** 2026-07-28 removes sessions and the initialize handshake, adds `server/discover`, Multi Round-Trip Requests, required `Mcp-Method` and `Mcp-Name` headers, cacheable lists, and moves Tasks to an extension. Roots, Sampling and Logging are deprecated with a twelve-month minimum window ([MCP](https://modelcontextprotocol.io/specification/2026-07-28/changelog) `[read]`).
- **Auth hardening.** Clients validate `iss`, credentials are bound to their issuer, and Dynamic Client Registration is deprecated in favour of Client ID Metadata Documents ([MCP](https://blog.modelcontextprotocol.io/posts/2026-07-28/) `[read]`).
- **Security guidance.** Covers confused deputy, token passthrough (forbidden), SSRF during OAuth discovery, state handle hijacking, malicious authorization URLs and scope minimisation ([MCP](https://modelcontextprotocol.io/specification/draft/basic/security_best_practices) `[read]`).
- **Incidents.**
  - `mcp-remote` CVE-2025-6514, CVSS 9.6: OS command injection from an untrusted server, affecting 0.0.5 up to 0.1.15, fixed in 0.1.16 (GitHub advisory GHSA-6xpm-ggf7-wc3p `[read]`). The package had nearly half a million downloads ([pipelab](https://pipelab.org/blog/state-of-mcp-security-2026/) `[read]`).
  - `postmark-mcp`: described as the first publicly documented malicious MCP server ([pipelab](https://pipelab.org/blog/state-of-mcp-security-2026/) `[read]`).
  - Tool poisoning and rug pulls, where a server changes after approval ([glasp](https://glasp.co/articles/mcp-security-tool-poisoning-supply-chain) `[read]`).
  - The Vulnerable MCP Project is quoted as tracking over 50 known vulnerabilities, 13 of them critical ([pipelab](https://pipelab.org/blog/state-of-mcp-security-2026/) `[read]`). Other sources give different totals, so treat the count as approximate.
- **The structural issue.** Willison notes MCP encourages mixing tools, which can create the trifecta ([Willison](https://simonwillison.net/2025/Jun/16/the-lethal-trifecta/) `[read]`). No protocol revision fixes that `[judgement]`.
- **PHP and Laravel.** Official `mcp/sdk` is maintained with the PHP Foundation ([GitHub](https://github.com/modelcontextprotocol/php-sdk) `[search]`). `laravel/mcp` provides servers, tool annotations and a client, and its docs link the 2026-07-28 spec. Its source lists 2026-07-28 as the latest supported version, alongside older versions ([GitHub](https://github.com/laravel/mcp/blob/main/src/Enums/ProtocolVersion.php), checked through the GitHub API `[read]`). Its Passport OAuth flow has a single `mcp:use` scope, with custom scopes unsupported, so authorisation must be coded per tool ([Laravel](https://laravel.com/docs/13.x/mcp) `[read]`).
- **What enterprises restrict.** Salesforce allows tools only, with no user-level authentication ([Salesforce](https://help.salesforce.com/s/articleView?id=ai.agent_mcp_considerations.htm&amp=&language=en_US&amp=&type=5) `[search]`). Anthropic defaults MCP tools to always ask ([Anthropic](https://platform.claude.com/docs/en/managed-agents/permission-policies) `[read]`).

**Recommendation** `[judgement]`:

| Role | Decision | Conditions |
|---|---|---|
| Between Dot platforms | **Neither.** Use typed internal tools over signed HTTP | Dot owns both ends, and a protocol adds nothing to that trust boundary. It removes token passthrough, tool poisoning and discovery risks |
| Dot as MCP **server** | **Yes, later.** After launch, read-only first | Lets a customer's Claude or ChatGPT reach their own Dot data. Per-tenant, authorised in code per tool, no wildcard scopes, no destructive tools at first. The stateless spec suits PHP request handling well. Note the customer's client then forms its own trifecta, so log and rate-limit |
| Dot as MCP **client** | **Not at launch.** Curated allowlist only | No customer-added arbitrary servers. Pin a hash of each tool schema in Dot's own code and re-approve on change. Do not depend on `mcp-scan` for this: one article says its pinning was removed in 2026 ([glasp](https://glasp.co/articles/mcp-security-tool-poisoning-supply-chain) `[read]`), and another says it needs a Snyk token ([mcpplayground](https://mcpplaygroundonline.com/blog/mcp-security-tool-poisoning-owasp-top-10-mcp-scan) `[search]`). Treat all output as external taint. Default to ask. Block private IP ranges and consider an egress proxy, as the spec advises ([MCP](https://modelcontextprotocol.io/specification/draft/basic/security_best_practices) `[read]`). Run the client in a way that cannot spawn local processes |

Design tools so a later MCP server is a thin adapter: strict JSON schemas, read and destructive annotations, stable names, deterministic ordering. This keeps the option open at low cost. Skip A2A entirely for now.

### 7.7 Usage-based cost attribution

Facts:

- OpenTelemetry GenAI conventions define token attributes. One guide treats cost as something to compute from token counters rather than as part of the spec ([iotdigitaltwinplm](https://iotdigitaltwinplm.com/opentelemetry-genai-semantic-conventions-llm-agent-tracing-token-cost-attributes-collector-pipeline-2026/) `[read]`). Check the current registry before naming a cost attribute. **Unverified.**
- Input counts in Laravel's SDK are totals that include cache tokens, and output totals include reasoning tokens. It exposes cache read, cache write, reasoning and an uncached-input helper ([Laravel](https://laravel.com/docs/13.x/ai-sdk) `[read]`). Pricing cache tokens separately without subtracting double counts them.
- Claude pricing separates base input, 5-minute writes, 1-hour writes, cache reads and output, with a further multiplier for US-only inference ([Anthropic](https://platform.claude.com/docs/en/about-claude/pricing) `[read]`).

Recommended design `[judgement]`:

1. **An append-only `agent_usage_events` ledger,** one row per model call and per paid tool call, written in the same transaction as the journal step.
   - Attribution: tenant, agent, agent version, owner, run, step, platform, action class, trigger, delegating user.
   - Measures: provider, model, uncached input, cache read, cache write, output, reasoning, tool units, duration.
   - Money: price table version, USD cost, ZAR rate and date, ZAR cost.
   - Raw: provider's usage JSON, for reconciliation.
2. **Versioned price table in the database,** never hard-coded. Compute cost at write time and never change history.
3. **Roll up from the ledger** to run, agent, tenant and month.
4. **Separate wasted spend.** Tag failed, retried, denied and looped work. A run's cost is split into useful and wasted. Few platforms show this, and it answers the complaint about paying for failures ([Monetizely](https://www.getmonetizely.com/blogs/the-doomed-evolution-of-salesforces-agentforce-pricing) `[read]`, [CIO](https://www.cio.com/article/4169954/servicenows-ai-control-tower-offers-hazy-view-of-spend.html) `[read]`).
5. **Budgets at four levels:** per run, per agent per month, per tenant per month, per platform connection. Check before each step using the worst-case estimate. Laravel's `Stop` middleware can end a run on a usage check ([Laravel](https://laravel.com/docs/13.x/ai-sdk) `[read]`).
6. **Stop gracefully.** Copilot Studio can block agents mid-conversation at 125% ([Microsoft](https://learn.microsoft.com/en-us/microsoft-copilot-studio/requirements-messages-management) `[read]`). Dot should park the run at a clean step and raise an inbox item, with a warning at 80%.
7. **Limits must cover everything an agent causes,** including sub-runs, unlike Copilot's agent limit that excludes flows (same source `[read]`).
8. **Loop detection.** Stop on repeated identical calls and on per-run step limits. Zapier's cap of 75 tasks is a good default reference ([Zapier](https://zapier.com/blog/zapier-agents-is-now-ai-by-zapier/) `[read]`).
9. **Customer pricing.** Use one customer-facing unit, shown in rand, prepaid with a hard cap and no surprise overage. Hybrid is the most popular model, used by roughly 37% in one report ([Growth Unhinged](https://www.growthunhinged.com/p/the-state-of-b2b-monetization-in-2026) `[read]`). Outcome pricing is attractive, but it needs a reliable definition of success, so defer it. One statistics page puts it at about 9% fully implemented and about 47% piloting ([alatirok](https://alatirok.com/ai-agent-pricing-models-statistics-2026/) `[read]`), and search summaries gave other figures that I could not trace. Connect to central billing and Dot Points only after internal cost data is trusted.
10. **Monthly reconciliation** of the ledger against provider invoices. Investigate any gap above a small threshold.
11. **Report the useful numbers.** Cost per completed task, cost per approved action, cost per agent, and savings claims only with a stated baseline.

Attach a tenant ID to every span or journal row ([digitalapplied](https://www.digitalapplied.com/blog/llm-agent-cost-attribution-guide-production-2026) `[read]`). With a database ledger on one host, the design work is in the SQL indexes `[judgement]`.

---

## 8. South African considerations

Not legal advice. Have an attorney confirm before relying on any of this.

- **POPIA section 71** covers decisions based solely on automated processing, so much AI-assisted work may fall outside it ([ITLawCo](https://itlawco.com/sa-national-ai-policy-submission-2026/) `[read]`). That submission proposes extending it to cases where automated processing is a material input (same source `[read]`). Keep final HR and credit-type decisions with a person regardless.
- **Regulator guidance.** As at 2026-03-05 none had been published on AI risk or section 71 automated decisions ([ITLawCo](https://itlawco.com/information-regulator-2026-27-annual-performance-plan/) `[read]`). Whether any has been issued since is **unverified**.
- **Draft National AI Policy.** Gazetted 2026-04-10 as Notice 3880 with a 60 day comment window ([Polity](https://www.polity.org.za/article/speak-now-or-forever-hold-your-peace-the-draft-ai-policy-has-been-published-and-parties-have-60-days-to-comment-2026-04-13) `[search]`, [CDH](https://www.cliffedekkerhofmeyr.com/en/news/publications/2026/South-Africa/Technology-Communications/corporate-communication-and-technology-communications-alert-commentary-on-south-sfricas-national-draft-artificial-intelligence-policy) `[search]`). Final status is **unverified**.
- **Section 72 cross-border transfers.** I found no authoritative guidance on applying it to LLM APIs. **Unverified.** Treat every model call containing personal data as a possible cross-border transfer until an attorney says otherwise.
- **Data residency.** No South African residency for Claude's first-party products was found ([App Developer Studio](https://www.appdeveloperstudio.co.za/resources/guides/claude-enterprise-and-team) `[search]`). Bedrock in Cape Town offers global cross-region inference, so processing may leave the country ([AWS](https://aws.amazon.com/blogs/machine-learning/scale-ai-in-south-africa-using-amazon-bedrock-global-cross-region-inference-with-anthropic-claude-4-5-models) `[search]`). OpenAI and Azure routes for South Africa are **unverified**.

Product implications `[judgement]`:

- Data-class rules per tool, including a way to block HR and medical data from a given model route.
- Redact or pseudonymise before external model calls where the task permits.
- A record of which provider and region processed each run, kept in the journal.
- A subject-access view of what an agent did with a person's data, reusing Dot's POPIA export tooling.
- Agents should identify themselves in outbound messages.

---

## 9. Decisions this research supports

| Decision | Recommendation | Main evidence |
|---|---|---|
| Builder style | Describe, then review a structured agent record. No graph canvas | Section 6.3 |
| Agent and workflow | One model: an agent with deterministic steps and bounded judgement | Salesforce, Zapier |
| Multi-agent | Single agent per job. Orchestrator for parallel reads only | Google |
| Durability | Journal plus one step per queue job | Section 7.1 |
| Security | Trust labels, taint, gate, policy engine, closed output channels | Section 7.3 |
| Approval | Consequence-gated, system-rendered, hash-bound | Sections 7.4, 7.5 |
| Autonomy | Per agent, action class and platform, promoted on evidence | Section 7.4 |
| Protocols | Internal typed tools. MCP server later. MCP client later and curated. No A2A | Section 7.6 |
| Cost | Append-only ledger, hard budgets, one rand unit | Section 7.7 |
| Evals | Own them in Dot. State-based graders, incidents become tests | Section 7.2 |
| Vendor exposure | Model-neutral journal and prompts, provider behind a driver | Section 2 |

**Fix before building:**

1. Confirm the production database and worker flags.
2. Raise or isolate `retry_after`, because 90 seconds is too short for a model call.
3. Check PCNTL on the host CLI.
4. Choose Prism or `laravel/ai`, and decide whether to move to Laravel 13.
5. Agree the idempotency contract across Dot platforms.

**Avoid:** a marketplace of third-party skills or MCP servers (treat a skill like installed software, per Anthropic), customer-defined Python, visual graph builders, autonomous payments, and agent-to-agent protocols.

---

## 10. Unverified items and gaps

**Could not verify well, by platform**

- **Sierra, Decagon:** only vendor self-description, or competitor and estimate sources. No independent product testing. Prices unpublished.
- **Workday ASOR, ServiceNow AI Control Tower:** no list prices, and secondary coverage only.
- **Gumloop:** official pricing page returned no readable content. Figures come from competitor blogs and a pricing tracker.
- **Vellum:** whether its Agent Builder and eval products continue after the pivot and closure.
- **Notion, Asana, ClickUp, Rovo, Zoho, Odoo:** third-party sources only, and they conflict.
- **Google:** human approval features, and GA status of Agent Simulation and Evaluation.
- **Agentforce:** human approval model and memory model.
- **Mastra:** suspend and resume and scorers were not on pages I could read.
- **CrewAI:** AMP deployment details and task guardrails.
- **OpenAI:** announcement pages returned 403. Workspace Agents detail relies on VentureBeat, and credit details on search summaries of help pages. AgentKit pricing not confirmed.

**Could not verify well, by topic**

- Direct Reddit sentiment, and current G2 scores.
- Whether the 33% "UC Berkeley" agent-review figure is real. I found only a blog, so I left it out of the conclusions.
- Exact OWASP Agentic Top 10 names ASI04 to ASI06.
- Total CVE counts for MCP. The NVD page did not load, so only CVE-2025-6514 was confirmed, through the GitHub advisory database.
- Cognition's April 2026 follow-up to its multi-agent essay.
- TheAgentCompany's 30% figure on a primary CMU page.
- Claims of a 97% approval rate on a primary Anthropic page. Anthropic's page shows 93%.
- Current Copilot Studio credit prices beyond the June 2026 guide. Search suggests later guides exist.
- Host limits, PCNTL, and production database for Dot.Agents.
- POPIA section 72 application, and any Regulator guidance after March 2026.

**Sources that disagreed**

- Lindy's Trustpilot score: 3.3 on the page today, against 1.7, 2.4 and 2.7 in blogs. I used the page.
- CaMeL's result: 67% in v1, 77% in v2. I used v2.
- Salesforce Max Edition credits: blogs conflict, and I used the official page's 2.75M.
- Relevance AI's Vendor Credit amounts: its docs contradict themselves.
- AgentCore Runtime rates: blogs conflict because v1 and v2 both exist. I used the official page.
