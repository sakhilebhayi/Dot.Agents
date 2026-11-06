<x-guest-layout>
    <x-slot:title>Platform terms</x-slot:title>

    <h1>Platform Terms of Service</h1>
    <p class="auth-lede">One-time acceptance required before accessing the platform</p>

    @if (session('warning'))
        <p class="auth-note" role="status">{{ session('warning') }}</p>
    @endif

    <p class="consent-lede">
        To access <strong>Dot.Agents</strong> you must review and accept the
        Platform Terms of Service and Privacy Policy. Your data is processed in accordance with
        <strong>GDPR</strong> and <strong>POPIA</strong>
        regulations.
    </p>

    <dl class="consent-points">
        @foreach([
            ['title' => 'Data security', 'body' => 'Data is encrypted in transit, and credentials and agent instructions are encrypted in storage. We never sell your data.'],
            ['title' => 'AI governance', 'body' => 'All AI agent actions are logged, auditable, and subject to human-in-the-loop approval workflows.'],
            ['title' => 'GDPR and POPIA compliance', 'body' => 'You retain rights to access, correct, and delete your personal data at any time via organization settings.'],
        ] as $point)
            <div>
                <dt>{{ $point['title'] }}</dt>
                <dd>{{ $point['body'] }}</dd>
            </div>
        @endforeach
    </dl>

    <form method="POST" action="{{ route('consent.accept') }}">
        @csrf

        <label for="accept" class="check">
            <input id="accept" type="checkbox" name="accept" required>
            <span>
                I have read and agree to the
                <a href="{{ route('terms.show') }}" target="_blank" rel="noopener">Terms of Service</a>
                and
                <a href="{{ route('policy.show') }}" target="_blank" rel="noopener">Privacy Policy</a>,
                including the processing of my personal data under GDPR and POPIA.
            </span>
        </label>

        @error('accept')
            <p class="auth-problem" role="alert">{{ $message }}</p>
        @enderror

        <div class="consent-actions">
            <button type="submit" class="button solid">Accept and enter the platform</button>
            <button type="submit" form="consent-logout" class="link">Sign out instead</button>
        </div>
    </form>

    {{-- Separate logout form (outside the accept form to avoid nesting) --}}
    <form id="consent-logout" method="POST" action="{{ route('logout') }}" hidden>
        @csrf
    </form>
</x-guest-layout>
