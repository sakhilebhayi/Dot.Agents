<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — Dot.Agents</title>
    <meta name="description" content="Privacy Policy for Dot.Agents — the Adaptive Enterprise Operating System.">
    <link rel="icon" href="/dot.logos3.png" type="image/png">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="font-sans antialiased bg-[#f3f0ea] text-[#111111]">

{{-- Nav --}}
<nav class="fixed top-0 inset-x-0 z-50 bg-[#f3f0ea]/95 backdrop-blur border-b border-[#e8e8e2]">
    <div class="max-w-6xl mx-auto px-6 py-3 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <img src="/dot.logos3.png" alt="Dot.Agents" class="h-14 sm:h-[72px] w-auto">
            <span class="font-semibold text-[#111111] font-display text-base">Dot.Agents</span>
        </a>
        <div class="flex items-center gap-3">
            @auth
                <a href="{{ url('/dashboard') }}" class="da-btn-primary da-btn-sm">Open Platform</a>
            @else
                <a href="{{ route('login') }}" class="text-sm text-[#555550] hover:text-[#111111] transition-colors font-medium">Sign In</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="da-btn-primary da-btn-sm">Get Started</a>
                @endif
            @endauth
        </div>
    </div>
</nav>

{{-- Hero --}}
<section class="bg-[#150f2e] pt-32 pb-16 px-6">
    <div class="max-w-3xl mx-auto text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-[#f0c23a] mb-3">Legal</p>
        <h1 class="text-4xl md:text-5xl font-black font-display text-white mb-4">Privacy Policy</h1>
        <p class="text-white/50">Last updated: {{ date('F j, Y') }}</p>
    </div>
</section>

