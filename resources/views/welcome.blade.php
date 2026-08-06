<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ── Primary SEO ─────────────────────────────────────────────────── --}}
    <title>Dot.Agents — Hire, deploy, and govern your AI workforce</title>
    <meta name="description" content="Dot.Agents lets organisations hire AI agents from a marketplace, deploy them as configured instances, assign them work, and govern every decision with an audit trail, delusion-risk scoring, and an approval workflow.">
    <meta name="keywords" content="AI agent platform, digital workforce, AI governance, agent marketplace, approval workflow, audit trail, delusion detection, multi-tenant SaaS">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author" content="Dot.Agents">
    <link rel="canonical" href="{{ url('/') }}">

    {{-- ── Open Graph ──────────────────────────────────────────────────── --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:site_name" content="Dot.Agents">
    <meta property="og:title" content="Dot.Agents — Hire, deploy, and govern your AI workforce">
    <meta property="og:description" content="Browse a marketplace of AI agents, deploy one as your organisation's own instance, assign it work, and watch it operate under a governance stack that logs, scores, and can halt it.">
    <meta property="og:image" content="{{ url('/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Dot.Agents — AI workforce platform with built-in governance">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

    {{-- ── Twitter / X Card ───────────────────────────────────────────── --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@dotagents">
    <meta name="twitter:creator" content="@dotagents">
    <meta name="twitter:title" content="Dot.Agents — Hire, deploy, and govern your AI workforce">
    <meta name="twitter:description" content="A marketplace of AI agents, a governance stack that audits every decision, and an approval workflow that keeps a human in the loop.">
    <meta name="twitter:image" content="{{ url('/og-image.png') }}">
    <meta name="twitter:image:alt" content="Dot.Agents — AI workforce platform with built-in governance">

    {{-- ── Favicons ────────────────────────────────────────────────────── --}}
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/dot.logos3.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- ── Structured Data: Organization ──────────────────────────────── --}}
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    {
        "@@context": "https://schema.org",
        "@type": "Organization",
        "name": "Dot.Agents",
        "url": "{{ url('/') }}",
        "logo": "{{ url('/dot.logos3.png') }}",
        "description": "Dot.Agents is a multi-tenant platform that lets organisations hire, deploy, monitor, and govern specialised AI agents as digital workforce members.",
        "foundingDate": "2024",
        "contactPoint": [{
            "@type": "ContactPoint",
            "contactType": "customer support",
            "email": "support@dotagents.com"
        }],
        "sameAs": []
    }
    </script>

    {{-- ── Structured Data: SoftwareApplication ───────────────────────── --}}
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    {
        "@@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "Dot.Agents",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "url": "{{ url('/') }}",
        "description": "Deploy AI agents from a marketplace, assign them work, and govern every decision with an audit trail, delusion-risk scoring, an approval workflow, and a Digital Immune System that can suspend a deployment automatically.",
        "featureList": [
            "Agent marketplace and deployment",
            "Confidence-thresholded deployment modes",
            "Immutable audit trail",
            "Delusion-risk scoring",
            "Human-in-the-loop approval workflow",
            "Digital Immune System drift and anomaly detection",
            "Ten-dimension agent scorecard",
            "Prompt-injection guard",
            "Visual multi-step workflow builder",
            "Social account-connected agents"
        ],
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD",
            "description": "Free tier available. Enterprise plans available.",
            "url": "{{ url('/register') }}"
        },
        "provider": {
            "@type": "Organization",
            "name": "Dot.Agents",
            "url": "{{ url('/') }}"
        }
    }
    </script>

    {{-- ── Structured Data: WebSite ────────────────────────────────────── --}}
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    {
        "@@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Dot.Agents",
        "url": "{{ url('/') }}",
        "description": "A multi-tenant platform for hiring, deploying, and governing AI agents as a digital workforce."
    }
    </script>

    {{-- ── Structured Data: FAQ ────────────────────────────────────────── --}}
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    {
        "@@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What is Dot.Agents?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Dot.Agents is a multi-tenant platform that lets organisations hire, deploy, monitor, and govern specialised AI agents as digital workforce members, under an enforced governance stack."
                }
            },
            {
                "@type": "Question",
                "name": "How does deployment work?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "An organisation browses a marketplace of agent types, deploys one as its own configured instance with a deployment mode and confidence threshold, then assigns it tasks."
                }
            },
            {
                "@type": "Question",
                "name": "What stops an agent from acting badly?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Every AI decision is scored for delusion risk, routed through an approval workflow when it crosses a threshold, logged to an immutable audit trail, and watched by a Digital Immune System that can suspend a deployment automatically on drift or failure spikes."
                }
            },
            {
                "@type": "Question",
                "name": "Is this multi-tenant?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. Every tenant-owned record carries an organisation ID enforced by database scopes, middleware, and per-action authorization checks, while the agent marketplace itself is shared across all organisations."
                }
            }
        ]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        :root {
            --ink: #150f2e;
            --ink-soft: #1d1640;
            --indigo: #453798;
            --indigo-bright: #6353c9;
            --gold: #f0c23a;
            --gold-soft: #f6d876;
            --paper: #f3f0ea;
            --slate: #b9b3d6;
            --line: rgba(243, 240, 234, 0.12);
            --font-display: 'Space Grotesk', system-ui, sans-serif;
            --font-body: 'IBM Plex Sans', system-ui, sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
            --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
        }
        html { background: var(--ink); }
        body { font-family: var(--font-body); background: var(--ink); color: var(--paper); }
        .font-display { font-family: var(--font-display); }
        .font-mono { font-family: var(--font-mono); }

        .press { transition: transform 160ms var(--ease-out); }
        .press:active { transform: scale(0.97); }

        @media (prefers-reduced-motion: no-preference) {
            .reveal {
                opacity: 0;
                transform: translateY(14px);
                transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
            }
            .reveal.is-visible { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; }
        }

        @media (hover: hover) and (pointer: fine) {
            .row-hover:hover { background: rgba(243, 240, 234, 0.03); }
            .link-underline { background-size: 0% 1px; }
            .link-underline:hover { background-size: 100% 1px; }
        }
        .link-underline {
            background-image: linear-gradient(currentColor, currentColor);
            background-position: 0 100%;
            background-repeat: no-repeat;
            transition: background-size 220ms var(--ease-out);
        }
    </style>
