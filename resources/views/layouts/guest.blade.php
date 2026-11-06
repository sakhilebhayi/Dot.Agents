<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="description" content="Sign in to Dot.Agents, AI agents that stop and ask.">
    <title>{{ $title ?? 'Sign in' }} · Dot.Agents</title>
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="auth-page">
<main class="auth">
    <a class="brand" href="{{ url('/') }}"><img src="{{ asset('images/mark.png') }}" alt="" width="24" height="24"><span>Dot.Agents</span></a>
    <div class="auth-body">
        {{ $slot }}
    </div>
    <p class="auth-foot">Agents that stop and ask.</p>
</main>
@livewireScripts
</body>
</html>