{{-- Content --}}
<main class="py-16 px-6">
    <div class="max-w-3xl mx-auto">

        {{-- Intro statement --}}
        <div class="bg-[#150f2e] rounded-2xl p-8 mb-12 text-center">
            <p class="text-white/80 leading-relaxed text-base">
                Dot.Agents is built on a foundation of trust. We handle your data with the same governance standards
                we build into our platform — transparent, controlled, and accountable.
            </p>
        </div>

        {{-- Quick nav --}}
        <div class="da-card p-6 mb-12">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#453798] mb-4">In This Document</p>
            <div class="grid sm:grid-cols-2 gap-y-2 gap-x-6">
                @foreach([
                    ['#who-we-are',        '1. Who We Are'],
                    ['#what-we-collect',   '2. Information We Collect'],
                    ['#how-we-use',        '3. How We Use Your Information'],
                    ['#ai-data',           '4. AI Processing & Agent Data'],
                    ['#sharing',           '5. Sharing & Disclosure'],
                    ['#international',     '6. International Transfers'],
                    ['#retention',         '7. Data Retention'],
                    ['#security',          '8. Security'],
                    ['#your-rights',       '9. Your Rights'],
                    ['#cookies',           '10. Cookies & Tracking'],
                    ['#children',          '11. Children\'s Privacy'],
                    ['#third-party',       '12. Third-Party Services'],
                    ['#changes',           '13. Changes to This Policy'],
                    ['#contact',           '14. Contact & DPO'],
                ] as [$href, $label])
                    <a href="{{ $href }}" class="text-sm text-[#453798] hover:text-[#111111] transition-colors py-0.5">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="space-y-0 text-[#555550] text-sm leading-relaxed">

            {{-- 1 --}}
            <div id="who-we-are" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">1</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Who We Are</h2>
                </div>
                <p class="mb-3">Dot.Agents (<strong class="text-[#111111]">"we"</strong>, <strong class="text-[#111111]">"us"</strong>, <strong class="text-[#111111]">"our"</strong>) is the operator of the Dot.Agents Adaptive Enterprise Operating System. We are the data controller for personal information processed in connection with the Service.</p>
                <p>This Privacy Policy explains how we collect, use, store, protect, and share information when you access or use our platform at <strong class="text-[#111111]">dotagents.com</strong> and all associated subdomains, APIs, and applications.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 2 --}}
            <div id="what-we-collect" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">2</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Information We Collect</h2>
                </div>

                <div class="space-y-6">
                    <div>
                        <h3 class="font-bold text-[#111111] mb-2">2.1 Information You Provide</h3>
                        <ul class="space-y-1.5 pl-4">
                            @foreach([
                                'Account registration data (name, email address, password)',
                                'Organization profile (company name, domain, industry, size)',
                                'Billing information (processed and stored securely by our payment provider)',
                                'Organizational DNA configuration (mission, vision, values, risk appetite)',
                                'Agent configurations, workflows, and deployment settings',
                                'Content submitted to agents (prompts, documents, messages)',
                                'Support communications and feedback',
                            ] as $item)
                                <li class="flex items-start gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#453798] mt-1.5 flex-shrink-0"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-[#111111] mb-2">2.2 Information We Collect Automatically</h3>
                        <ul class="space-y-1.5 pl-4">
                            @foreach([
                                'Log data (IP address, browser type, operating system, pages visited, timestamps)',
                                'Usage data (features accessed, agent interactions, workflow executions)',
                                'Session data (authentication tokens, session identifiers)',
                                'Device information (device type, screen resolution, timezone)',
                                'Performance data (API response times, error rates)',
                            ] as $item)
                                <li class="flex items-start gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#453798] mt-1.5 flex-shrink-0"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-[#111111] mb-2">2.3 Information from Third Parties</h3>
                        <ul class="space-y-1.5 pl-4">
                            @foreach([
                                'Social account data when you connect platforms (Facebook, Instagram, LinkedIn, WhatsApp Business, TikTok, X) — limited to what is necessary for agent operations',
                                'Identity data from SSO or OAuth providers if used for login',
                                'Payment verification data from our billing processor',
                            ] as $item)
                                <li class="flex items-start gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#453798] mt-1.5 flex-shrink-0"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 3 --}}
            <div id="how-we-use" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">3</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">How We Use Your Information</h2>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e8e8e2]">
                    <div class="grid grid-cols-3 bg-[#f9f9f7] border-b border-[#e8e8e2]">
                        <div class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#555550]">Purpose</div>
                        <div class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#555550]">Data Used</div>
                        <div class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#555550]">Legal Basis</div>
                    </div>
                    @foreach([
                        ['Providing the Service',              'Account, usage, org config',         'Contract performance'],
                        ['Billing & payment processing',       'Billing contact, usage metrics',     'Contract performance'],
                        ['Security & fraud prevention',        'Log data, IP address, session data', 'Legitimate interests'],
                        ['Platform improvement',               'Aggregated, anonymized usage data',  'Legitimate interests'],
                        ['Customer support',                   'Account, communications',            'Contract performance'],
                        ['Legal compliance',                   'Any required data',                  'Legal obligation'],
                        ['Marketing communications',           'Email address',                      'Consent (opt-in)'],
                        ['AI governance & audit logging',      'Agent inputs, outputs, decisions',   'Contract performance'],
                    ] as $row)
                        <div class="grid grid-cols-3 border-b border-[#f3f3ef] last:border-0">
                            <div class="px-4 py-3 text-xs text-[#111111] font-medium">{{ $row[0] }}</div>
                            <div class="px-4 py-3 text-xs text-[#555550]">{{ $row[1] }}</div>
                            <div class="px-4 py-3">
                                <span class="da-badge da-badge-purple text-[10px]">{{ $row[2] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 4 --}}
            <div id="ai-data" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">4</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">AI Processing & Agent Data</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">Agent Inputs.</strong> Content you submit to agents (prompts, documents, instructions) is processed by AI models to generate responses. This data is logged as part of the governance audit trail and may be subject to our delusion detection and moderation systems.</p>
                <p class="mb-3"><strong class="text-[#111111]">Decision Logs.</strong> Every agent decision is stored in a decision log linked to your organization. These logs contain: the input hash, the agent's output, confidence score, delusion risk score, and any approval or escalation events. Decision logs are retained as part of your governance record.</p>
                <p class="mb-3"><strong class="text-[#111111]">Enterprise Memory.</strong> Content stored in the Enterprise Memory Cortex is owned by your organization. Memory entries are scoped exclusively to your tenant and are not shared with other organizations or used to train shared AI models.</p>
                <p class="mb-3"><strong class="text-[#111111]">AI Model Providers.</strong> We use third-party AI model providers (including OpenAI) to power agent inference. Data sent to these providers is governed by their respective terms and privacy policies. We have data processing agreements in place with all AI model providers. We do not enable model training on your data by default.</p>
                <p><strong class="text-[#111111]">Social Account Data.</strong> When you connect social platforms, we access only the permissions you explicitly grant. Social account tokens are encrypted at rest. We do not sell or share social account data with third parties outside of the connected platform's own API interactions.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 5 --}}
            <div id="sharing" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">5</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Sharing & Disclosure</h2>
                </div>
                <p class="mb-4">We do not sell your personal data. We may share data only in the following circumstances:</p>
                <div class="space-y-3">
                    @foreach([
                        ['title' => 'Service Providers',       'desc' => 'Trusted vendors who process data on our behalf under contractual data processing agreements (e.g., payment processors, cloud hosting, AI model providers, email delivery, error monitoring).'],
                        ['title' => 'Legal Requirements',      'desc' => 'When required by law, court order, or government authority, or to protect the rights, property, or safety of Dot.Agents, our users, or others.'],
                        ['title' => 'Business Transfers',      'desc' => 'In connection with a merger, acquisition, or sale of assets, your data may be transferred. We will notify you before your data becomes subject to a different privacy policy.'],
                        ['title' => 'With Your Consent',       'desc' => 'For any other purpose with your explicit prior consent.'],
                        ['title' => 'Aggregated / Anonymized', 'desc' => 'We may share anonymized, aggregated usage statistics that cannot reasonably identify you.'],
                    ] as $item)
                        <div class="bg-[#f9f9f7] border border-[#e8e8e2] rounded-xl p-4">
                            <p class="font-bold text-[#111111] text-sm mb-1">{{ $item['title'] }}</p>
                            <p class="text-xs text-[#555550]">{{ $item['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 6 --}}
            <div id="international" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">6</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">International Transfers</h2>
                </div>
                <p class="mb-3">Dot.Agents operates globally. Your data may be stored and processed in countries outside your country of residence, including South Africa, the United States, and the European Union.</p>
                <p class="mb-3">For transfers involving personal data of individuals in the European Economic Area (EEA) or United Kingdom, we rely on Standard Contractual Clauses approved by the European Commission or equivalent mechanisms.</p>
                <p>For South African residents, we comply with the <strong class="text-[#111111]">Protection of Personal Information Act (POPIA)</strong> and the cross-border transfer provisions of POPIA Section 72.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 7 --}}
            <div id="retention" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">7</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Data Retention</h2>
                </div>
                <div class="overflow-hidden rounded-xl border border-[#e8e8e2]">
                    <div class="grid grid-cols-3 bg-[#f9f9f7] border-b border-[#e8e8e2]">
                        <div class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#555550]">Data Type</div>
                        <div class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#555550]">Retention Period</div>
                        <div class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-[#555550]">Deletion</div>
                    </div>
                    @foreach([
                        ['Account data',         'Duration of subscription',    'Within 90 days of account closure'],
                        ['Customer Data',        'Duration of subscription',    'Within 90 days of account closure'],
                        ['Audit logs',           'Up to 7 years',               'Per compliance requirements'],
                        ['Decision logs',        'Up to 3 years',               'Configurable per organization'],
                        ['Billing records',      'Up to 7 years',               'Per financial regulations'],
                        ['Security event logs',  'Up to 12 months',             'Automated purge'],
                        ['Marketing consent',    'Until withdrawn',             'Immediately upon request'],
                        ['Support tickets',      '2 years after resolution',    'On request or schedule'],
                    ] as $row)
                        <div class="grid grid-cols-3 border-b border-[#f3f3ef] last:border-0">
                            <div class="px-4 py-3 text-xs font-medium text-[#111111]">{{ $row[0] }}</div>
                            <div class="px-4 py-3 text-xs text-[#555550]">{{ $row[1] }}</div>
                            <div class="px-4 py-3 text-xs text-[#555550]">{{ $row[2] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 8 --}}
            <div id="security" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">8</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Security</h2>
                </div>
                <p class="mb-4">We implement enterprise-grade security controls to protect your data:</p>
                <div class="grid sm:grid-cols-2 gap-3 mb-4">
                    @foreach([
                        'Encryption in transit (TLS 1.3) and at rest (AES-256)',
                        'Multi-factor authentication support',
                        'Strict multi-tenant data isolation at database level',
                        'Role-based access control with least-privilege enforcement',
                        'Continuous security monitoring and threat detection',
                        'Automated vulnerability scanning',
                        'Encrypted storage of social API tokens and credentials',
                        'Regular third-party security assessments',
                    ] as $item)
                        <div class="flex items-start gap-2.5 bg-green-50 border border-green-100 rounded-lg px-3 py-2.5">
                            <svg class="w-3.5 h-3.5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-xs text-[#555550]">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
                <p>In the event of a personal data breach, we will notify affected users and relevant supervisory authorities within the timeframes required by applicable law.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 9 --}}
            <div id="your-rights" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">9</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Your Rights</h2>
                </div>
                <p class="mb-5">Depending on your jurisdiction, you may have the following rights regarding your personal data:</p>
                <div class="space-y-3 mb-5">
                    @foreach([
                        ['right' => 'Access',          'desc' => 'Request a copy of the personal data we hold about you.'],
                        ['right' => 'Correction',      'desc' => 'Request correction of inaccurate or incomplete data.'],
                        ['right' => 'Erasure',         'desc' => 'Request deletion of your personal data (subject to legal retention requirements).'],
                        ['right' => 'Portability',     'desc' => 'Request your data in a structured, machine-readable format.'],
                        ['right' => 'Restriction',     'desc' => 'Request restriction of processing in certain circumstances.'],
                        ['right' => 'Objection',       'desc' => 'Object to processing based on legitimate interests.'],
                        ['right' => 'Withdraw Consent','desc' => 'Withdraw consent at any time where processing is consent-based (e.g., marketing).'],
                        ['right' => 'Complaint',       'desc' => 'Lodge a complaint with your local data protection authority.'],
                    ] as $item)
                        <div class="flex gap-4 items-start">
                            <span class="da-badge da-badge-purple text-xs flex-shrink-0 mt-0.5">{{ $item['right'] }}</span>
                            <span class="text-sm text-[#555550]">{{ $item['desc'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p>To exercise your rights, contact our Data Protection Officer at <a href="mailto:privacy@dotagents.com" class="text-[#453798] hover:underline">privacy@dotagents.com</a>. We will respond within 30 days (or as required by applicable law).</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 10 --}}
            <div id="cookies" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">10</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Cookies & Tracking</h2>
                </div>
                <p class="mb-5">We use the following categories of cookies and similar technologies:</p>
                <div class="space-y-3 mb-4">
                    @foreach([
                        ['type' => 'Strictly Necessary', 'badge' => 'da-badge-green',  'desc' => 'Required for the platform to function — session management, authentication, CSRF protection. Cannot be disabled.'],
                        ['type' => 'Functional',         'badge' => 'da-badge-yellow', 'desc' => 'Remembers your preferences (language, theme, dashboard state). Can be disabled without loss of core functionality.'],
                        ['type' => 'Analytics',          'badge' => 'da-badge-gray',   'desc' => 'Aggregated usage statistics to understand how the platform is used and where to improve it. You may opt out.'],
                        ['type' => 'Security',           'badge' => 'da-badge-purple', 'desc' => 'Fraud detection and abuse prevention. Required for platform security.'],
                    ] as $cookie)
                        <div class="flex gap-4 items-start p-4 bg-[#f9f9f7] border border-[#e8e8e2] rounded-xl">
                            <span class="{{ $cookie['badge'] }} flex-shrink-0 mt-0.5">{{ $cookie['type'] }}</span>
                            <span class="text-sm text-[#555550]">{{ $cookie['desc'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p>You can manage cookie preferences through your browser settings. Note that disabling certain cookies may affect platform functionality.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 11 --}}
            <div id="children" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">11</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Children's Privacy</h2>
                </div>
                <p>The Service is not directed to individuals under the age of 18. We do not knowingly collect personal data from children. If you become aware that a child has provided us with personal data, please contact us at <a href="mailto:privacy@dotagents.com" class="text-[#453798] hover:underline">privacy@dotagents.com</a> and we will take steps to delete such data.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 12 --}}
            <div id="third-party" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">12</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Third-Party Services</h2>
                </div>
                <p class="mb-3">The platform integrates with third-party services. When you connect or use these services, your data may be processed by those third parties under their own privacy policies:</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach([
                        ['name' => 'OpenAI',      'purpose' => 'AI model inference for agent responses'],
                        ['name' => 'Stripe',       'purpose' => 'Payment processing and billing management'],
                        ['name' => 'Sentry',       'purpose' => 'Error monitoring and performance tracking'],
                        ['name' => 'Redis',        'purpose' => 'Session and queue management'],
                        ['name' => 'Facebook/Meta','purpose' => 'Social account integration (when connected)'],
                        ['name' => 'LinkedIn',     'purpose' => 'Social account integration (when connected)'],
                        ['name' => 'TikTok',       'purpose' => 'Social account integration (when connected)'],
                        ['name' => 'X (Twitter)',  'purpose' => 'Social account integration (when connected)'],
                    ] as $service)
                        <div class="bg-[#f9f9f7] border border-[#e8e8e2] rounded-lg px-4 py-3">
                            <p class="font-bold text-xs text-[#111111] mb-0.5">{{ $service['name'] }}</p>
                            <p class="text-xs text-[#909088]">{{ $service['purpose'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 13 --}}
            <div id="changes" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">13</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Changes to This Policy</h2>
                </div>
                <p class="mb-3">We may update this Privacy Policy periodically. When we make material changes, we will notify you by email and post a notice within the platform at least 14 days before the changes take effect.</p>
                <p>The "Last updated" date at the top of this page reflects when changes were last made. Continued use of the Service after the effective date constitutes acceptance of the updated Policy.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 14 --}}
            <div id="contact" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">14</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Contact & Data Protection Officer</h2>
                </div>
                <p class="mb-5">For privacy enquiries, rights requests, or to reach our Data Protection Officer:</p>
                <div class="da-card p-6 grid sm:grid-cols-3 gap-6">
                    @foreach([
                        ['label' => 'Privacy / DPO', 'value' => 'privacy@dotagents.com',  'desc'  => 'Data rights requests, privacy concerns'],
                        ['label' => 'Security',       'value' => 'security@dotagents.com', 'desc'  => 'Security incidents, breach reports'],
                        ['label' => 'Legal',          'value' => 'legal@dotagents.com',    'desc'  => 'Regulatory, compliance enquiries'],
                    ] as $contact)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-[#453798] mb-1">{{ $contact['label'] }}</p>
                            <a href="mailto:{{ $contact['value'] }}" class="text-sm text-[#555550] hover:text-[#453798] transition-colors block mb-1">{{ $contact['value'] }}</a>
                            <p class="text-xs text-[#909088]">{{ $contact['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-6 text-xs text-[#909088]">South African residents may also lodge a complaint with the <strong class="text-[#555550]">Information Regulator (South Africa)</strong> at <a href="https://inforegulator.org.za" target="_blank" rel="noopener noreferrer" class="text-[#453798] hover:underline">inforegulator.org.za</a>.</p>
            </div>

        </div>
    </div>
</main>

{{-- Footer --}}
<footer class="bg-[#f9f9f7] border-t border-[#e8e8e2] py-12 px-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <img src="/dot.logos3.png" alt="Dot.Agents" class="h-11 w-auto">
                <span class="font-semibold text-[#111111] font-display">Dot.Agents</span>
            </div>
            <div class="flex items-center gap-8">
                @if (Route::has('terms.show'))
                    <a href="{{ route('terms.show') }}" class="text-sm text-[#909088] hover:text-[#111111] transition-colors">Terms</a>
                @endif
                <a href="{{ route('policy.show') }}" class="text-sm text-[#453798] font-medium">Privacy</a>
                <a href="/" class="text-sm text-[#909088] hover:text-[#111111] transition-colors">Home</a>
            </div>
            <p class="text-sm text-[#909088]">&copy; {{ date('Y') }} Dot.Agents. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>
