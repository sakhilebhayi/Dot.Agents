<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy · Dot.Agents</title>
    <meta name="description" content="Privacy Policy for Dot.Agents — the Adaptive Enterprise Operating System.">
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
        <h1>Privacy Policy</h1>
        <p class="legal-date">Last updated: September 19, 2026</p>
        <p class="legal-lede">
            Dot.Agents is built on a foundation of trust. We handle your data with the same governance standards
            we build into our platform — transparent, controlled, and accountable.
        </p>
    </header>

    <nav class="legal-toc" aria-labelledby="in-this-document">
        <h2 id="in-this-document">In this document</h2>
        <ol>
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
                <li><a href="{{ $href }}">{{ $label }}</a></li>
            @endforeach
        </ol>
    </nav>

    <div class="legal-body">
        <section id="who-we-are">
            <h2><span class="legal-num">1</span> Who We Are</h2>
            <p>Dot.Agents (<strong>"we"</strong>, <strong>"us"</strong>, <strong>"our"</strong>) is the operator of the Dot.Agents Adaptive Enterprise Operating System. We are the data controller for personal information processed in connection with the Service.</p>
            <p>This Privacy Policy explains how we collect, use, store, protect, and share information when you access or use our platform at <strong>dotagents.com</strong> and all associated subdomains, APIs, and applications.</p>
        </section>

        <section id="what-we-collect">
            <h2><span class="legal-num">2</span> Information We Collect</h2>

            <h3>2.1 Information You Provide</h3>
            <ul>
                @foreach([
                    'Account registration data (name, email address, password)',
                    'Organization profile (company name, domain, industry, size)',
                    'Billing information (processed and stored securely by our payment provider)',
                    'Organizational DNA configuration (mission, vision, values, risk appetite)',
                    'Agent configurations, workflows, and deployment settings',
                    'Content submitted to agents (prompts, documents, messages)',
                    'Support communications and feedback',
                ] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

            <h3>2.2 Information We Collect Automatically</h3>
            <ul>
                @foreach([
                    'Log data (IP address, browser type, operating system, pages visited, timestamps)',
                    'Usage data (features accessed, agent interactions, workflow executions)',
                    'Session data (authentication tokens, session identifiers)',
                    'Device information (device type, screen resolution, timezone)',
                    'Performance data (API response times, error rates)',
                ] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

            <h3>2.3 Information from Third Parties</h3>
            <ul>
                @foreach([
                    'Social account data when you connect platforms (Facebook, Instagram, LinkedIn, WhatsApp Business, TikTok, X) — limited to what is necessary for agent operations',
                    'Identity data from SSO or OAuth providers if used for login',
                    'Payment verification data from our billing processor',
                ] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>

        <section id="how-we-use">
            <h2><span class="legal-num">3</span> How We Use Your Information</h2>

            <table class="legal-table">
                <thead>
                    <tr>
                        <th scope="col">Purpose</th>
                        <th scope="col">Data Used</th>
                        <th scope="col">Legal Basis</th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr>
                            <th scope="row">{{ $row[0] }}</th>
                            <td data-label="Data Used">{{ $row[1] }}</td>
                            <td data-label="Legal Basis">{{ $row[2] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section id="ai-data">
            <h2><span class="legal-num">4</span> AI Processing & Agent Data</h2>
            <p><strong>Agent Inputs.</strong> Content you submit to agents (prompts, documents, instructions) is processed by AI models to generate responses. This data is logged as part of the governance audit trail and may be subject to our delusion detection and moderation systems.</p>
            <p><strong>Decision Logs.</strong> Every agent decision is stored in a decision log linked to your organization. These logs contain: the input hash, the agent's output, confidence score, delusion risk score, and any approval or escalation events. Decision logs are retained as part of your governance record.</p>
            <p><strong>Enterprise Memory.</strong> Content stored in the Enterprise Memory Cortex is owned by your organization. Memory entries are scoped exclusively to your tenant and are not shared with other organizations or used to train shared AI models.</p>
            <p><strong>AI Model Providers.</strong> We use third-party AI model providers (including OpenAI) to power agent inference. Data sent to these providers is governed by their respective terms and privacy policies. We have data processing agreements in place with all AI model providers. We do not enable model training on your data by default.</p>
            <p><strong>Social Account Data.</strong> When you connect social platforms, we access only the permissions you explicitly grant. Social account tokens are encrypted at rest. We do not sell or share social account data with third parties outside of the connected platform's own API interactions.</p>
        </section>

        <section id="sharing">
            <h2><span class="legal-num">5</span> Sharing & Disclosure</h2>
            <p>We do not sell your personal data. We may share data only in the following circumstances:</p>
            <dl class="legal-list">
                @foreach([
                    ['title' => 'Service Providers',       'desc' => 'Trusted vendors who process data on our behalf under contractual data processing agreements (e.g., payment processors, cloud hosting, AI model providers, email delivery, error monitoring).'],
                    ['title' => 'Legal Requirements',      'desc' => 'When required by law, court order, or government authority, or to protect the rights, property, or safety of Dot.Agents, our users, or others.'],
                    ['title' => 'Business Transfers',      'desc' => 'In connection with a merger, acquisition, or sale of assets, your data may be transferred. We will notify you before your data becomes subject to a different privacy policy.'],
                    ['title' => 'With Your Consent',       'desc' => 'For any other purpose with your explicit prior consent.'],
                    ['title' => 'Aggregated / Anonymized', 'desc' => 'We may share anonymized, aggregated usage statistics that cannot reasonably identify you.'],
                ] as $item)
                    <div>
                        <dt>{{ $item['title'] }}</dt>
                        <dd>{{ $item['desc'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section id="international">
            <h2><span class="legal-num">6</span> International Transfers</h2>
            <p>Dot.Agents operates globally. Your data may be stored and processed in countries outside your country of residence, including South Africa, the United States, and the European Union.</p>
            <p>For transfers involving personal data of individuals in the European Economic Area (EEA) or United Kingdom, we rely on Standard Contractual Clauses approved by the European Commission or equivalent mechanisms.</p>
            <p>For South African residents, we comply with the <strong>Protection of Personal Information Act (POPIA)</strong> and the cross-border transfer provisions of POPIA Section 72.</p>
        </section>

        <section id="retention">
            <h2><span class="legal-num">7</span> Data Retention</h2>
            <table class="legal-table">
                <thead>
                    <tr>
                        <th scope="col">Data Type</th>
                        <th scope="col">Retention Period</th>
                        <th scope="col">Deletion</th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr>
                            <th scope="row">{{ $row[0] }}</th>
                            <td data-label="Retention Period">{{ $row[1] }}</td>
                            <td data-label="Deletion">{{ $row[2] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section id="security">
            <h2><span class="legal-num">8</span> Security</h2>
            <p>We use these controls to protect your data:</p>
            <ul>
                @foreach([
                    'Encryption in transit, and of credentials and agent instructions in storage',
                    'Multi-factor authentication support',
                    'Each business\'s data kept apart by application controls, tested on every release',
                    'Role-based access with seven roles',
                    'Scheduled security monitoring',
                    'Automated dependency scanning',
                    'Encrypted storage of social API tokens and credentials',
                ] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            <p>In the event of a personal data breach, we will notify affected users and relevant supervisory authorities within the timeframes required by applicable law.</p>
        </section>

        <section id="your-rights">
            <h2><span class="legal-num">9</span> Your Rights</h2>
            <p>Depending on your jurisdiction, you may have the following rights regarding your personal data:</p>
            <dl class="legal-list">
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
                    <div>
                        <dt>{{ $item['right'] }}</dt>
                        <dd>{{ $item['desc'] }}</dd>
                    </div>
                @endforeach
            </dl>
            <p>To exercise your rights, contact our Data Protection Officer at <a href="mailto:privacy@dotagents.com">privacy@dotagents.com</a>. We will respond within 30 days (or as required by applicable law).</p>
        </section>

        <section id="cookies">
            <h2><span class="legal-num">10</span> Cookies & Tracking</h2>
            <p>We use the following categories of cookies and similar technologies:</p>
            <dl class="legal-list">
                @foreach([
                    ['type' => 'Strictly Necessary', 'desc' => 'Required for the platform to function — session management, authentication, CSRF protection. Cannot be disabled.'],
                    ['type' => 'Functional',         'desc' => 'Remembers your preferences (language, theme, dashboard state). Can be disabled without loss of core functionality.'],
                    ['type' => 'Analytics',          'desc' => 'Aggregated usage statistics to understand how the platform is used and where to improve it. You may opt out.'],
                    ['type' => 'Security',           'desc' => 'Fraud detection and abuse prevention. Required for platform security.'],
                ] as $cookie)
                    <div>
                        <dt>{{ $cookie['type'] }}</dt>
                        <dd>{{ $cookie['desc'] }}</dd>
                    </div>
                @endforeach
            </dl>
            <p>You can manage cookie preferences through your browser settings. Note that disabling certain cookies may affect platform functionality.</p>
        </section>

        <section id="children">
            <h2><span class="legal-num">11</span> Children's Privacy</h2>
            <p>The Service is not directed to individuals under the age of 18. We do not knowingly collect personal data from children. If you become aware that a child has provided us with personal data, please contact us at <a href="mailto:privacy@dotagents.com">privacy@dotagents.com</a> and we will take steps to delete such data.</p>
        </section>

        <section id="third-party">
            <h2><span class="legal-num">12</span> Third-Party Services</h2>
            <p>The platform integrates with third-party services. When you connect or use these services, your data may be processed by those third parties under their own privacy policies:</p>
            <dl class="legal-list">
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
                    <div>
                        <dt>{{ $service['name'] }}</dt>
                        <dd>{{ $service['purpose'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section id="changes">
            <h2><span class="legal-num">13</span> Changes to This Policy</h2>
            <p>We may update this Privacy Policy periodically. When we make material changes, we will notify you by email and post a notice within the platform at least 14 days before the changes take effect.</p>
            <p>The "Last updated" date at the top of this page reflects when changes were last made. Continued use of the Service after the effective date constitutes acceptance of the updated Policy.</p>
        </section>

        <section id="contact">
            <h2><span class="legal-num">14</span> Contact & Data Protection Officer</h2>
            <p>For privacy enquiries, rights requests, or to reach our Data Protection Officer:</p>
            <dl class="legal-list">
                @foreach([
                    ['label' => 'Privacy / DPO', 'value' => 'privacy@dotagents.com',  'desc'  => 'Data rights requests, privacy concerns'],
                    ['label' => 'Security',       'value' => 'security@dotagents.com', 'desc'  => 'Security incidents, breach reports'],
                    ['label' => 'Legal',          'value' => 'legal@dotagents.com',    'desc'  => 'Regulatory, compliance enquiries'],
                ] as $contact)
                    <div>
                        <dt>{{ $contact['label'] }}</dt>
                        <dd>
                            <a href="mailto:{{ $contact['value'] }}">{{ $contact['value'] }}</a>
                            <small>{{ $contact['desc'] }}</small>
                        </dd>
                    </div>
                @endforeach
            </dl>
            <p>South African residents may also lodge a complaint with the <strong>Information Regulator (South Africa)</strong> at <a href="https://inforegulator.org.za" target="_blank" rel="noopener noreferrer">inforegulator.org.za</a>.</p>
        </section>
    </div>
</main>

<footer class="foot">
    <span>&copy; {{ date('Y') }} Dot.Agents. All rights reserved.</span>
    <nav aria-label="Legal">
        <a href="{{ route('terms.show') }}">Terms</a>
        <a href="{{ route('policy.show') }}" aria-current="page">Privacy</a>
        <a href="/">Home</a>
    </nav>
</footer>
</body>
</html>