</head>
<body class="antialiased">

{{-- ═══════════════════════════════════════════════
     NAVIGATION
════════════════════════════════════════════════ --}}
<header id="site-header" class="fixed top-0 left-0 right-0 z-50 border-b border-transparent transition-colors duration-300">
    <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between" aria-label="Main navigation">
        <a href="/" class="flex items-center press" aria-label="Dot.Agents — Home">
            <img src="{{ asset('images/logo.png') }}" alt="Dot.Agents" class="h-14 sm:h-[72px] w-auto">
        </a>

        <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--slate)]">
            <a href="#workforce" class="link-underline hover:text-[var(--paper)] pb-0.5">Workforce</a>
            <a href="#governance" class="link-underline hover:text-[var(--paper)] pb-0.5">Governance</a>
            <a href="#platform" class="link-underline hover:text-[var(--paper)] pb-0.5">Platform</a>
        </div>

        @if (Route::has('login'))
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#150f2e] text-sm font-display font-semibold rounded-lg transition-colors">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--slate)] hover:text-[var(--paper)] transition-colors">
                        Sign in
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#150f2e] text-sm font-display font-semibold rounded-lg transition-colors">
                            Get started
                        </a>
                    @endif
                @endauth

                <button id="mobile-menu-toggle" type="button" class="md:hidden press p-2 -mr-2 text-[var(--paper)]" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path id="icon-menu-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                        <path id="icon-menu-close" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif
    </nav>

    <div id="mobile-menu" class="hidden md:hidden border-t border-[var(--line)] bg-[#150f2e]">
        <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
            <a href="#workforce" class="px-3 py-2.5 text-[var(--slate)] hover:text-[var(--paper)]">Workforce</a>
            <a href="#governance" class="px-3 py-2.5 text-[var(--slate)] hover:text-[var(--paper)]">Governance</a>
            <a href="#platform" class="px-3 py-2.5 text-[var(--slate)] hover:text-[var(--paper)]">Platform</a>
            @guest
                <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--slate)] hover:text-[var(--paper)]">Sign in</a>
            @endguest
        </div>
    </div>
</header>

<main id="main-content">

