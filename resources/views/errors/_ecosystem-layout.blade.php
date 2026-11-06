@php
    // An error page must still render when the build is missing or the database is down,
    // so it asks for nothing but the stylesheet, and only if the stylesheet is there.
    $viteManifestPath = public_path('build/manifest.json');
    $hasStyles = file_exists(public_path('hot'));
    if (! $hasStyles && file_exists($viteManifestPath)) {
        $viteManifest = json_decode(file_get_contents($viteManifestPath), true) ?? [];
        $hasStyles = isset($viteManifest['resources/css/landing.css']);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Dot.Agents</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    @if ($hasStyles)
        @vite(['resources/css/landing.css'])
    @endif
</head>
<body class="doc-page">
<a class="skip" href="#main">Skip to content</a>

<header class="bar">
    <a class="brand" href="/" aria-label="Dot.Agents home"><img src="{{ asset('images/mark.png') }}" alt="" width="24" height="24"><span>Dot.Agents</span></a>
</header>

<main id="main" class="err">
    <div class="err-line" data-state="{{ $state ?? 'stop' }}" aria-hidden="true"><i></i><b></b></div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    @yield('action')
</main>

<footer class="foot">
    <span>Dot.Agents</span>
    <span class="mono">Error {{ $code }}</span>
</footer>
</body>
</html>
