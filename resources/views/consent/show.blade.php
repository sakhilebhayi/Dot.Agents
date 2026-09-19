<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Platform Terms — {{ config('app.name', 'Dot.Agents') }}</title>
    <link rel="icon" href="/dot.logos3.png" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f9f9f7] font-sans antialiased min-h-screen flex flex-col">

    {{-- ── Top bar ── --}}
    <header class="bg-[#1e1660] flex-shrink-0">
        <div class="max-w-4xl mx-auto px-6 h-14 flex items-center gap-3">
            <img src="/dot.logos3.png" alt="Dot.Agents" class="h-7 w-auto">
            <span class="text-sm font-semibold text-white">Dot.Agents</span>
            <span class="text-white/30 text-xs ml-1">/ Platform Terms</span>
        </div>
    </header>

    {{-- ── Main ── --}}
    <main class="flex-1 flex items-start justify-center px-4 py-12">
        <div class="w-full max-w-2xl">

            {{-- Flash warnings --}}
            @if (session('warning'))
                <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ session('warning') }}
                </div>
            @endif

            {{-- Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                {{-- Card header --}}
                <div class="bg-gradient-to-br from-[#1e1660] to-[#3d2ea0] px-8 py-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-[#f5be1c] flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-[#1a1400]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white leading-tight">Platform Terms of Service</h1>
                            <p class="text-brand-purple-pale text-sm mt-0.5">One-time acceptance required before accessing the platform</p>
                        </div>
                    </div>
                </div>

                {{-- Card body --}}
                <div class="px-8 py-7 space-y-6">

                    <p class="text-gray-600 text-sm leading-relaxed">
                        To access <strong class="text-gray-900">Dot.Agents</strong> you must review and accept the
                        Platform Terms of Service and Privacy Policy. Your data is processed in accordance with
                        <strong class="text-gray-900">GDPR</strong> and <strong class="text-gray-900">POPIA</strong>
                        regulations.
                    </p>

                    {{-- Key points --}}
                    <div class="bg-gray-50 rounded-xl border border-gray-100 divide-y divide-gray-100">
                        @foreach([
                            ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'title' => 'Data Security', 'body' => 'All data is encrypted at rest and in transit. We never sell or share your data with third parties.'],
                            ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'title' => 'AI Governance', 'body' => 'All AI agent actions are logged, auditable, and subject to human-in-the-loop approval workflows.'],
                            ['icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3', 'title' => 'GDPR & POPIA Compliance', 'body' => 'You retain rights to access, correct, and delete your personal data at any time via organization settings.'],
                        ] as $point)
                        <div class="flex items-start gap-3.5 px-4 py-4">
                            <div class="w-8 h-8 rounded-lg bg-brand-purple-pale flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-brand-purple-mid" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $point['icon'] }}"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $point['title'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $point['body'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Form --}}
                    <form method="POST" action="{{ route('consent.accept') }}">
                        @csrf

                        <label for="accept"
                               class="flex items-start gap-3 p-4 rounded-xl border-2 border-gray-200 hover:border-brand-purple-light cursor-pointer transition-colors group">
                            <input id="accept" type="checkbox" name="accept" required
                                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-brand-purple-mid focus:ring-brand-purple-light flex-shrink-0">
                            <span class="text-sm text-gray-700 leading-relaxed">
                                I have read and agree to the
                                <a href="#" class="text-brand-purple-mid hover:text-brand-purple font-medium underline underline-offset-2">Terms of Service</a>
                                and
                                <a href="#" class="text-brand-purple-mid hover:text-brand-purple font-medium underline underline-offset-2">Privacy Policy</a>,
                                including the processing of my personal data under GDPR and POPIA.
                            </span>
                        </label>

                        @error('accept')
                            <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="mt-5 flex items-center justify-between">
                            <a href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('consent-logout').submit();"
                               class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                                Sign out instead
                            </a>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#1e1660] hover:bg-[#3d2ea0] text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Accept &amp; Enter Platform
                            </button>
                        </div>
                    </form>

                    {{-- Separate logout form (outside the accept form to avoid nesting) --}}
                    <form id="consent-logout" method="POST" action="{{ route('logout') }}" class="hidden">
                        @csrf
                    </form>

                </div>
            </div>

            {{-- Footer note --}}
            <p class="text-center text-xs text-gray-400 mt-6">
                Dot.Agents Enterprise Platform &mdash; Governed AI Workforce &middot;
                <span class="text-gray-500">{{ config('app.name') }}</span>
            </p>

        </div>
    </main>

</body>
</html>
