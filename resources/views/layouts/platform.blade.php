<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark dot-roboto" data-dh-theme-class="dark" data-dh-theme-key="dot-agents-theme" data-dh-theme-default="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim(strip_tags((string) ($title ?? $header ?? 'Dot.Agents'))) }}</title>
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    {{-- Theme first, before any paint: night is the default, a stored choice wins (dot-ui reads the data-dh-theme-* attributes on <html>). --}}
    <script src="{{ asset('dot-ui/js/dot-theme-boot.js') }}"></script>
    {{-- Shared Dot UI (Roboto, type scale, header, search, switcher), served from this site's own public/dot-ui: no CDN, no inline script. --}}
    <link rel="preload" href="{{ asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-roboto.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-type.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-search.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-switcher.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-header.css') }}">
    @vite(['resources/css/app.css', 'resources/css/signal.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="signal">

@php
    $viewer = auth()->user();
    $organization = $viewer?->organizations()->where('organizations.id', \App\Support\TenantContext::id())->first();
    $otherOrganizations = $viewer ? $viewer->organizations()->where('organizations.id', '!=', \App\Support\TenantContext::id())->orderBy('organizations.name')->get(['organizations.id', 'organizations.name']) : collect();
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

@php
    $account = $viewer ? [
        'name' => $viewer->name,
        'email' => $viewer->email,
        'team' => trim(($organization?->name ?? 'No business selected').' · '.(\App\Support\OrgRoles::LABELS[$viewer->roleInOrganization($organization?->id)] ?? 'Member')),
        'links' => array_values(array_filter([
            ['label' => 'Your account', 'href' => route('profile.show'), 'current' => request()->routeIs('profile.*')],
            ...(($otherOrganizations ?? collect())->isNotEmpty() ? [['heading' => 'Switch business']] : []),
            ...(($otherOrganizations ?? collect())->map(fn ($other) => ['label' => $other->name, 'form' => ['action' => route('organization.switch'), 'method' => 'POST', 'fields' => ['organization_id' => $other->id]]])->all()),
        ])),
        'signOut' => ['action' => route('logout')],
        'theme' => true,
    ] : null;
@endphp

{{-- One header over the whole app: logo, search, the platform switcher, notifications and the account. The sidebar below it is the
     platform's own navigation; on a phone the header's menu button opens it (dot-ui adopt mode). --}}
<x-dot.header id="dh" brand-href="{{ route('dashboard') }}" brand-label="Dot.Agents, command centre" :account="$account"
    drawer-target="app-sidebar" menu-label="Open the menu" search-label="Search Dot.Agents" skip="#content">
    <x-slot:brand>
        <img src="{{ asset('images/mark.png') }}" alt="" width="28" height="28">
        <span class="dh-brand__name">Dot.Agents</span>
    </x-slot:brand>
    @if($viewer && $organization)
        <x-slot:search><livewire:header-search /></x-slot:search>
    @endif
    <x-slot:tools>
        <x-dot.switcher platform="agents" :company="$organization?->infodot_team_uuid" :company-name="$organization?->name" logos="/dot-ui/img/platforms" />
        @livewire('notification-bell')
    </x-slot:tools>
</x-dot.header>

<div class="shell">
    <aside class="side" id="app-sidebar" aria-label="Dot.Agents">
        <div class="side-brand">
            <a href="{{ route('dashboard') }}"><img src="{{ asset('images/mark.png') }}" alt="" width="26" height="26"> Dot.Agents</a>
            <button type="button" class="icon-btn side-close" data-dh-close aria-label="Close the menu"><x-sig.icon name="close" /></button>
        </div>
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
    </aside>

    <div class="main">
        @if(session('success'))<div class="page flash"><div class="notice clear" role="status">{{ session('success') }}</div></div>@endif
        @if(session('error'))<div class="page flash"><div class="notice stop" role="alert">{{ session('error') }}</div></div>@endif

        {{-- The Jetstream pages (team settings, API tokens) still pass their heading as markup in `header`; the Signal Box pages print their own h1. --}}
        @if(isset($header) && str_contains((string) $header, '<'))<div class="page flash legacy-head">{{ $header }}</div>@endif
        <main id="content">{{ $slot }}</main>
    </div>
</div>

@stack('modals')
<script src="{{ asset('dot-ui/js/dot-search.js') }}" defer></script>
<script src="{{ asset('dot-ui/js/dot-search-results.js') }}" defer></script>
<script src="{{ asset('dot-ui/js/dot-switcher.js') }}" defer></script>
<script src="{{ asset('dot-ui/js/dot-nav.js') }}" defer></script>
@livewireScripts
</body>
</html>
