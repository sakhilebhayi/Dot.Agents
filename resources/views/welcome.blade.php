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
        <a href="#job">The job</a>
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
                    <a class="button hero-signin" href="{{ route('login') }}">Sign in</a>
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
                    <button type="button" class="answer yes" data-answer="yes">Approve</button>
                    <button type="button" class="answer no" data-answer="no">Reject</button>
                </div>
            </div>
            <p class="demo-note">An example you can press. Nothing here is real or sent.</p>
        </div>
    </section>

    <div class="run" data-run>
        <section class="band split" id="job" data-reach>
            <div>
                <h2>Every agent starts with a job.</h2>
                <p class="band-lede">Describe the work in your own words, start from a template, or build it step by step. A named person answers for it, and it stays a draft until someone publishes it.</p>
                <p class="band-lede">Change it whenever you like. Live work keeps running on the published version, and you can always go back to an older one.</p>
            </div>
            <figure class="agent">
                <figcaption><b>Invoice checker</b><span class="on">On duty</span></figcaption>
                <dl>
                    <div><dt>Job</dt><dd>Compare each supplier invoice with the agreed price and tell accounts about any difference.</dd></div>
                    <div><dt>Answers to</dt><dd>Thandi Mokoena</dd></div>
                    <div><dt>Tools given</dt><dd>Read documents, search supplier terms, message a team member</dd></div>
                    <div><dt>Limits</dt><dd>12 steps a run, 15 minutes, a monthly budget</dd></div>
                    <div><dt>Running</dt><dd>Version 7, published by Thandi</dd></div>
                </dl>
                <p class="figure-note">An example agent.</p>
            </figure>
        </section>

        <section class="band split held" id="gate" data-reach>
            <div>
                <h2>It can only touch what you give it.</h2>
                <p class="band-lede">Listing a tool gives an agent nothing. You grant each one, and a business rule can take it away again.</p>
                <ul class="plain">
                    <li>The platform checks every tool call before it runs. The model has no say in that, whatever it was told.</li>
                    <li>Words from a web page, a document or a webhook arrive marked as outside data, so they can inform the work but never direct it.</li>
                    <li>Once an agent has read outside content, it needs a person before it changes anything.</li>
                    <li>Moving money, final decisions about a person and deleting what cannot be recovered always go to a person, at every level.</li>
                </ul>
            </div>
            <fieldset class="dial">
                <legend>How much may it do alone?</legend>
                <p class="dial-case">On invoice 4471, at this level:</p>
                <div class="dial-steps">
                    <label><input type="radio" name="level" value="1"><span>Assist</span></label>
                    <label><input type="radio" name="level" value="2"><span>Prepare</span></label>
                    <label><input type="radio" name="level" value="3" checked><span>With approval</span></label>
                    <label><input type="radio" name="level" value="4"><span>Controlled</span></label>
                </div>
                <ul class="dial-out">
                    <li data-level="1"><b>It tells you what it found.</b> It changes nothing and sends nothing.</li>
                    <li data-level="2"><b>It writes the note to accounts.</b> The note waits in a draft for a person to send.</li>
                    <li data-level="3"><b>It holds at the signal.</b> The note goes out once a person approves it, exactly as approved.</li>
                    <li data-level="4"><b>It sends the note itself,</b> because this is low risk and the run read nothing from outside. Paying the invoice would still stop for a person.</li>
                </ul>
            </fieldset>
        </section>

        <section class="band split" id="team" data-reach>
            <div>
                <h2>Work that takes more than one agent.</h2>
                <p class="band-lede">Connect agents, tools and people in order, side by side or down different paths, or put a supervisor over a team and let it hand out the work.</p>
                <p class="band-lede">A workflow is only finished when all of it worked. If one branch fails, it says which one and picks up from there.</p>
            </div>
            <figure class="branch">
                <svg viewBox="0 0 520 220" role="img" aria-label="An example workflow. Read the order, then check stock and check credit side by side. Stock passed. Credit is held for a person. Dispatch waits for both.">
                    <path class="done" d="M40 110 H110 C150 110 150 50 190 50 H300"/>
                    <path class="done" d="M110 110 C150 110 150 170 190 170 H300"/>
                    <path class="wait" d="M300 50 H340 C380 50 380 110 420 110 H480"/>
                    <path class="wait" d="M300 170 H340 C380 170 380 110 420 110"/>
                    <circle class="n-done" cx="40" cy="110" r="8"/>
                    <circle class="n-done" cx="300" cy="50" r="8"/>
                    <rect class="n-hold" x="291" y="161" width="18" height="18" rx="2" transform="rotate(45 300 170)"/>
                    <circle class="n-wait" cx="480" cy="110" r="8"/>
                    <text x="24" y="86">Read the order</text>
                    <text x="190" y="30">Check stock</text>
                    <text x="190" y="202">Check credit</text>
                    <text class="t-hold" x="326" y="202">Waiting for a person</text>
                    <text x="428" y="86">Dispatch</text>
                </svg>
                <figcaption>An example workflow, held at one step.</figcaption>
            </figure>
        </section>

        <section class="band split" id="record" data-reach>
            <div>
                <h2>Every step is on the record.</h2>
                <p class="band-lede">See what started the work, what the agent asked for, what came back, who approved it and what it cost. When something fails, the record says what happened, where, and what you can do next.</p>
                <ul class="plain">
                    <li>Figures are counted from what actually ran, with their definitions beside them.</li>
                    <li>Costs come from dated prices, and spending that produced nothing is shown apart.</li>
                    <li>Try a new version against saved questions before it touches real work. A test changes nothing.</li>
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

        <section class="close" data-reach>
            <h2>Start with one agent and one job.</h2>
            <p>Dot.Agents is part of the Dot business platforms.</p>
            <a class="button solid" href="{{ route('register') }}">Set up your first agent</a>
        </section>
    </div>
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
