@props(['state'])

@php [$word, $lamp] = \App\Support\AgentStates::lamp($state); @endphp

<span {{ $attributes->class(['lamp', $lamp]) }}><i aria-hidden="true"></i>{{ $word }}</span>