{{-- ═══════════════════════════════════════════════
     HERO — asymmetric, no centered block
════════════════════════════════════════════════ --}}
<section class="relative min-h-[100dvh] flex items-end overflow-hidden" aria-label="Hero">
    <!-- Photo: control room viewed from above, by Patrick Konior, unsplash.com/photos/a-view-of-a-control-room-from-above-FwHJ6mJjVd4 -->
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1737502483537-19b698ff7d00?q=80&w=2400&auto=format&fit=crop');"></div>
    <div class="absolute inset-0" style="background: linear-gradient(100deg, var(--ink) 0%, var(--ink) 32%, rgba(21,15,46,0.55) 55%, rgba(21,15,46,0.3) 75%, rgba(21,15,46,0.15) 100%);"></div>
    <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(21,15,46,0.25) 0%, transparent 30%, transparent 70%, var(--ink) 100%);"></div>

    {{-- Signature: line-art briefcase-in-circle + forward chevron, echoing the real Dot.Agents mark's icon --}}
    <svg class="hidden lg:block absolute right-[4%] top-[14%] h-[62%] w-auto opacity-[0.16] pointer-events-none" viewBox="0 0 420 420" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="160" cy="160" r="148" stroke="#f0c23a" stroke-width="3"/>
        <rect x="108" y="140" width="104" height="76" rx="6" stroke="#f3f0ea" stroke-width="3"/>
        <path d="M138 140V126a10 10 0 0110-10h24a10 10 0 0110 10v14" stroke="#f3f0ea" stroke-width="3"/>
        <path d="M108 176H212" stroke="#f3f0ea" stroke-width="3"/>
        <path d="M280 100L360 210L280 320" stroke="#f3f0ea" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

    <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-32 pb-16 sm:pb-20 w-full">
        <div class="max-w-2xl reveal" data-reveal>
            <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-6">
                AI workforce platform
            </p>

            <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--paper)] mb-6">
                Hire the agent.<br>Govern the decision.
            </h1>

            <p class="text-lg text-[var(--slate)] leading-relaxed max-w-xl mb-10">
                Browse a marketplace of specialised AI agents, deploy one as your organisation's own configured instance, and assign it real work. Every decision it makes is scored, logged, and — if it crosses a threshold — stopped for a human to review.
            </p>

            @guest
                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#150f2e] font-display font-semibold rounded-lg transition-colors">
                        Get started
                    </a>
                    <a href="#governance" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--slate)] transition-colors">
                        See how it's governed
                    </a>
                </div>
            @endguest
        </div>
    </div>

    {{-- Instrumentation strip — real subsystems, not a fabricated metric --}}
    <div class="relative z-10 w-full border-t border-[var(--line)] bg-[#150f2e]/60 backdrop-blur-sm">
        <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--slate)]">
            <span>Marketplace &amp; deployment</span>
            <span class="text-[var(--gold)]">·</span>
            <span>Audit trail</span>
            <span class="text-[var(--gold)]">·</span>
            <span>Delusion-risk scoring</span>
            <span class="text-[var(--gold)]">·</span>
            <span>Approval workflow</span>
            <span class="text-[var(--gold)]">·</span>
            <span>Digital Immune System</span>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     WORKFORCE — how deployment actually works
