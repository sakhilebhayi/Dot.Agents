<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Dot.Agents') }}</title>
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/dot.logos3.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-[#f9f9f7] text-[#111111]">

<div class="min-h-screen flex">

    {{-- Brand Panel --}}
    <div class="hidden lg:flex lg:w-[45%] xl:w-[42%] flex-col justify-between
                bg-[#1e1660] px-12 py-10 relative overflow-hidden">

        {{-- Background texture --}}
        <div class="absolute inset-0 opacity-[0.04]"
             style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-brand-purple-mid/20 rounded-full blur-3xl -translate-y-1/4 translate-x-1/4"></div>
        <div class="absolute top-1/3 -left-20 w-80 h-80 bg-brand-yellow/8 rounded-full blur-3xl"></div>

        {{-- Logo --}}
        <div class="relative z-10">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3 group">
                <img src="/dot.logos3.png" alt="Dot.Agents" class="h-9 w-auto">
                <div>
                    <p class="text-white font-semibold text-base font-display leading-tight">Dot.Agents</p>
                    <p class="text-white/40 text-xs leading-tight">Workforce Platform</p>
                </div>
            </a>
        </div>

        {{-- Middle content --}}
        <div class="relative z-10 space-y-8">
            <div>
                <p class="text-brand-yellow text-xs font-semibold uppercase tracking-widest mb-4">Enterprise AI Workforce</p>
                <h2 class="text-white text-3xl font-bold font-display leading-snug">
                    Manage digital teams<br>like real ones.
                </h2>
                <p class="text-white/50 text-sm mt-4 leading-relaxed max-w-sm">
                    Deploy specialized agents across departments, workflows, and operations — with full governance, accountability, and measurable outcomes.
                </p>
            </div>

            {{-- Feature list --}}
            <div class="space-y-3">
                @foreach([
                    ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => 'Governed approval workflows'],
                    ['icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2', 'label' => 'Visual workflow builder'],
                    ['icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label' => 'Multi-tenant organization management'],
                ] as $feature)
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-2.5 h-2.5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-white/60 text-sm">{{ $feature['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Footer --}}
        <div class="relative z-10">
            <p class="text-white/20 text-xs">&copy; {{ date('Y') }} Dot.Agents. Enterprise Digital Workforce Platform.</p>
        </div>
    </div>

    {{-- Form Panel --}}
    <div class="flex-1 flex flex-col justify-center px-6 sm:px-12 lg:px-16 py-12">
        {{-- Mobile logo --}}
        <div class="lg:hidden mb-10">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5">
                <img src="/dot.logos3.png" alt="Dot.Agents" class="h-8 w-auto">
                <span class="font-semibold text-[#111111] font-display">Dot.Agents</span>
            </a>
        </div>

        <div class="w-full max-w-sm mx-auto">
            {{ $slot }}
        </div>
    </div>
</div>

@livewireScripts
</body>
</html>
