<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service · Dot.Agents</title>
    <meta name="description" content="Terms of Service for Dot.Agents — the Adaptive Enterprise Operating System.">
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/landing.css'])
    @endif
</head>
<body class="doc-page">
<a class="skip" href="#main">Skip to content</a>

<header class="bar">
    <a class="brand" href="/" aria-label="Dot.Agents home"><img src="{{ asset('images/mark.png') }}" alt="" width="24" height="24"><span>Dot.Agents</span></a>
    <nav aria-label="Main">
        @auth
            <a class="button" href="{{ url('/dashboard') }}">Open platform</a>
        @else
            <a class="signin" href="{{ route('login') }}">Sign in</a>
            @if (Route::has('register'))
                <a class="button solid" href="{{ route('register') }}">Get started</a>
            @endif
        @endauth
    </nav>
</header>

<main id="main" class="legal">
    <header class="legal-head">
        <h1>Terms of Service</h1>
        <p class="legal-date">Last updated: September 19, 2026</p>
    </header>

    <nav class="legal-toc" aria-labelledby="in-this-document">
        <h2 id="in-this-document">In this document</h2>
        <ol>
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
                ['#governing-law',     '13. Governing Law & Disputes'],
                ['#changes',           '14. Changes to Terms'],
                ['#contact',           '15. Contact'],
            ] as [$href, $label])
                <li><a href="{{ $href }}">{{ $label }}</a></li>
            @endforeach
        </ol>
    </nav>

    <div class="legal-body">
        <section id="acceptance">
            <h2><span class="legal-num">1</span> Acceptance of Terms</h2>
            <p>By accessing or using the Dot.Agents platform (the <strong>"Service"</strong>), you (<strong>"Customer"</strong> or <strong>"User"</strong>) agree to be bound by these Terms of Service (<strong>"Terms"</strong>). If you are entering into these Terms on behalf of an organization, you represent and warrant that you have the authority to bind that organization.</p>
            <p>If you do not agree to these Terms, you must not access or use the Service.</p>
            <p>These Terms incorporate our <a href="{{ route('policy.show') }}">Privacy Policy</a> by reference.</p>
        </section>

        <section id="description">
            <h2><span class="legal-num">2</span> Description of Service</h2>
            <p>Dot.Agents is an <strong>Adaptive Enterprise Operating System</strong> that enables organizations to deploy, manage, govern, and scale AI-powered digital workforces. The Service includes:</p>
            <ul>
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
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            <p>We reserve the right to modify, suspend, or discontinue any part of the Service at any time with reasonable notice.</p>
        </section>

        <section id="accounts">
            <h2><span class="legal-num">3</span> Accounts & Access</h2>
            <p><strong>Registration.</strong> You must provide accurate, complete, and current information when creating an account. You are responsible for maintaining the confidentiality of your credentials.</p>
            <p><strong>Organizations.</strong> The Service supports multi-tenant organizations. The account owner (<strong>"Organization Admin"</strong>) is responsible for all activity that occurs under their organization, including the actions of all members they invite.</p>
            <p><strong>Security.</strong> You must notify us immediately at <a href="mailto:security@dotagents.com">security@dotagents.com</a> upon becoming aware of any unauthorized access to your account.</p>
            <p><strong>Age.</strong> You must be at least 18 years old and have the legal capacity to enter into a binding contract to use the Service.</p>
        </section>

        <section id="acceptable-use">
            <h2><span class="legal-num">4</span> Acceptable Use</h2>
            <p>You agree not to use the Service to:</p>
            <ul>
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
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            <p>We may suspend or terminate access immediately for violations of this section.</p>
        </section>

        <section id="ai-governance">
            <h2><span class="legal-num">5</span> AI Governance & Responsibility</h2>
            <p><strong>AI Outputs.</strong> AI-generated outputs from agents deployed on the platform are provided for informational and operational assistance purposes. You are responsible for reviewing, validating, and making final decisions on any agent output, particularly in regulated industries (finance, healthcare, legal, etc.).</p>
            <p><strong>Autonomous Agents.</strong> When deploying agents in autonomous or semi-autonomous modes, you accept that agent actions are executed on your behalf. You remain fully responsible for the consequences of those actions within your organization and externally.</p>
            <p><strong>Confidence Thresholds.</strong> The platform provides configurable confidence thresholds and approval workflows. You are responsible for setting appropriate thresholds for your use case and risk tolerance.</p>
            <p><strong>Delusion Detection.</strong> The platform includes automated hallucination and delusion risk scoring. While we make reasonable efforts to flag high-risk outputs, we do not guarantee that all inaccurate AI outputs will be detected.</p>
            <p><strong>Human Oversight.</strong> Dot.Agents is designed to augment human decision-making, not replace it entirely. For critical business, legal, financial, or safety decisions, human review is required before acting on agent recommendations.</p>
        </section>

        <section id="data">
            <h2><span class="legal-num">6</span> Data & Privacy</h2>
            <p><strong>Your Data.</strong> You retain ownership of all data you submit to the Service (<strong>"Customer Data"</strong>). You grant us a limited, non-exclusive licence to process Customer Data solely to provide the Service.</p>
            <p><strong>Data Isolation.</strong> The platform enforces strict multi-tenant data isolation. Customer Data is scoped to your organization and is not accessible by other organizations.</p>
            <p><strong>AI Training.</strong> We do not use your Customer Data to train our base AI models without your explicit written consent.</p>
            <p><strong>Retention.</strong> We retain Customer Data for the duration of your subscription and for a period of 90 days following termination, after which it is permanently deleted unless legal obligations require otherwise.</p>
            <p>Our full data handling practices are described in our <a href="{{ route('policy.show') }}">Privacy Policy</a>.</p>
        </section>

        <section id="ip">
            <h2><span class="legal-num">7</span> Intellectual Property</h2>
            <p><strong>Platform IP.</strong> The Dot.Agents platform, including its Enterprise Brain, Executive Council, Organizational Digital Twin, and all underlying technology, algorithms, interfaces, and documentation are the exclusive intellectual property of Dot.Agents and its licensors. Nothing in these Terms transfers ownership of platform IP to you.</p>
            <p><strong>Trademarks.</strong> "Dot.Agents™", "Enterprise Brain™", "Executive Council™", "Organizational Digital Twin™", "Enterprise Memory Cortex™", "Digital Workforce™", "Social Commerce & Customer Success Intelligence™", "Organizational DNA™", "Adaptive Enterprise Consciousness™" and all associated logos are trademarks of Dot.Agents. You may not use these marks without prior written permission.</p>
            <p><strong>Your Content.</strong> You retain all rights to content, data, and configurations you create within the platform. You grant us the rights necessary to operate and improve the Service.</p>
            <p><strong>Feedback.</strong> If you provide feedback or suggestions about the Service, we may use that feedback without restriction or compensation to you.</p>
        </section>

        <section id="billing">
            <h2><span class="legal-num">8</span> Billing & Payments</h2>
            <p><strong>Subscription Fees.</strong> Access to paid features is subject to payment of applicable subscription fees as specified in your chosen plan. All fees are stated in your local currency or USD and are exclusive of applicable taxes.</p>
            <p><strong>Billing Cycle.</strong> Subscriptions are billed in advance on a monthly or annual basis depending on your selected plan. Fees are non-refundable except as required by applicable law or as expressly stated in these Terms.</p>
            <p><strong>Usage-Based Charges.</strong> Certain features (AI inference, token usage, social integrations) may incur usage-based charges in addition to base subscription fees. Usage is metered and billed monthly in arrears.</p>
            <p><strong>Late Payment.</strong> If payment fails, we will notify you and attempt to collect within 7 days. Failure to pay may result in suspension of Service without further notice.</p>
            <p><strong>Price Changes.</strong> We will provide at least 30 days' notice of any price changes via email to your account's billing contact.</p>
        </section>

        <section id="suspension">
            <h2><span class="legal-num">9</span> Suspension & Termination</h2>
            <p><strong>By You.</strong> You may cancel your subscription at any time through the billing settings. Cancellation takes effect at the end of the current billing period.</p>
            <p><strong>By Us.</strong> We may suspend or terminate your access immediately and without notice if: (a) you breach these Terms; (b) we determine your use poses a security risk; (c) required by law; or (d) we reasonably believe termination is necessary to protect the platform or other users.</p>
            <p><strong>Effect of Termination.</strong> Upon termination, your right to access the Service ceases immediately. You may request an export of your Customer Data within 30 days of termination. After 90 days, Customer Data is permanently deleted.</p>
            <p><strong>Survival.</strong> Sections 6, 7, 10, 11, 12, and 13 survive termination of these Terms.</p>
        </section>

        <section id="disclaimers">
            <h2><span class="legal-num">10</span> Disclaimers & Warranties</h2>
            <div class="legal-box">
                <p class="legal-box-label">Important</p>
                <p>THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTY OF ANY KIND. TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, DOT.AGENTS EXPRESSLY DISCLAIMS ALL WARRANTIES, WHETHER EXPRESS, IMPLIED, STATUTORY OR OTHERWISE, INCLUDING IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, AND NON-INFRINGEMENT.</p>
            </div>
            <p>We do not warrant that: (a) the Service will be uninterrupted or error-free; (b) AI outputs will be accurate, complete, or suitable for your purposes; (c) defects will be corrected; or (d) the Service is free of viruses or harmful components.</p>
            <p>You acknowledge that AI systems, by their nature, may produce outputs that are inaccurate, misleading, or inappropriate. You assume full responsibility for how you use and act upon AI-generated outputs.</p>
        </section>

        <section id="liability">
            <h2><span class="legal-num">11</span> Limitation of Liability</h2>
            <div class="legal-box">
                <p>TO THE MAXIMUM EXTENT PERMITTED BY LAW, DOT.AGENTS SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, PUNITIVE, OR EXEMPLARY DAMAGES, INCLUDING LOSS OF PROFITS, DATA, REVENUE, GOODWILL, OR BUSINESS INTERRUPTION, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.</p>
            </div>
            <p>Our total cumulative liability to you for all claims arising out of or relating to these Terms or the Service shall not exceed the greater of: (a) the total fees paid by you in the 12 months immediately preceding the claim; or (b) USD $100.</p>
            <p>Some jurisdictions do not allow the exclusion of certain warranties or the limitation of liability for certain types of damages. In such jurisdictions, our liability is limited to the maximum extent permitted by law.</p>
        </section>

        <section id="indemnification">
            <h2><span class="legal-num">12</span> Indemnification</h2>
            <p>You agree to indemnify, defend, and hold harmless Dot.Agents and its officers, directors, employees, and agents from and against any claims, liabilities, damages, losses, costs, and expenses (including reasonable legal fees) arising out of or in connection with:</p>
            <ul>
                @foreach([
                    'Your use of or access to the Service',
                    'Your violation of these Terms',
                    'Your violation of any third-party rights',
                    'Any content or data you submit to the Service',
                    'Actions taken by AI agents you deploy on the platform',
                ] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>

        <section id="governing-law">
            <h2><span class="legal-num">13</span> Governing Law & Disputes</h2>
            <p>These Terms are governed by and construed in accordance with the laws of the Republic of South Africa, without regard to conflict of law principles.</p>
            <p>Any dispute arising out of or in connection with these Terms, including any question regarding its existence, validity, or termination, shall first be submitted to good faith negotiation between the parties. If not resolved within 30 days, disputes shall be referred to mediation before seeking any other remedy.</p>
            <p>If you are accessing the Service from outside South Africa, you are responsible for compliance with your local laws.</p>
        </section>

        <section id="changes">
            <h2><span class="legal-num">14</span> Changes to Terms</h2>
            <p>We may update these Terms from time to time. When we make material changes, we will notify you by email and display a notice within the platform at least 14 days before the changes take effect.</p>
            <p>Your continued use of the Service after the effective date of updated Terms constitutes acceptance of the new Terms. If you do not agree to the updated Terms, you must stop using the Service before the effective date.</p>
        </section>

        <section id="contact">
            <h2><span class="legal-num">15</span> Contact</h2>
            <p>If you have any questions about these Terms, please contact us:</p>
            <dl class="legal-list">
                @foreach([
                    ['label' => 'Legal',    'value' => 'legal@dotagents.com'],
                    ['label' => 'Security', 'value' => 'security@dotagents.com'],
                    ['label' => 'Support',  'value' => 'support@dotagents.com'],
                ] as $contact)
                    <div>
                        <dt>{{ $contact['label'] }}</dt>
                        <dd><a href="mailto:{{ $contact['value'] }}">{{ $contact['value'] }}</a></dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</main>

<footer class="foot">
    <span>&copy; {{ date('Y') }} Dot.Agents. All rights reserved.</span>
    <nav aria-label="Legal">
        <a href="{{ route('terms.show') }}" aria-current="page">Terms</a>
        <a href="{{ route('policy.show') }}">Privacy</a>
        <a href="/">Home</a>
    </nav>
</footer>
</body>
</html>