════════════════════════════════════════════════ --}}
<section id="workforce" class="py-24 sm:py-28 px-5 sm:px-8" aria-labelledby="workforce-heading">
    <div class="max-w-[1400px] mx-auto">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
            <div class="reveal" data-reveal>
                <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-4">From catalog to task</p>
                <h2 id="workforce-heading" class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                    Every agent starts as a listing, not a chat window
                </h2>
                <p class="text-[var(--slate)] leading-relaxed max-w-sm">
                    An <span class="font-mono text-[13px] text-[var(--paper)]">Agent</span> is a marketplace catalog entry — shared across every organisation. Deploying one creates an <span class="font-mono text-[13px] text-[var(--paper)]">AgentDeployment</span>: your own instance, with its own deployment mode, confidence threshold, and instructions.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-x-10">
                @php
                    $workforce = [
                        ['title' => 'AgentDeployment', 'body' => 'Your configured instance of a marketplace agent — deployment mode, confidence threshold, and custom instructions live here, not on the shared catalog entry.'],
                        ['title' => 'AgentTask', 'body' => 'One unit of assigned work: input and output payloads, confidence score, latency, cost, and token usage, recorded per task.'],
                        ['title' => 'AgentSession & AgentMessage', 'body' => 'Threaded conversation history per deployment, so a chat with an agent is a durable record, not a disposable window.'],
                        ['title' => 'AgentMemory', 'body' => 'Long-term and vector memory an agent carries between tasks, scoped to the deployment that owns it.'],
                        ['title' => 'AgentWorkflow', 'body' => 'Multi-node pipelines built in a visual workflow builder, chaining agents and steps into a single execution.'],
                        ['title' => 'Skill system', 'body' => 'Capabilities an agent can invoke, gated by approval, permission checks, execution audit, and a per-skill score.'],
                    ];
                @endphp
                @foreach ($workforce as $w)
                    <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                        <h3 class="font-display font-medium text-base text-[var(--paper)] mb-1.5">{{ $w['title'] }}</h3>
                        <p class="text-sm text-[var(--slate)] leading-relaxed">{{ $w['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     GOVERNANCE — divided list, the real center of gravity
════════════════════════════════════════════════ --}}
<section id="governance" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--ink-soft)] border-y border-[var(--line)]" aria-labelledby="governance-heading">
    <div class="max-w-[1400px] mx-auto">
        <div class="max-w-xl mb-16 reveal" data-reveal>
            <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-4">What most of the codebase serves</p>
            <h2 id="governance-heading" class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight">
                This isn't a thin wrapper around a chat API
            </h2>
            <p class="text-[var(--slate)] leading-relaxed mt-5">
                Every AI decision is auditable, every agent runs inside a deployment mode with an enforced confidence threshold, and a background system watches for drift and can suspend a deployment on its own.
            </p>
        </div>

        <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
            @php
                $governance = [
                    ['tag' => 'Audit', 'title' => 'Immutable audit trail', 'body' => 'Every significant event lands in an append-only log — no update or delete route exists for it — categorised user, agent, system, or security, each with a risk level.'],
                    ['tag' => 'Risk', 'title' => 'Delusion-risk scoring', 'body' => 'Every AI decision gets a 0–100 hallucination-risk score. 51–75 triggers a human approval request; 76–100 blocks the task outright and flags the deployment.'],
                    ['tag' => 'Approval', 'title' => 'Human-in-the-loop workflow', 'body' => 'Deployment modes — advisory, semi-autonomous, autonomous, executive approval — decide when a task pauses for a person to approve or reject it before it resumes.'],
                    ['tag' => 'Immune system', 'title' => 'Digital Immune System', 'body' => 'A scheduled service watching task-failure rate, p95 latency, delusion-risk trend, injection attempts, and confidence drift, able to suspend a deployment on breach.'],
                    ['tag' => 'Scorecard', 'title' => 'Ten-dimension scorecard', 'body' => 'Task success, confidence accuracy, latency, delusion risk, approval rate, skill utilisation, memory quality, security posture, governance compliance, business impact — computed per agent, per period.'],
                    ['tag' => 'Security', 'title' => 'Prompt-injection guard', 'body' => 'Every piece of user text bound for an LLM is checked before it reaches the model. A hit blocks the call and logs a security event instead of a silent pass-through.'],
                ];
            @endphp
            @foreach ($governance as $i => $g)
                <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                    <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--gold)] mb-3">{{ $g['tag'] }}</p>
                    <h3 class="font-display font-semibold text-xl text-[var(--paper)] mb-2.5">{{ $g['title'] }}</h3>
                    <p class="text-[var(--slate)] leading-relaxed max-w-md">{{ $g['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     PLATFORM — uneven grid, ecosystem/infra facts
════════════════════════════════════════════════ --}}
<section id="platform" class="py-24 sm:py-28 px-5 sm:px-8" aria-labelledby="platform-heading">
    <div class="max-w-[1400px] mx-auto">
        <div class="max-w-xl mb-16 reveal" data-reveal>
            <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-4">Underneath</p>
            <h2 id="platform-heading" class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight">
                Built to run more than one organisation, on more than one model
            </h2>
        </div>

        <div class="grid lg:grid-cols-[1.4fr_minmax(0,1fr)] gap-8 lg:gap-12">
            <div class="grid sm:grid-cols-2 gap-4">
                @php
                    $platform = [
                        ['title' => 'Provider failover', 'body' => 'Every completion call is mediated by a provider-agnostic layer with a failover chain — OpenAI, then Anthropic, then Google AI, then a self-hosted Ollama fallback — each leg behind its own circuit breaker.'],
                        ['title' => 'Multi-tenancy', 'body' => 'Organisations are the tenant root. Every tenant-owned model carries an organisation ID, enforced by database scopes, middleware, and per-action authorization — not left to the query layer to remember.'],
                        ['title' => 'Workflow builder', 'body' => 'A visual editor for multi-node agent pipelines: nodes, connections, and executions, so a sequence of agent steps is a saved artefact, not a one-off script.'],
                        ['title' => 'Social-connected agents', 'body' => 'Agents can operate a connected social account directly — publishing, inbox triage, sentiment monitoring, and lead qualification, with the same governance stack applied.'],
                        ['title' => 'Marketplace extensions', 'body' => 'Third-party capabilities install as plugins onto an agent, with their own lifecycle events, without forking the core catalog.'],
                        ['title' => 'Ecosystem SSO', 'body' => 'A user authenticated through the wider ecosystem hub gains access here automatically, on a shared authentication handoff — no separate signup.'],
                    ];
                @endphp
                @foreach ($platform as $p)
                    <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                        <h3 class="font-display font-medium text-base text-[var(--paper)] mb-1.5">{{ $p['title'] }}</h3>
                        <p class="text-sm text-[var(--slate)] leading-relaxed">{{ $p['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-2xl p-8 reveal self-start" data-reveal>
                <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--gold)] mb-4">Events emitted</p>
                <p class="font-display font-semibold text-4xl text-[var(--paper)] mb-2">57</p>
                <p class="text-sm text-[var(--slate)] leading-relaxed mb-6">
                    domain events across six sub-domains — deployment lifecycle, task outcomes, approvals, security, social, and workflow — each with its own listener chain for audit logging, notification, and scorecard updates.
                </p>
                <div class="flex flex-col gap-2 font-mono text-xs text-[var(--slate)]">
                    <span class="link-underline">AgentDeployed → AgentPaused → AgentDecommissioned</span>
                    <span class="link-underline">ApprovalRequested → ApprovalProcessed</span>
                    <span class="link-underline">SecurityThreatDetected → KillSwitchActivated</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     CTA
════════════════════════════════════════════════ --}}
<section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden" aria-label="Get started">
    <div class="absolute inset-0" style="background: radial-gradient(90% 70% at 50% 0%, #241a52 0%, var(--ink) 60%);"></div>

    <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
            Deploy your first agent under a governance stack that's already built
        </h2>
        <p class="text-[var(--slate)] leading-relaxed mb-10 max-w-lg mx-auto">
            No hardware, no separate signup if you're already on the ecosystem. A free tier is available; enterprise plans scale from there.
        </p>

        @guest
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#150f2e] font-display font-semibold rounded-lg transition-colors">
                    Get started
                </a>
                <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--slate)] transition-colors">
                    Sign in
                </a>
            </div>
        @endguest
        @auth
            <a href="{{ url('/dashboard') }}" class="press inline-flex px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#150f2e] font-display font-semibold rounded-lg transition-colors">
                Open your dashboard
            </a>
        @endauth
    </div>
