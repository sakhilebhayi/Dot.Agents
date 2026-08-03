<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ── Primary SEO ─────────────────────────────────────────────────── --}}
    <title>Dot.Agents™ — The Adaptive Enterprise Operating System</title>
    <meta name="description" content="Dot.Agents is the Adaptive Enterprise Operating System. Deploy an Enterprise Brain, Executive Council, Digital Workforce, and Organizational Digital Twin to build a truly intelligent digital enterprise.">
    <meta name="keywords" content="enterprise AI platform, digital workforce, AI agents, enterprise operating system, autonomous agents, executive AI council, organizational digital twin, enterprise brain, AI governance, enterprise automation">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author" content="Dot.Agents">
    <link rel="canonical" href="{{ url('/') }}">

    {{-- ── Open Graph ──────────────────────────────────────────────────── --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:site_name" content="Dot.Agents">
    <meta property="og:title" content="Dot.Agents™ — The Adaptive Enterprise Operating System">
    <meta property="og:description" content="Build a Digital Enterprise powered by an Enterprise Brain, Executive Council, Organizational Digital Twin, and Autonomous Workforce. Dot.Agents is the AI Operating System for the modern organization.">
    <meta property="og:image" content="{{ url('/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Dot.Agents — The Adaptive Enterprise Operating System">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

    {{-- ── Twitter / X Card ───────────────────────────────────────────── --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@dotagents">
    <meta name="twitter:creator" content="@dotagents">
    <meta name="twitter:title" content="Dot.Agents™ — The Adaptive Enterprise Operating System">
    <meta name="twitter:description" content="Deploy an Enterprise Brain, Executive Council, and Autonomous Digital Workforce. Build a truly intelligent organization with Dot.Agents.">
    <meta name="twitter:image" content="{{ url('/og-image.png') }}">
    <meta name="twitter:image:alt" content="Dot.Agents — The Adaptive Enterprise Operating System">

    {{-- ── Favicons ────────────────────────────────────────────────────── --}}
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/dot.logos3.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- ── Structured Data: Organization ──────────────────────────────── --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Organization",
        "name": "Dot.Agents",
        "alternateName": "Dot.Agents™",
        "url": "{{ url('/') }}",
        "logo": "{{ url('/dot.logos3.png') }}",
        "description": "Dot.Agents is the Adaptive Enterprise Operating System — an AI-powered digital workforce platform featuring Enterprise Brain, Executive Council, Organizational Digital Twin, and Enterprise Memory Cortex.",
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
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "Dot.Agents™",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "url": "{{ url('/') }}",
        "description": "The Adaptive Enterprise Operating System. Deploy AI-powered digital departments, an Enterprise Brain, Executive Council, and Organizational Digital Twin to build an intelligent organization.",
        "featureList": [
            "Enterprise Brain",
            "Executive Council (CEO, CFO, COO, CTO, CIO, CISO, CHRO, CMO AI Agents)",
            "Organizational Digital Twin",
            "Enterprise Memory Cortex",
            "Digital Workforce — Customer Success, Sales, Marketing, Finance, HR, Operations, IT, Executive",
            "Social Commerce & Customer Success Intelligence",
            "Enterprise Governance Framework",
            "Approval Workflows & Audit Trails",
            "Delusion Detection & AI Safety",
            "Enterprise Simulation Engine",
            "Enterprise Learning Engine",
            "Enterprise Health System"
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

    {{-- ── Structured Data: WebSite + Sitelinks Searchbox ─────────────── --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Dot.Agents",
        "url": "{{ url('/') }}",
        "description": "The Adaptive Enterprise Operating System — AI-powered digital workforce for the modern organization.",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "{{ url('/') }}?q={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>

    {{-- ── Structured Data: FAQ ────────────────────────────────────────── --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What is Dot.Agents?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Dot.Agents is an Adaptive Enterprise Operating System that enables organizations to deploy, govern, and scale intelligent digital workforces powered by a configurable Enterprise Brain."
                }
            },
            {
                "@type": "Question",
                "name": "What is the Enterprise Brain?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "The Enterprise Brain is a centralized AI intelligence system that coordinates strategy, operations, finance, governance, security, customer success, sales, marketing, HR, and compliance across your organization."
                }
            },
            {
                "@type": "Question",
                "name": "What is the Executive Council?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "The Executive Council is a set of AI leadership agents modelled after C-suite roles — CEO, CFO, COO, CTO, CIO, CISO, CHRO, and CMO — that provide multi-perspective recommendations before major decisions."
                }
            },
            {
                "@type": "Question",
                "name": "What digital departments can I deploy?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Dot.Agents supports Digital Departments for Customer Success, Sales, Marketing, Finance, Human Resources, Operations, IT, and Executive functions — each with specialized AI agents."
                }
            },
            {
                "@type": "Question",
                "name": "How does Dot.Agents handle AI governance?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Dot.Agents includes enterprise governance features: approval workflows, confidence-based escalation, immutable audit trails, decision logging, delusion detection, compliance controls, multi-tenant isolation, and emergency kill switches."
                }
            }
        ]
    }
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="font-sans antialiased bg-white text-[#111111]">


{{-- ═══════════════════════════════════════════════
     NAVIGATION
════════════════════════════════════════════════ --}}
<nav class="fixed top-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-b border-[#e8e8e2]" aria-label="Main navigation">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2.5" aria-label="Dot.Agents — Home">
            <img src="/dot.logos3.png" alt="Dot.Agents logo" class="h-8 w-auto" width="32" height="32">
            <span class="font-semibold text-[#111111] font-display text-base">Dot.Agents</span>
        </a>

        <div class="hidden md:flex items-center gap-8" role="list">
            <a href="#future" class="text-sm text-[#555550] hover:text-[#111111] transition-colors" role="listitem">Vision</a>
            <a href="#departments" class="text-sm text-[#555550] hover:text-[#111111] transition-colors" role="listitem">Departments</a>
            <a href="#governance" class="text-sm text-[#555550] hover:text-[#111111] transition-colors" role="listitem">Governance</a>
        </div>

        <div class="flex items-center gap-3">
            @auth
                <a href="{{ url('/dashboard') }}" class="da-btn-primary da-btn-sm">
                    Open Platform
                </a>
            @else
                <a href="{{ route('login') }}" class="text-sm text-[#555550] hover:text-[#111111] transition-colors font-medium">
                    Sign In
                </a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="da-btn-primary da-btn-sm">
                        Get Started
                    </a>
                @endif
            @endauth
        </div>
    </div>
</nav>

{{-- ═══════════════════════════════════════════════
     HERO
════════════════════════════════════════════════ --}}
<main id="main-content">
<section class="bg-[#1e1660] pt-40 pb-28 px-6 relative overflow-hidden" aria-label="Hero — Dot.Agents Adaptive Enterprise Operating System">
    <!-- Photographic Background: real data-center server-room photo by Kier in Sight Archives (@kierinsightarchives), unsplash.com/photos/a-close-up-of-a-server-room-3Nwt6w-KU3E -->
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1667264501379-c1537934c7ab?q=80&w=2400&auto=format&fit=crop');"></div>
    <div class="absolute inset-0 bg-[#1e1660]/90"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-[#1e1660] via-[#1e1660]/85 to-[#1e1660]/60"></div>
    <div class="absolute inset-0 opacity-[0.04]"
         style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 32px 32px;"></div>
    <div class="absolute bottom-0 right-0 w-[700px] h-[700px] bg-brand-purple-mid/15 rounded-full blur-3xl translate-x-1/3 translate-y-1/3"></div>
    <div class="absolute top-0 left-0 w-[500px] h-[500px] bg-brand-yellow/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>

    <div class="max-w-5xl mx-auto relative z-10 text-center">
        <div class="inline-flex items-center gap-2 bg-brand-yellow/15 border border-brand-yellow/25 rounded-full px-5 py-2 mb-8">
            <span class="w-1.5 h-1.5 bg-brand-yellow rounded-full animate-pulse"></span>
            <span class="text-brand-yellow text-xs font-semibold tracking-wide uppercase">The Adaptive Enterprise Operating System</span>
        </div>

        <h1 class="text-6xl md:text-7xl font-black text-white font-display leading-none mb-4 tracking-tight">
            DOT.AGENTS<span class="text-brand-yellow">™</span>
        </h1>

        <p class="text-xl md:text-2xl text-white/60 font-light mb-8">The Adaptive Enterprise Operating System</p>

        <p class="text-base md:text-lg text-white/50 leading-relaxed max-w-3xl mx-auto mb-12">
            Build a Digital Enterprise powered by an Enterprise Brain, Executive Council,<br class="hidden md:block">
            Organizational Digital Twin, and Autonomous Workforce.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            @if (Route::has('register'))
                <a href="{{ route('register') }}"
                   class="da-btn-yellow da-btn-lg px-10 font-semibold shadow-xl">
                    Start Building
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            @endif
            @auth
                <a href="{{ url('/dashboard') }}" class="da-btn-yellow da-btn-lg px-10 font-semibold shadow-xl">
                    Open Platform
                </a>
            @endauth
            <a href="#future"
               class="da-btn da-btn-lg px-10 text-white/70 hover:text-white border border-white/20 hover:border-white/40 bg-transparent hover:bg-white/8">
                Explore the Vision
            </a>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     THE FUTURE
════════════════════════════════════════════════ --}}
<section id="future" class="py-28 px-6 bg-white" aria-labelledby="future-heading">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-16">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">A New Category</p>
            <h2 id="future-heading" class="text-4xl md:text-5xl font-black font-display text-[#111111] leading-tight">
                The Future of Organizations<br>Is Not More Software.
            </h2>
            <p class="text-[#555550] mt-5 max-w-2xl mx-auto leading-relaxed text-lg">
                The future is organizations that can think, learn, govern, adapt, and execute
                through a digital workforce operating alongside human teams.
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            {{-- What others offer --}}
            <div class="rounded-2xl border border-[#e8e8e2] bg-[#f9f9f7] p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-[#555550] text-lg">Most platforms provide</h3>
                </div>
                <ul class="space-y-3">
                    @foreach(['AI Assistants', 'AI Chatbots', 'Workflow Automation', 'Task Automation'] as $item)
                        <li class="flex items-center gap-3 text-[#909088]">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-300 flex-shrink-0"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- What we offer --}}
            <div class="rounded-2xl border-2 border-brand-purple bg-[#1e1660] p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 rounded-full bg-brand-yellow flex items-center justify-center">
                        <svg class="w-4 h-4 text-[#1e1660]" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-white text-lg">Dot.Agents provides</h3>
                </div>
                <ul class="space-y-3">
                    @foreach(['Digital Departments', 'Digital Workforce', 'Enterprise Intelligence', 'Executive Decision Systems', 'Organizational Memory', 'Enterprise Governance'] as $item)
                        <li class="flex items-center gap-3 text-white/80">
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-yellow flex-shrink-0"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-10 text-center">
            <p class="text-xl font-semibold text-[#111111]">We are not building AI tools.</p>
            <p class="text-2xl font-black text-brand-purple font-display mt-1">We are building Digital Enterprises.</p>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     THE PROBLEM
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#f9f9f7]">
    <div class="max-w-5xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">The Challenge</p>
                <h2 class="text-4xl font-black font-display text-[#111111] leading-tight mb-6">
                    The Problem
                </h2>
                <p class="text-[#555550] leading-relaxed text-lg mb-4">Modern organizations are drowning in complexity.</p>
                <p class="text-[#909088] leading-relaxed">Most AI platforms solve individual tasks. Very few help organizations operate as a coordinated intelligence system.</p>
            </div>
            <div class="grid grid-cols-1 gap-3">
                @foreach([
                    'Too many disconnected systems',
                    'Too many manual processes',
                    'Too much tribal knowledge',
                    'Slow decision making',
                    'Operational inefficiencies',
                    'Compliance risks',
                    'Employee overload',
                    'Knowledge loss when people leave',
                    'Inconsistent customer experiences',
                ] as $problem)
                    <div class="flex items-center gap-3 bg-white rounded-lg px-4 py-3 border border-[#e8e8e2]">
                        <div class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-3 h-3 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm text-[#555550]">{{ $problem }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     THE VISION
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#1e1660] relative overflow-hidden">
    <div class="absolute inset-0 opacity-[0.03]"
         style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;"></div>
    <div class="max-w-5xl mx-auto relative z-10">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-yellow mb-3">The Dot.Agents Vision</p>
            <h2 class="text-4xl md:text-5xl font-black font-display text-white leading-tight mb-6">
                Imagine a digital version<br>of your company.
            </h2>
            <p class="text-white/50 text-lg max-w-2xl mx-auto">A version that learns, governs, and continuously improves itself.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
            @foreach([
                ['icon' => '🧠', 'text' => 'Understands your business'],
                ['icon' => '👥', 'text' => 'Understands your people'],
                ['icon' => '🤝', 'text' => 'Understands your customers'],
                ['icon' => '📋', 'text' => 'Understands your policies'],
                ['icon' => '🎯', 'text' => 'Understands your objectives'],
                ['icon' => '📈', 'text' => 'Learns from every decision'],
                ['icon' => '⚙️', 'text' => 'Improves every workflow'],
                ['icon' => '🔒', 'text' => 'Protects organizational knowledge'],
                ['icon' => '🚀', 'text' => 'Continuously optimizes performance'],
            ] as $point)
                <div class="flex items-center gap-3 bg-white/8 border border-white/10 rounded-xl px-5 py-4">
                    <span class="text-xl flex-shrink-0">{{ $point['icon'] }}</span>
                    <span class="text-white/80 text-sm font-medium">{{ $point['text'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="text-center">
            <p class="text-2xl font-black text-brand-yellow font-display">This is Dot.Agents.</p>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     WHAT IS DOT.AGENTS
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-white">
    <div class="max-w-5xl mx-auto text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">Platform Overview</p>
        <h2 class="text-4xl font-black font-display text-[#111111] leading-tight mb-6">What Is Dot.Agents?</h2>
        <p class="text-[#555550] leading-relaxed text-lg max-w-3xl mx-auto mb-14">
            Dot.Agents is an <strong class="text-[#111111]">Adaptive Enterprise Operating System</strong> that enables organizations to deploy, govern, and scale intelligent digital workforces powered by a configurable Enterprise Brain.
        </p>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                ['label' => 'Enterprise Brain',          'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',  'color' => 'bg-purple-50 text-brand-purple'],
                ['label' => 'Executive Council',         'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',  'color' => 'bg-yellow-50 text-yellow-700'],
                ['label' => 'Organizational Digital Twin','icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'bg-blue-50 text-blue-700'],
                ['label' => 'Enterprise Memory Cortex',  'icon' => 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18', 'color' => 'bg-green-50 text-green-700'],
                ['label' => 'Governance Framework',     'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'bg-red-50 text-red-700'],
                ['label' => 'Autonomous Workforce',     'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-2', 'color' => 'bg-indigo-50 text-indigo-700'],
                ['label' => 'Continuous Learning Engine','icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'color' => 'bg-orange-50 text-orange-700'],
                ['label' => 'Social Commerce Intelligence','icon' => 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z', 'color' => 'bg-pink-50 text-pink-700'],
            ] as $cap)
                <div class="da-card p-5 text-center hover:shadow-da-md transition-all hover:-translate-y-0.5">
                    <div class="w-11 h-11 rounded-xl {{ $cap['color'] }} flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $cap['icon'] }}"/>
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-[#111111] leading-snug">{{ $cap['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     ENTERPRISE BRAIN
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#f9f9f7]">
    <div class="max-w-5xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <div class="inline-flex items-center gap-2 bg-brand-purple-pale border border-brand-purple/20 rounded-full px-4 py-1.5 mb-6">
                    <span class="text-brand-purple text-xs font-semibold">Core Platform</span>
                </div>
                <h2 class="text-4xl font-black font-display text-[#111111] leading-tight mb-4">
                    Enterprise Brain<span class="text-brand-yellow">™</span>
                </h2>
                <p class="text-brand-purple font-semibold mb-5">The Intelligence Layer Behind Your Entire Organization</p>
                <p class="text-[#555550] leading-relaxed mb-8">
                    A centralized intelligence system that coordinates strategy, operations, finance, governance, security, customer success, and more — so every agent becomes part of a coordinated enterprise intelligence network.
                </p>
                <p class="text-sm font-semibold text-[#111111] mb-4">The Enterprise Brain continuously asks:</p>
                <div class="space-y-2">
                    @foreach([
                        'Why are we doing this?',
                        'Should we be doing this?',
                        'What are the risks?',
                        'What are the costs?',
                        'What are the alternatives?',
                        'What is the expected outcome?',
                        'How does this impact the organization?',
                    ] as $question)
                        <div class="flex items-start gap-3">
                            <span class="text-brand-purple mt-0.5">›</span>
                            <span class="text-sm text-[#555550] italic">{{ $question }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach(['Strategy', 'Operations', 'Finance', 'Governance', 'Security', 'Customer Success', 'Sales', 'Marketing', 'Human Resources', 'Compliance'] as $domain)
                    <div class="bg-white border border-[#e8e8e2] rounded-lg px-4 py-3 text-sm font-medium text-[#555550] text-center">
                        {{ $domain }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     EXECUTIVE COUNCIL
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#1e1660] relative overflow-hidden">
    <div class="absolute inset-0 opacity-[0.03]"
         style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;"></div>
    <div class="max-w-5xl mx-auto relative z-10">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-yellow mb-3">AI Leadership</p>
            <h2 class="text-4xl font-black font-display text-white leading-tight mb-4">
                Executive Council<span class="text-brand-yellow">™</span>
            </h2>
            <p class="text-white/50 text-lg max-w-2xl mx-auto">
                AI Leadership That Thinks Like Your Executive Team. Deploy a Digital Executive Council before major decisions for multi-perspective recommendations.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                ['role' => 'CEO Agent', 'focus' => ['Growth', 'Vision', 'Expansion', 'Competitive advantage'], 'color' => 'border-brand-yellow/30 bg-brand-yellow/8'],
                ['role' => 'CFO Agent', 'focus' => ['Profitability', 'Cost optimization', 'ROI', 'Budget control'], 'color' => 'border-green-500/30 bg-green-500/8'],
                ['role' => 'COO Agent', 'focus' => ['Execution', 'Efficiency', 'Operational excellence'], 'color' => 'border-blue-400/30 bg-blue-400/8'],
                ['role' => 'CTO Agent', 'focus' => ['Technology strategy', 'Innovation', 'Platform evolution'], 'color' => 'border-purple-400/30 bg-purple-400/8'],
                ['role' => 'CIO Agent', 'focus' => ['Information systems', 'Enterprise intelligence', 'Digital transformation'], 'color' => 'border-cyan-400/30 bg-cyan-400/8'],
                ['role' => 'CISO Agent', 'focus' => ['Security', 'Risk', 'Compliance', 'Governance'], 'color' => 'border-red-400/30 bg-red-400/8'],
                ['role' => 'CHRO Agent', 'focus' => ['Workforce optimization', 'Talent management', 'Organizational health'], 'color' => 'border-orange-400/30 bg-orange-400/8'],
                ['role' => 'CMO Agent', 'focus' => ['Customer growth', 'Marketing', 'Brand strategy'], 'color' => 'border-pink-400/30 bg-pink-400/8'],
            ] as $exec)
                <div class="border {{ $exec['color'] }} rounded-xl p-5">
                    <p class="font-bold text-white text-sm mb-3">{{ $exec['role'] }}</p>
                    <ul class="space-y-1">
                        @foreach($exec['focus'] as $item)
                            <li class="text-xs text-white/55 flex items-center gap-1.5">
                                <span class="w-1 h-1 rounded-full bg-white/30 flex-shrink-0"></span>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     DIGITAL TWIN + MEMORY CORTEX
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-12">
            {{-- Digital Twin --}}
            <div class="da-card p-8">
                <div class="inline-flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-full px-4 py-1.5 mb-5">
                    <span class="text-blue-700 text-xs font-semibold">Organizational Intelligence</span>
                </div>
                <h3 class="text-2xl font-black font-display text-[#111111] mb-3">
                    Organizational Digital Twin<span class="text-brand-yellow">™</span>
                </h3>
                <p class="text-brand-purple font-medium text-sm mb-4">AI That Understands Your Organization</p>
                <p class="text-[#555550] text-sm leading-relaxed mb-6">
                    Most AI understands prompts. Dot.Agents understands businesses. The Digital Twin continuously models your entire organizational structure.
                </p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach(['Departments', 'Teams', 'Employees', 'Assets', 'Projects', 'Workflows', 'Customers', 'Vendors', 'Policies', 'Objectives', 'Constraints', 'More...'] as $item)
                        <div class="bg-[#f9f9f7] rounded-lg px-3 py-2 text-xs text-[#555550] text-center font-medium">
                            {{ $item }}
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Memory Cortex --}}
            <div class="da-card p-8">
                <div class="inline-flex items-center gap-2 bg-green-50 border border-green-200 rounded-full px-4 py-1.5 mb-5">
                    <span class="text-green-700 text-xs font-semibold">Organizational Memory</span>
                </div>
                <h3 class="text-2xl font-black font-display text-[#111111] mb-3">
                    Enterprise Memory Cortex<span class="text-brand-yellow">™</span>
                </h3>
                <p class="text-brand-purple font-medium text-sm mb-4">Your Organization Never Forgets</p>
                <p class="text-[#555550] text-sm leading-relaxed mb-5">
                    Most businesses lose knowledge every day. People leave. Documents disappear. Lessons are forgotten. Dot.Agents creates a permanent Memory Cortex powered by semantic intelligence.
                </p>
                <p class="text-xs font-semibold text-[#555550] mb-3">The system remembers:</p>
                <div class="flex flex-wrap gap-2 mb-5">
                    @foreach(['Decisions', 'Workflows', 'Customer interactions', 'Projects', 'Policies', 'Procedures', 'Successes', 'Failures'] as $memory)
                        <span class="da-badge da-badge-gray text-xs">{{ $memory }}</span>
                    @endforeach
                </div>
                <p class="text-xs text-[#909088] italic">Your company becomes smarter over time.</p>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     DIGITAL WORKFORCE / DEPARTMENTS
════════════════════════════════════════════════ --}}
<section id="departments" class="py-28 px-6 bg-[#f9f9f7]" aria-labelledby="departments-heading">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">Deploy Entire Departments</p>
            <h2 id="departments-heading" class="text-4xl font-black font-display text-[#111111] leading-tight mb-4">
                Digital Workforce<span class="text-brand-yellow">™</span>
            </h2>
            <p class="text-[#555550] text-lg max-w-2xl mx-auto">
                Most AI platforms deploy agents. Dot.Agents deploys departments.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['dept' => 'Customer Success', 'color' => 'bg-purple-50 border-purple-200', 'label_color' => 'text-brand-purple', 'agents' => ['Support Agent', 'Customer Success Agent', 'Reputation Agent', 'Lead Qualification Agent']],
                ['dept' => 'Sales',            'color' => 'bg-green-50 border-green-200',   'label_color' => 'text-green-700',    'agents' => ['Lead Generation Agent', 'Sales Development Agent', 'Proposal Agent', 'Conversion Agent']],
                ['dept' => 'Marketing',        'color' => 'bg-pink-50 border-pink-200',     'label_color' => 'text-pink-700',     'agents' => ['Campaign Agent', 'Social Media Agent', 'Content Agent', 'Analytics Agent']],
                ['dept' => 'Finance',          'color' => 'bg-yellow-50 border-yellow-200', 'label_color' => 'text-yellow-700',   'agents' => ['Financial Analyst Agent', 'Accounts Agent', 'Budget Agent', 'Forecasting Agent']],
                ['dept' => 'Human Resources',  'color' => 'bg-orange-50 border-orange-200', 'label_color' => 'text-orange-700',   'agents' => ['Recruitment Agent', 'Onboarding Agent', 'Employee Success Agent']],
                ['dept' => 'Operations',       'color' => 'bg-blue-50 border-blue-200',     'label_color' => 'text-blue-700',     'agents' => ['Workflow Agent', 'Process Optimization Agent', 'Compliance Agent']],
                ['dept' => 'IT',               'color' => 'bg-gray-50 border-gray-200',     'label_color' => 'text-gray-700',     'agents' => ['Service Desk Agent', 'Infrastructure Agent', 'Security Operations Agent']],
                ['dept' => 'Executive',        'color' => 'bg-[#1e1660]/5 border-[#1e1660]/20', 'label_color' => 'text-[#1e1660]', 'agents' => ['CEO Agent', 'CFO Agent', 'COO Agent', 'Strategic Planning Agent']],
            ] as $dept)
                <div class="bg-white border rounded-xl p-5 {{ $dept['color'] }}">
                    <p class="font-bold text-sm {{ $dept['label_color'] }} mb-3">{{ $dept['dept'] }}</p>
                    <ul class="space-y-1.5">
                        @foreach($dept['agents'] as $agent)
                            <li class="text-xs text-[#555550] flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-40 flex-shrink-0 {{ $dept['label_color'] }}"></span>
                                {{ $agent }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     SOCIAL COMMERCE
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#1e1660] relative overflow-hidden">
    <div class="absolute inset-0 opacity-[0.03]"
         style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;"></div>
    <div class="max-w-5xl mx-auto relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-yellow mb-3">Social Intelligence</p>
                <h2 class="text-4xl font-black font-display text-white leading-tight mb-4">
                    Social Commerce & Customer<br>Success Intelligence<span class="text-brand-yellow">™</span>
                </h2>
                <p class="text-white/50 font-medium mb-5">Your AI Social Media Team</p>
                <p class="text-white/60 leading-relaxed mb-8">
                    Connect business social accounts and activate AI-powered customer engagement across every channel.
                </p>
                <div class="flex flex-wrap gap-2 mb-6">
                    @foreach(['Facebook', 'Instagram', 'LinkedIn', 'WhatsApp Business', 'TikTok', 'X'] as $platform)
                        <span class="bg-white/10 border border-white/15 rounded-full px-4 py-1.5 text-white/70 text-xs font-medium">{{ $platform }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach(['Customer support', 'Lead generation', 'Community management', 'Reputation monitoring', 'Sentiment analysis', 'Sales conversion'] as $cap)
                        <div class="flex items-center gap-2 text-white/70 text-sm">
                            <svg class="w-3.5 h-3.5 text-brand-yellow flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $cap }}
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="space-y-3">
                <div class="bg-white/10 border border-white/15 rounded-xl p-5 text-center">
                    <p class="text-brand-yellow font-black text-2xl font-display mb-1">Monitor → Engage → Convert → Retain → Grow</p>
                    <p class="text-white/40 text-sm">Across all connected channels</p>
                </div>
                @foreach([
                    ['metric' => '↑ 34%', 'label' => 'Customer retention', 'color' => 'text-green-400'],
                    ['metric' => '↑ 28%', 'label' => 'Lead conversion rate', 'color' => 'text-brand-yellow'],
                    ['metric' => '↓ 62%', 'label' => 'Response time', 'color' => 'text-blue-400'],
                    ['metric' => '↑ 91%', 'label' => 'CSAT improvement', 'color' => 'text-purple-400'],
                ] as $stat)
                    <div class="bg-white/8 border border-white/10 rounded-xl px-5 py-4 flex items-center justify-between">
                        <span class="text-white/60 text-sm">{{ $stat['label'] }}</span>
                        <span class="font-black text-lg font-display {{ $stat['color'] }}">{{ $stat['metric'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     ORGANIZATIONAL DNA
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">Configuration</p>
            <h2 class="text-4xl font-black font-display text-[#111111] leading-tight mb-4">
                Organizational DNA<span class="text-brand-yellow">™</span>
            </h2>
            <p class="text-[#555550] text-lg max-w-2xl mx-auto">
                Every organization thinks differently. Your AI should too. The result: a digital workforce that behaves like your organization.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                ['config' => 'Mission',             'desc' => 'Why your organization exists',                          'icon' => '🎯'],
                ['config' => 'Vision',              'desc' => 'Where your organization is going',                      'icon' => '🔭'],
                ['config' => 'Values',              'desc' => 'What guides decisions',                                 'icon' => '⚖️'],
                ['config' => 'Leadership Style',    'desc' => 'How decisions are made',                               'icon' => '👔'],
                ['config' => 'Risk Appetite',       'desc' => 'How aggressive or conservative your strategy is',      'icon' => '📊'],
                ['config' => 'Communication Style', 'desc' => 'How the workforce communicates internally and externally','icon' => '💬'],
                ['config' => 'Strategic Priorities','desc' => 'What the organization optimizes for',                  'icon' => '🏆'],
                ['config' => 'Organizational Culture','desc' => 'The personality of your digital workforce',          'icon' => '🌱'],
            ] as $dna)
                <div class="da-card p-5 hover:shadow-da-md transition-all hover:-translate-y-0.5">
                    <span class="text-2xl mb-3 block">{{ $dna['icon'] }}</span>
                    <p class="font-bold text-sm text-[#111111] mb-1">{{ $dna['config'] }}</p>
                    <p class="text-xs text-[#909088] leading-relaxed">{{ $dna['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     ENTERPRISE GOVERNANCE
════════════════════════════════════════════════ --}}
<section id="governance" class="py-28 px-6 bg-[#f9f9f7]" aria-labelledby="governance-heading">
    <div class="max-w-5xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">Built for Trust</p>
                <h2 id="governance-heading" class="text-4xl font-black font-display text-[#111111] leading-tight mb-4">
                    Enterprise Governance<span class="text-brand-yellow">™</span>
                </h2>
                <p class="text-[#555550] leading-relaxed mb-8">Designed with governance at its core. Every action is traceable. Every decision is explainable. Every workflow is governable.</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach(['Approval workflows', 'Risk scoring', 'Audit trails', 'Decision logging', 'Policy enforcement', 'Delusion detection', 'Compliance controls', 'Multi-tenant isolation', 'Security monitoring', 'Emergency kill switches'] as $cap)
                        <div class="flex items-center gap-2 text-sm text-[#555550]">
                            <svg class="w-4 h-4 text-brand-purple flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $cap }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="da-card overflow-hidden">
                <div class="bg-[#1e1660] px-5 py-3 flex items-center gap-2">
                    <div class="flex gap-1.5">
                        <div class="w-2.5 h-2.5 rounded-full bg-white/20"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-white/20"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-white/20"></div>
                    </div>
                    <span class="text-white/50 text-xs ml-2">Governance Center</span>
                </div>
                <div class="p-5 space-y-3">
                    @foreach([
                        ['label' => 'DIS Status',        'value' => 'All Clear',       'badge' => 'da-badge-green'],
                        ['label' => 'Pending Approvals', 'value' => '3 awaiting',      'badge' => 'da-badge-yellow'],
                        ['label' => 'Audit Events (24h)','value' => '1,847 recorded',  'badge' => 'da-badge-gray'],
                        ['label' => 'Threat Signals',    'value' => '0 detected',      'badge' => 'da-badge-green'],
                        ['label' => 'Avg Trust Score',   'value' => '94 / 100',        'badge' => 'da-badge-purple'],
                    ] as $row)
                        <div class="flex items-center justify-between py-2 border-b border-[#f3f3ef] last:border-0">
                            <span class="text-xs text-[#555550]">{{ $row['label'] }}</span>
                            <span class="{{ $row['badge'] }}">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     LEARNING + SIMULATION + HEALTH
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">Continuous Intelligence</p>
            <h2 class="text-4xl font-black font-display text-[#111111] leading-tight">
                Platform Intelligence Systems
            </h2>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            {{-- Learning Engine --}}
            <div class="da-card p-7">
                <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <h3 class="font-black text-[#111111] font-display mb-2">Enterprise Learning Engine<span class="text-brand-yellow">™</span></h3>
                <p class="text-xs text-brand-purple font-semibold mb-3">Continuous Organizational Improvement</p>
                <p class="text-sm text-[#909088] leading-relaxed mb-4">The platform learns from agent performance, workflow outcomes, customer feedback, and security incidents.</p>
                <p class="text-xs font-semibold text-[#555550] mb-2">Automatically recommends:</p>
                <ul class="space-y-1">
                    @foreach(['Better workflows', 'Better policies', 'Better processes', 'Better agent configurations'] as $rec)
                        <li class="text-xs text-[#909088] flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-orange-400 flex-shrink-0"></span>
                            {{ $rec }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Simulation Engine --}}
            <div class="da-card p-7">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="font-black text-[#111111] font-display mb-2">Enterprise Simulation Engine<span class="text-brand-yellow">™</span></h3>
                <p class="text-xs text-brand-purple font-semibold mb-3">Predict Before You Act</p>
                <p class="text-sm text-[#909088] leading-relaxed mb-4">Before implementing major changes, simulate revenue, operational, customer, risk, and cost impact.</p>
                <div class="bg-[#f9f9f7] rounded-lg p-3 text-xs font-mono">
                    <p class="text-[#555550] mb-2">↑ Marketing Spend 20%</p>
                    <p class="text-green-600">+24% Leads</p>
                    <p class="text-green-600">+13% Revenue</p>
                    <p class="text-[#909088]">+5% Opex</p>
                    <p class="text-blue-600">Confidence: 87%</p>
                </div>
            </div>

            {{-- Health System --}}
            <div class="da-card p-7">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h3 class="font-black text-[#111111] font-display mb-2">Enterprise Health System<span class="text-brand-yellow">™</span></h3>
                <p class="text-xs text-brand-purple font-semibold mb-3">Real-Time Organizational Intelligence</p>
                <p class="text-sm text-[#909088] leading-relaxed mb-4">Monitor every dimension of organizational health with actionable recommendations.</p>
                <div class="space-y-1.5">
                    @foreach(['Revenue Health', 'Customer Health', 'Agent Health', 'Workflow Health', 'Security Health', 'Compliance Health', 'Operational Health'] as $health)
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[#555550]">{{ $health }}</span>
                            <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-green-400 rounded-full" style="width: {{ rand(72, 98) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     WHY DIFFERENT + COMPARISON
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#f9f9f7]">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-3">The Difference</p>
            <h2 class="text-4xl font-black font-display text-[#111111] leading-tight mb-4">
                Why Dot.Agents Is Different
            </h2>
        </div>

        <div class="rounded-2xl border border-[#e8e8e2] overflow-hidden bg-white">
            <div class="grid grid-cols-3">
                <div class="bg-[#f9f9f7] px-6 py-4 border-b border-r border-[#e8e8e2]">
                    <p class="text-xs font-bold text-[#909088] uppercase tracking-wide">Dimension</p>
                </div>
                <div class="px-6 py-4 border-b border-r border-[#e8e8e2] text-center">
                    <p class="text-xs font-bold text-[#909088] uppercase tracking-wide">Traditional AI</p>
                </div>
                <div class="bg-[#1e1660] px-6 py-4 border-b border-[#e8e8e2] text-center">
                    <p class="text-xs font-bold text-brand-yellow uppercase tracking-wide">Dot.Agents</p>
                </div>
            </div>
            @foreach([
                ['dim' => 'Interaction Model',  'old' => 'Assistant',              'new' => 'Workforce'],
                ['dim' => 'Interface',          'old' => 'Chatbot',                'new' => 'Department'],
                ['dim' => 'Automation Scope',   'old' => 'Task Automation',        'new' => 'Business Operations'],
                ['dim' => 'Memory',             'old' => 'Conversation Memory',    'new' => 'Enterprise Memory'],
                ['dim' => 'Leadership',         'old' => 'Single Agent',           'new' => 'Executive Council'],
                ['dim' => 'Platform Type',      'old' => 'Workflow Tool',          'new' => 'Enterprise Operating System'],
                ['dim' => 'Intelligence',       'old' => 'Generic AI',             'new' => 'Organizational Intelligence'],
                ['dim' => 'Adaptation',         'old' => 'Static Rules',           'new' => 'Adaptive Learning'],
            ] as $row)
                <div class="grid grid-cols-3 border-b border-[#e8e8e2] last:border-0">
                    <div class="bg-[#f9f9f7] px-6 py-4 border-r border-[#e8e8e2]">
                        <p class="text-sm font-semibold text-[#555550]">{{ $row['dim'] }}</p>
                    </div>
                    <div class="px-6 py-4 border-r border-[#e8e8e2] text-center">
                        <span class="text-sm text-[#909088]">{{ $row['old'] }}</span>
                    </div>
                    <div class="bg-[#1e1660]/3 px-6 py-4 text-center">
                        <span class="text-sm font-semibold text-brand-purple">{{ $row['new'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     THE EVOLUTION
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#1e1660] relative overflow-hidden">
    <div class="absolute inset-0 opacity-[0.03]"
         style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;"></div>
    <div class="max-w-3xl mx-auto relative z-10 text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-yellow mb-3">The Journey</p>
        <h2 class="text-4xl font-black font-display text-white leading-tight mb-14">The Evolution</h2>

        <div class="space-y-0">
            @foreach([
                ['label' => 'AI Assistant',      'active' => false],
                ['label' => 'AI Agent',          'active' => false],
                ['label' => 'Digital Worker',    'active' => false],
                ['label' => 'Digital Department','active' => false],
                ['label' => 'Digital Workforce', 'active' => false],
                ['label' => 'Enterprise Brain',  'active' => false],
                ['label' => 'Adaptive Enterprise','active' => true],
            ] as $i => $step)
                <div class="flex flex-col items-center">
                    <div class="px-8 py-3 rounded-xl {{ $step['active'] ? 'bg-brand-yellow text-[#1e1660] font-black text-lg' : 'bg-white/10 border border-white/15 text-white/60 text-sm font-medium' }} transition-all">
                        {{ $step['label'] }}
                    </div>
                    @if(!$loop->last)
                        <div class="w-px h-6 bg-white/20 flex items-center justify-center">
                            <svg class="w-3 h-3 text-white/30 translate-y-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     MISSION + FUTURE
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-12">
            {{-- Mission --}}
            <div class="bg-[#f9f9f7] border border-[#e8e8e2] rounded-2xl p-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-purple mb-4">Our Purpose</p>
                <h3 class="text-2xl font-black font-display text-[#111111] mb-6">The Mission</h3>
                <p class="text-[#555550] leading-relaxed text-lg">
                    To give every organization access to a configurable digital workforce governed by an Enterprise Brain that can
                    <strong class="text-[#111111]">think</strong>,
                    <strong class="text-[#111111]">learn</strong>,
                    <strong class="text-[#111111]">govern</strong>,
                    <strong class="text-[#111111]">coordinate</strong>, and
                    <strong class="text-[#111111]">continuously improve</strong>
                    alongside human teams.
                </p>
            </div>

            {{-- Future --}}
            <div class="bg-[#1e1660] rounded-2xl p-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-yellow mb-4">The Vision</p>
                <h3 class="text-2xl font-black font-display text-white mb-6">The Future</h3>
                <p class="text-white/60 leading-relaxed mb-8">We believe every organization will eventually have:</p>
                <div class="space-y-3">
                    @foreach(['A Human Workforce', 'A Digital Workforce', 'An Enterprise Brain'] as $element)
                        <div class="flex items-center gap-3 bg-white/10 border border-white/15 rounded-xl px-5 py-4">
                            <span class="text-brand-yellow font-black text-lg">+</span>
                            <span class="text-white font-semibold">{{ $element }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-white/40 text-sm mt-6 leading-relaxed">The organizations that successfully combine all three will define the next generation of business.</p>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════
     CTA
════════════════════════════════════════════════ --}}
<section class="py-28 px-6 bg-[#1e1660] relative overflow-hidden">
    <div class="absolute inset-0 opacity-[0.03]"
         style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;"></div>
    <div class="max-w-3xl mx-auto relative z-10 text-center">
        <h2 class="text-5xl font-black font-display text-white leading-tight mb-4">
            Welcome to<br><span class="text-brand-yellow">Dot.Agents.</span>
        </h2>
        <p class="text-white/50 text-xl mb-2">The Adaptive Enterprise Operating System.<span class="text-brand-yellow">™</span></p>
        <p class="text-white/35 mb-12">Start building your digital enterprise today.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="da-btn-yellow da-btn-lg px-12 font-semibold shadow-xl text-base">
                    Get Started — It's Free
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            @endif
            <a href="{{ route('login') }}" class="da-btn da-btn-lg px-12 text-white/70 hover:text-white border border-white/20 hover:border-white/40 bg-transparent hover:bg-white/8">
                Sign In
            </a>
        </div>
    </div>
</section>

</main>

{{-- ═══════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════ --}}
<footer class="bg-[#f9f9f7] border-t border-[#e8e8e2] py-12 px-6" aria-label="Site footer">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-2.5">
                <img src="/dot.logos3.png" alt="Dot.Agents logo" class="h-7 w-auto" width="28" height="28" loading="lazy">
                <span class="font-semibold text-[#111111] font-display">Dot.Agents</span>
            </div>

            <div class="flex items-center gap-8">
                @if (Route::has('terms.show'))
                    <a href="{{ route('terms.show') }}" class="text-sm text-[#909088] hover:text-[#111111] transition-colors">Terms</a>
                @endif
                @if (Route::has('policy.show'))
                    <a href="{{ route('policy.show') }}" class="text-sm text-[#909088] hover:text-[#111111] transition-colors">Privacy</a>
                @endif
            </div>

            <p class="text-sm text-[#909088]">
                &copy; {{ date('Y') }} Dot.Agents. All rights reserved.
            </p>
        </div>
    </div>
</footer>

</body>
</html>

