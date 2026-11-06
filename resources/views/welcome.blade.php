<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dot.Agents. AI agents that stop and ask</title>
    <meta name="description" content="Give your business AI agents that do real work with the tools you allow, and hold at a signal whenever something needs a person.">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Dot.Agents. AI agents that stop and ask">
    <meta property="og:description" content="Agents that do real work with the tools you allow, and hold at a signal whenever something needs a person.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ url('/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'Dot.Agents', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'url' => url('/'), 'description' => 'AI agents for business that do real work with the tools you allow, and stop for a person when it matters.'], JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<header class="bar">
    <a class="brand" href="/" aria-label="Dot.Agents home"><img src="{{ asset('images/mark.png') }}" alt="" width="24" height="24"><span>Dot.Agents</span></a>
    <nav aria-label="Main">
        <a href="#signal">The signal</a>
        <a href="#gate">What it can touch</a>
        <a href="#record">The record</a>
        <a class="signin" href="{{ route('login') }}">Sign in</a>
    </nav>
</header>

<main id="main">
    <section class="hero" id="signal">
        <div class="hero-top">
            <h1>Agents that <span>stop and ask.</span></h1>
            <div>
                <p class="lede">Dot.Agents gives your business AI agents that do real work, with only the tools you allow. When something needs a person, the work holds at a signal until one answers.</p>
                <div class="hero-actions">
                    <a class="button solid" href="{{ route('register') }}">Set up your first agent</a>
                    <a class="button" href="{{ route('login') }}">Sign in</a>
                </div>
            </div>
        </div>

        <div class="demo" data-demo>
            <div class="demo-head">
                <span>Invoice check</span>
                <span class="demo-state" data-state role="status">Running</span>
            </div>
            <ol class="line" aria-label="One piece of work, step by step">
                <li class="stop" data-stop>Read the invoice</li>
                <li class="stop" data-stop>Add up the lines</li>
                <li class="stop" data-stop>Check our terms</li>
                <li class="stop held" data-stop data-hold>Tell accounts</li>
                <li class="stop" data-stop>Done</li>
            </ol>
            <div class="card" data-card hidden>
                <p class="card-what">It wants to send a message</p>
                <dl>
                    <div><dt>To</dt><dd>Thandi Mokoena, Accounts</dd></div>
                    <div><dt>Message</dt><dd>Invoice 4471 is R1,200 over the agreed price on line 3.</dd></div>
                    <div><dt>If you say no</dt><dd>Nothing is sent, and the agent is told why.</dd></div>
                </dl>
                <div class="card-actions">
                    <button type="button" class="button solid" data-answer="yes">Approve</button>
                    <button type="button" class="button" data-answer="no">Reject</button>
                </div>
            </div>
            <p class="demo-note">An example you can press. Nothing here is real or sent.</p>
        </div>
    </section>

    <section class="band" id="job">
        <h2>Give it a job, not a chat box.</h2>
        <p class="band-lede">Describe the work in your own words, start from a template, or build it step by step. Every agent has a job, an owner and limits you set.</p>
        <dl class="facts">
            <div><dt>A responsibility</dt><dd>One clear piece of work, in plain words. A vague job gets flagged before it goes live.</dd></div>
            <div><dt>An owner</dt><dd>A named person answers for every agent.</dd></div>
            <div><dt>Limits</dt><dd>Steps, tool calls, spending and time for each run, and a monthly budget for the business.</dd></div>
            <div><dt>Versions</dt><dd>Change an agent as much as you like. Nothing reaches live work until you publish, and you can go back.</dd></div>
        </dl>
    </section>

    <section class="band split" id="gate">
        <div>
            <h2>It can only touch what you give it.</h2>
            <p class="band-lede">Being on the list of tools gives an agent nothing. You grant each one, and a business rule can take it away again.</p>
            <ul class="plain">
                <li>The check runs in the platform before every tool call. The model has no say in it, whatever it was told.</li>
                <li>Words from a web page, a document or a webhook reach an agent only as marked data. They can inform it. They cannot instruct it.</li>
                <li>Once an agent has read outside content, it needs a person before it changes anything.</li>
                <li>Moving money, final decisions about a person, and deleting what cannot be recovered always go to a person, at every level.</li>
            </ul>
        </div>
        <figure class="levels" aria-label="The four levels of autonomy">
            <figcaption>How much it may do alone is up to you.</figcaption>
            <ol>
                <li><b>Assist</b>Suggests what to do. Changes nothing.</li>
                <li><b>Prepare</b>Prepares work for a person to send.</li>
                <li><b>Execute with approval</b>Acts after a person approves.</li>
                <li><b>Controlled autonomy</b>Carries out low-risk actions on its own, inside its limits.</li>
            </ol>
        </figure>
    </section>

    <section class="band split" id="team">
        <div>
            <h2>Work that takes more than one agent.</h2>
            <p class="band-lede">Connect agents, tools and people into a workflow, in order, side by side or down different paths. Or put a supervisor over a team and let it hand out the work.</p>
        </div>
        <p class="claim">A workflow only counts as done when the whole of it worked. One agent finishing proves nothing.</p>
    </section>

    <section class="band split" id="record">
        <div>
            <h2>Every step is on the record.</h2>
            <p class="band-lede">What started the work, what the agent asked for, what came back, who approved it and what it cost. When something fails, it says what, where, and what you can do.</p>
            <ul class="plain">
                <li>Figures are counted from what actually ran, with their definitions beside them.</li>
                <li>Costs come from dated prices, and spending that produced nothing is shown apart.</li>
                <li>Test a new version against saved questions before it touches real work. A test changes nothing.</li>
            </ul>
        </div>
        <figure class="record-figure">
            <figcaption>An example of one run</figcaption>
            <ol class="record">
            <li><b>Started by a schedule</b><span>Monday, 08:00</span></li>
            <li><b>Searched the supplier terms</b><span>Source: Supplier terms, passage 4</span></li>
            <li class="hold"><b>Waited for a person</b><span>Thandi approved in 12 minutes</span></li>
            <li><b>Sent the message</b><span>Exactly as approved</span></li>
            <li><b>Finished</b><span>Version 7 of the agent</span></li>
            </ol>
        </figure>
    </section>

    <section class="close">
        <h2>Start with one agent and one job.</h2>
        <p>Dot.Agents is part of the Dot business platforms. You sign in with your InfoDot account.</p>
        <a class="button solid" href="{{ route('register') }}">Set up your first agent</a>
    </section>
</main>

<footer class="foot">
    <span>Dot.Agents</span>
    <nav aria-label="Legal">
        <a href="{{ route('terms.show') }}">Terms</a>
        <a href="{{ route('policy.show') }}">Privacy</a>
    </nav>
</footer>
</body>
</html>
