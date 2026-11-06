@props(['level'])

@php $tone = ['critical' => 'stop', 'high' => 'hold', 'medium' => 'running'][$level] ?? ''; @endphp

<span class="chip {{ $tone }}">{{ ucfirst($level) }}</span>
