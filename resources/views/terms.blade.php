<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service — Dot.Agents</title>
    <meta name="description" content="Terms of Service for Dot.Agents — the Adaptive Enterprise Operating System.">
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
        <h1 class="text-4xl md:text-5xl font-black font-display text-white mb-4">Terms of Service</h1>
        <p class="text-white/50">Last updated: {{ date('F j, Y') }}</p>
    </div>
</section>

{{-- Content --}}
<main class="py-16 px-6">
    <div class="max-w-3xl mx-auto">

        {{-- Quick nav --}}
        <div class="da-card p-6 mb-12">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#453798] mb-4">In This Document</p>
            <div class="grid sm:grid-cols-2 gap-y-2 gap-x-6">
                @foreach([
                    ['#acceptance',        '1. Acceptance of Terms'],
                    ['#description',       '2. Description of Service'],
                    ['#accounts',          '3. Accounts & Access'],
                    ['#acceptable-use',    '4. Acceptable Use'],
                    ['#ai-governance',     '5. AI Governance & Responsibility'],
                    ['#data',              '6. Data & Privacy'],
                    ['#ip',                '7. Intellectual Property'],
                    ['#billing',           '8. Billing & Payments'],
                    ['#suspension',        '9. Suspension & Termination'],
                    ['#disclaimers',       '10. Disclaimers & Warranties'],
                    ['#liability',         '11. Limitation of Liability'],
                    ['#indemnification',   '12. Indemnification'],
                    ['#governing-law',     '13. Governing Law'],
                    ['#changes',           '14. Changes to Terms'],
                    ['#contact',           '15. Contact'],
                ] as [$href, $label])
                    <a href="{{ $href }}" class="text-sm text-[#453798] hover:text-[#111111] transition-colors py-0.5">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        {{-- Section helper macro via Blade component-style blocks --}}
        @php
            $section = fn(string $id, string $num, string $title) =>
                "<div id=\"{$id}\" class=\"scroll-mt-24 mb-12\">
                    <div class=\"flex items-center gap-3 mb-5\">
                        <span class=\"w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0\">{$num}</span>
                        <h2 class=\"text-xl font-black font-display text-[\#111111]\">{$title}</h2>
                    </div>";
        @endphp

        <div class="space-y-0 text-[#555550] text-sm leading-relaxed">

            {{-- 1 --}}
            <div id="acceptance" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">1</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Acceptance of Terms</h2>
                </div>
                <p class="mb-3">By accessing or using the Dot.Agents platform (the <strong class="text-[#111111]">"Service"</strong>), you (<strong class="text-[#111111]">"Customer"</strong> or <strong class="text-[#111111]">"User"</strong>) agree to be bound by these Terms of Service (<strong class="text-[#111111]">"Terms"</strong>). If you are entering into these Terms on behalf of an organization, you represent and warrant that you have the authority to bind that organization.</p>
                <p class="mb-3">If you do not agree to these Terms, you must not access or use the Service.</p>
                <p>These Terms incorporate our <a href="{{ route('policy.show') }}" class="text-[#453798] hover:underline">Privacy Policy</a> by reference.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 2 --}}
            <div id="description" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">2</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Description of Service</h2>
                </div>
                <p class="mb-3">Dot.Agents is an <strong class="text-[#111111]">Adaptive Enterprise Operating System</strong> that enables organizations to deploy, manage, govern, and scale AI-powered digital workforces. The Service includes:</p>
                <ul class="list-none space-y-2 mb-3 pl-4">
                    @foreach([
                        'Enterprise Brain — centralized organizational intelligence layer',
                        'Executive Council — AI leadership agents (CEO, CFO, COO, CTO, CIO, CISO, CHRO, CMO)',
                        'Organizational Digital Twin — real-time model of your organization',
                        'Digital Workforce — department-level AI agents across all business functions',
                        'Enterprise Memory Cortex — persistent organizational knowledge management',
                        'Governance Framework — audit trails, approval workflows, compliance controls',
                        'Social Commerce Intelligence — AI-powered social media engagement',
                        'Enterprise Simulation Engine — outcome prediction and scenario modelling',
                        'Workflow Builder — visual multi-agent workflow orchestration',
                        'API access, webhooks, and third-party integrations',
                    ] as $item)
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#453798] mt-1.5 flex-shrink-0"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <p>We reserve the right to modify, suspend, or discontinue any part of the Service at any time with reasonable notice.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 3 --}}
            <div id="accounts" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">3</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Accounts & Access</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">Registration.</strong> You must provide accurate, complete, and current information when creating an account. You are responsible for maintaining the confidentiality of your credentials.</p>
                <p class="mb-3"><strong class="text-[#111111]">Organizations.</strong> The Service supports multi-tenant organizations. The account owner (<strong class="text-[#111111]">"Organization Admin"</strong>) is responsible for all activity that occurs under their organization, including the actions of all members they invite.</p>
                <p class="mb-3"><strong class="text-[#111111]">Security.</strong> You must notify us immediately at <a href="mailto:security@dotagents.com" class="text-[#453798] hover:underline">security@dotagents.com</a> upon becoming aware of any unauthorized access to your account.</p>
                <p><strong class="text-[#111111]">Age.</strong> You must be at least 18 years old and have the legal capacity to enter into a binding contract to use the Service.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 4 --}}
            <div id="acceptable-use" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">4</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Acceptable Use</h2>
                </div>
                <p class="mb-4">You agree not to use the Service to:</p>
                <div class="grid sm:grid-cols-2 gap-2 mb-4">
                    @foreach([
                        'Violate any applicable law or regulation',
                        'Infringe the intellectual property rights of others',
                        'Transmit malware, viruses, or harmful code',
                        'Attempt to reverse-engineer the platform',
                        'Circumvent any security or access control',
                        'Generate or distribute illegal, abusive, or harmful content',
                        'Conduct unauthorized data scraping or harvesting',
                        'Impersonate any person or entity',
                        'Interfere with the integrity or performance of the Service',
                        'Train competing AI models using platform outputs without written consent',
                    ] as $item)
                        <div class="flex items-start gap-2 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                            <svg class="w-3.5 h-3.5 text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-xs text-[#555550]">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
                <p>We may suspend or terminate access immediately for violations of this section.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 5 --}}
            <div id="ai-governance" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">5</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">AI Governance & Responsibility</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">AI Outputs.</strong> AI-generated outputs from agents deployed on the platform are provided for informational and operational assistance purposes. You are responsible for reviewing, validating, and making final decisions on any agent output, particularly in regulated industries (finance, healthcare, legal, etc.).</p>
                <p class="mb-3"><strong class="text-[#111111]">Autonomous Agents.</strong> When deploying agents in autonomous or semi-autonomous modes, you accept that agent actions are executed on your behalf. You remain fully responsible for the consequences of those actions within your organization and externally.</p>
                <p class="mb-3"><strong class="text-[#111111]">Confidence Thresholds.</strong> The platform provides configurable confidence thresholds and approval workflows. You are responsible for setting appropriate thresholds for your use case and risk tolerance.</p>
                <p class="mb-3"><strong class="text-[#111111]">Delusion Detection.</strong> The platform includes automated hallucination and delusion risk scoring. While we make reasonable efforts to flag high-risk outputs, we do not guarantee that all inaccurate AI outputs will be detected.</p>
                <p><strong class="text-[#111111]">Human Oversight.</strong> Dot.Agents is designed to augment human decision-making, not replace it entirely. For critical business, legal, financial, or safety decisions, human review is required before acting on agent recommendations.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 6 --}}
            <div id="data" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">6</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Data & Privacy</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">Your Data.</strong> You retain ownership of all data you submit to the Service (<strong class="text-[#111111]">"Customer Data"</strong>). You grant us a limited, non-exclusive licence to process Customer Data solely to provide the Service.</p>
                <p class="mb-3"><strong class="text-[#111111]">Data Isolation.</strong> The platform enforces strict multi-tenant data isolation. Customer Data is scoped to your organization and is not accessible by other organizations.</p>
                <p class="mb-3"><strong class="text-[#111111]">AI Training.</strong> We do not use your Customer Data to train our base AI models without your explicit written consent.</p>
                <p class="mb-3"><strong class="text-[#111111]">Retention.</strong> We retain Customer Data for the duration of your subscription and for a period of 90 days following termination, after which it is permanently deleted unless legal obligations require otherwise.</p>
                <p>Our full data handling practices are described in our <a href="{{ route('policy.show') }}" class="text-[#453798] hover:underline">Privacy Policy</a>.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 7 --}}
            <div id="ip" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">7</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Intellectual Property</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">Platform IP.</strong> The Dot.Agents platform, including its Enterprise Brain, Executive Council, Organizational Digital Twin, and all underlying technology, algorithms, interfaces, and documentation are the exclusive intellectual property of Dot.Agents and its licensors. Nothing in these Terms transfers ownership of platform IP to you.</p>
                <p class="mb-3"><strong class="text-[#111111]">Trademarks.</strong> "Dot.Agents™", "Enterprise Brain™", "Executive Council™", "Organizational Digital Twin™", "Enterprise Memory Cortex™", "Digital Workforce™", "Social Commerce & Customer Success Intelligence™", "Organizational DNA™", "Adaptive Enterprise Consciousness™" and all associated logos are trademarks of Dot.Agents. You may not use these marks without prior written permission.</p>
                <p class="mb-3"><strong class="text-[#111111]">Your Content.</strong> You retain all rights to content, data, and configurations you create within the platform. You grant us the rights necessary to operate and improve the Service.</p>
                <p><strong class="text-[#111111]">Feedback.</strong> If you provide feedback or suggestions about the Service, we may use that feedback without restriction or compensation to you.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 8 --}}
            <div id="billing" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">8</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Billing & Payments</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">Subscription Fees.</strong> Access to paid features is subject to payment of applicable subscription fees as specified in your chosen plan. All fees are stated in your local currency or USD and are exclusive of applicable taxes.</p>
                <p class="mb-3"><strong class="text-[#111111]">Billing Cycle.</strong> Subscriptions are billed in advance on a monthly or annual basis depending on your selected plan. Fees are non-refundable except as required by applicable law or as expressly stated in these Terms.</p>
                <p class="mb-3"><strong class="text-[#111111]">Usage-Based Charges.</strong> Certain features (AI inference, token usage, social integrations) may incur usage-based charges in addition to base subscription fees. Usage is metered and billed monthly in arrears.</p>
                <p class="mb-3"><strong class="text-[#111111]">Late Payment.</strong> If payment fails, we will notify you and attempt to collect within 7 days. Failure to pay may result in suspension of Service without further notice.</p>
                <p><strong class="text-[#111111]">Price Changes.</strong> We will provide at least 30 days' notice of any price changes via email to your account's billing contact.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 9 --}}
            <div id="suspension" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">9</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Suspension & Termination</h2>
                </div>
                <p class="mb-3"><strong class="text-[#111111]">By You.</strong> You may cancel your subscription at any time through the billing settings. Cancellation takes effect at the end of the current billing period.</p>
                <p class="mb-3"><strong class="text-[#111111]">By Us.</strong> We may suspend or terminate your access immediately and without notice if: (a) you breach these Terms; (b) we determine your use poses a security risk; (c) required by law; or (d) we reasonably believe termination is necessary to protect the platform or other users.</p>
                <p class="mb-3"><strong class="text-[#111111]">Effect of Termination.</strong> Upon termination, your right to access the Service ceases immediately. You may request an export of your Customer Data within 30 days of termination. After 90 days, Customer Data is permanently deleted.</p>
                <p><strong class="text-[#111111]">Survival.</strong> Sections 6, 7, 10, 11, 12, and 13 survive termination of these Terms.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 10 --}}
            <div id="disclaimers" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">10</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Disclaimers & Warranties</h2>
                </div>
                <div class="bg-[#f9f9f7] border border-[#e8e8e2] rounded-xl p-5 mb-4">
                    <p class="text-xs font-semibold text-[#555550] uppercase tracking-wide mb-2">Important</p>
                    <p>THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTY OF ANY KIND. TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, DOT.AGENTS EXPRESSLY DISCLAIMS ALL WARRANTIES, WHETHER EXPRESS, IMPLIED, STATUTORY OR OTHERWISE, INCLUDING IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, AND NON-INFRINGEMENT.</p>
                </div>
                <p class="mb-3">We do not warrant that: (a) the Service will be uninterrupted or error-free; (b) AI outputs will be accurate, complete, or suitable for your purposes; (c) defects will be corrected; or (d) the Service is free of viruses or harmful components.</p>
                <p>You acknowledge that AI systems, by their nature, may produce outputs that are inaccurate, misleading, or inappropriate. You assume full responsibility for how you use and act upon AI-generated outputs.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 11 --}}
            <div id="liability" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">11</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Limitation of Liability</h2>
                </div>
                <div class="bg-[#f9f9f7] border border-[#e8e8e2] rounded-xl p-5 mb-4">
                    <p>TO THE MAXIMUM EXTENT PERMITTED BY LAW, DOT.AGENTS SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, PUNITIVE, OR EXEMPLARY DAMAGES, INCLUDING LOSS OF PROFITS, DATA, REVENUE, GOODWILL, OR BUSINESS INTERRUPTION, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.</p>
                </div>
                <p class="mb-3">Our total cumulative liability to you for all claims arising out of or relating to these Terms or the Service shall not exceed the greater of: (a) the total fees paid by you in the 12 months immediately preceding the claim; or (b) USD $100.</p>
                <p>Some jurisdictions do not allow the exclusion of certain warranties or the limitation of liability for certain types of damages. In such jurisdictions, our liability is limited to the maximum extent permitted by law.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 12 --}}
            <div id="indemnification" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">12</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Indemnification</h2>
                </div>
                <p class="mb-3">You agree to indemnify, defend, and hold harmless Dot.Agents and its officers, directors, employees, and agents from and against any claims, liabilities, damages, losses, costs, and expenses (including reasonable legal fees) arising out of or in connection with:</p>
                <ul class="list-none space-y-2 pl-4">
                    @foreach([
                        'Your use of or access to the Service',
                        'Your violation of these Terms',
                        'Your violation of any third-party rights',
                        'Any content or data you submit to the Service',
                        'Actions taken by AI agents you deploy on the platform',
                    ] as $item)
                        <li class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#453798] mt-1.5 flex-shrink-0"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 13 --}}
            <div id="governing-law" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">13</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Governing Law & Disputes</h2>
                </div>
                <p class="mb-3">These Terms are governed by and construed in accordance with the laws of the Republic of South Africa, without regard to conflict of law principles.</p>
                <p class="mb-3">Any dispute arising out of or in connection with these Terms, including any question regarding its existence, validity, or termination, shall first be submitted to good faith negotiation between the parties. If not resolved within 30 days, disputes shall be referred to mediation before seeking any other remedy.</p>
                <p>If you are accessing the Service from outside South Africa, you are responsible for compliance with your local laws.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 14 --}}
            <div id="changes" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">14</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Changes to Terms</h2>
                </div>
                <p class="mb-3">We may update these Terms from time to time. When we make material changes, we will notify you by email and display a notice within the platform at least 14 days before the changes take effect.</p>
                <p>Your continued use of the Service after the effective date of updated Terms constitutes acceptance of the new Terms. If you do not agree to the updated Terms, you must stop using the Service before the effective date.</p>
            </div>

            <div class="border-b border-[#f3f3ef] mb-12"></div>

            {{-- 15 --}}
            <div id="contact" class="scroll-mt-24 mb-12">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-8 h-8 rounded-lg bg-[#150f2e] text-[#f0c23a] text-xs font-black flex items-center justify-center flex-shrink-0">15</span>
                    <h2 class="text-xl font-black font-display text-[#111111]">Contact</h2>
                </div>
                <p class="mb-5">If you have any questions about these Terms, please contact us:</p>
                <div class="da-card p-6 grid sm:grid-cols-3 gap-4">
                    @foreach([
                        ['label' => 'Legal',    'value' => 'legal@dotagents.com'],
                        ['label' => 'Security', 'value' => 'security@dotagents.com'],
                        ['label' => 'Support',  'value' => 'support@dotagents.com'],
                    ] as $contact)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-[#453798] mb-1">{{ $contact['label'] }}</p>
                            <a href="mailto:{{ $contact['value'] }}" class="text-sm text-[#555550] hover:text-[#453798] transition-colors">{{ $contact['value'] }}</a>
                        </div>
                    @endforeach
                </div>
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
                <a href="{{ route('terms.show') }}" class="text-sm text-[#453798] font-medium">Terms</a>
                @if (Route::has('policy.show'))
                    <a href="{{ route('policy.show') }}" class="text-sm text-[#909088] hover:text-[#111111] transition-colors">Privacy</a>
                @endif
                <a href="/" class="text-sm text-[#909088] hover:text-[#111111] transition-colors">Home</a>
            </div>
            <p class="text-sm text-[#909088]">&copy; {{ date('Y') }} Dot.Agents. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>