</section>

</main>

{{-- ═══════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════ --}}
<footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]" aria-label="Site footer">
    <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
        <a href="/" class="flex items-center" aria-label="Dot.Agents — Home">
            <img src="{{ asset('images/logo.png') }}" alt="Dot.Agents" class="h-11 w-auto opacity-90">
        </a>

        <div class="flex items-center gap-8">
            @if (Route::has('terms.show'))
                <a href="{{ route('terms.show') }}" class="text-sm text-[var(--slate)] hover:text-[var(--paper)] transition-colors">Terms</a>
            @endif
            @if (Route::has('policy.show'))
                <a href="{{ route('policy.show') }}" class="text-sm text-[var(--slate)] hover:text-[var(--paper)] transition-colors">Privacy</a>
            @endif
        </div>

        <p class="font-mono text-xs tracking-wide text-[var(--slate)]">
            &copy; {{ date('Y') }} Dot.Agents. AI workforce platform.
        </p>
    </div>
</footer>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    // Scroll-reveal — restrained, respects prefers-reduced-motion, no perpetual animation.
    if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
    } else {
        document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
    }

    // Nav: plain JS, no Alpine/CDN dependency — this guest page ships its own JS bundle
    // and shouldn't need an external network fetch just to toggle a mobile menu.
    (function () {
        const header = document.getElementById('site-header');
        const onScroll = () => {
            const scrolled = window.pageYOffset > 24;
            header.classList.toggle('bg-[#150f2e]/95', scrolled);
            header.classList.toggle('backdrop-blur-md', scrolled);
            header.classList.toggle('border-[var(--line)]', scrolled);
            header.classList.toggle('border-transparent', !scrolled);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        const toggle = document.getElementById('mobile-menu-toggle');
        const menu = document.getElementById('mobile-menu');
        const iconOpen = document.getElementById('icon-menu-open');
        const iconClose = document.getElementById('icon-menu-close');
        if (toggle && menu) {
            toggle.addEventListener('click', () => {
                const isOpen = menu.classList.toggle('hidden') === false;
                toggle.setAttribute('aria-expanded', String(isOpen));
                iconOpen.classList.toggle('hidden', isOpen);
                iconClose.classList.toggle('hidden', !isOpen);
            });
        }
    })();
</script>
</body>
</html>
