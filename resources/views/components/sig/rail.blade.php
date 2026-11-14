@props(['state' => 'work'])

{{-- Decorative rail drawn from the platform's own marks: stops on a line. It claims nothing: every stop is an empty outline; the one gold held diamond appears only while an approval is waiting. It is not a progress bar. --}}
@php
    $stops = [60, 180, 300, 420, 540, 660, 780, 900, 1020, 1140];
    $empty = in_array($state, ['first-agent', 'no-agents'], true);
    $held = $state === 'review';
    $heldAt = 5;
@endphp
<svg {{ $attributes->class('rail') }} viewBox="0 0 1200 20" preserveAspectRatio="xMinYMid slice" aria-hidden="true" focusable="false">
    <line class="rail-line" x1="0" y1="10" x2="1200" y2="10" />
    @foreach($stops as $i => $x)
        @if($empty)
            <circle class="rail-stop off" cx="{{ $x }}" cy="10" r="4.5" style="--i: {{ $i }}" />
        @elseif($held && $i === $heldAt)
            <rect class="rail-stop held" x="{{ $x - 4.5 }}" y="5.5" width="9" height="9" rx="1" transform="rotate(45 {{ $x }} 10)" style="--i: {{ $i }}" />
        @else
            <circle class="rail-stop off" cx="{{ $x }}" cy="10" r="4.5" style="--i: {{ $i }}" />
        @endif
    @endforeach
</svg>
