<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $header ?? 'Dot.Agents' }}</title>
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('dot-agents-theme'); } catch (e) {}
            if (stored === 'light') {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/css/signal.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="signal" x-data="shell">
<a class="skip" href="#content">Skip to content</a>

@php
    $viewer = auth()->user();
    $organization = $viewer?->organizations()->where('organizations.id', \App\Support\TenantContext::id())->first();
    $waiting = $organization ? \App\Models\AgentApproval::query()->where('status', 'pending')->count() : 0;
    $nav = [
        'Today' => [
            ['dashboard', 'dashboard', 'panel', 'Command centre'],
            ['governance.approvals', 'governance.approvals*', 'approve', 'Approvals', $waiting],
            ['runs.index', 'runs.*', 'run', 'Runs'],
        ],
        'Workforce' => [
            ['agents.deployments', 'agents.*', 'agents', 'Agents'],
            ['agents.teams', 'agents.teams', 'team', 'Teams'],
            ['org.departments', 'org.departments*', 'team', 'Departments'],
            ['marketplace', 'marketplace*', 'template', 'Templates'],
        ],
        'Build' => [
            ['workflows.index', 'workflows.*', 'workflow', 'Workflows'],
            ['org.knowledge', 'org.knowledge*', 'knowledge', 'Knowledge'],
            ['org.skills', 'org.skills*', 'tool', 'Custom tools'],
        ],
        'Oversight' => [
            ['org.memory', 'org.memory*', 'memory', 'Memory'],
            ['governance.decisions', 'governance.decisions*', 'decision', 'Decisions'],
            ['governance.audit', 'governance.audit*', 'audit', 'Audit log'],
            ['security.center', 'security.*', 'shield', 'Security'],
        ],
        'Business' => [
            ['org.members', 'org.members*', 'people', 'People'],
            ['usage', 'usage', 'usage', 'Usage'],
            ['billing.index', 'billing.*', 'billing', 'Billing'],
            ['org.settings', 'org.settings*', 'settings', 'Settings'],
        ],
    ];
@endphp

<div class="shell">
    <div class="veil" x-show="menu" x-cloak x-on:click="menu = false"></div>

    <aside class="side" :class="{ open: menu }" aria-label="Dot.Agents">
        <a class="side-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/mark.png') }}" alt=""> Dot.Agents
        </a>
        <div class="side-org">
            <b>{{ $organization?->name ?? 'No business selected' }}</b>
            <span>{{ \App\Support\OrgRoles::LABELS[$viewer?->roleInOrganization($organization?->id)] ?? 'Member' }}</span>
        </div>
        <nav class="side-nav" aria-label="Main">
            @foreach($nav as $group => $items)
                <div class="side-group">{{ $group }}</div>
                @foreach($items as $item)
                    <a class="side-item" href="{{ route($item[0]) }}" @if(request()->routeIs($item[1])) aria-current="page" @endif>
                        <x-sig.icon :name="$item[2]" /> {{ $item[3] }}
                        @if(($item[4] ?? 0) > 0)<span class="count">{{ $item[4] }}</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="side-foot">
            <img src="{{ $viewer?->profile_photo_url }}" alt="">
            <a class="who" href="{{ route('profile.show') }}" style="text-decoration:none">
                <b>{{ $viewer?->name }}</b>
                <span>Your account</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="icon-btn" aria-label="Sign out"><x-sig.icon name="out" /></button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="top">
            <button type="button" class="icon-btn menu-btn" aria-label="Open the menu" x-on:click="menu = true"><x-sig.icon name="menu" /></button>
            @isset($header)<div class="top-title">{{ $header }}</div>@endisset
            <div class="top-space"></div>
            @livewire('notification-bell')
            <button type="button" class="icon-btn" aria-label="Switch between light and dark" x-on:click="toggleTheme"><x-sig.icon name="moon" /></button>
        </header>

        @if(session('success'))<div class="page" style="padding-bottom:0"><div class="notice clear" role="status">{{ session('success') }}</div></div>@endif
        @if(session('error'))<div class="page" style="padding-bottom:0"><div class="notice stop" role="alert">{{ session('error') }}</div></div>@endif

        <main id="content">{{ $slot }}</main>
    </div>
</div>

@stack('modals')
@livewireScripts
</body>
</html>
