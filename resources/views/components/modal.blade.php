@props(['id', 'maxWidth', 'labelledby' => null])

@php
$id = $id ?? md5($attributes->wire('model'));

$maxWidth = [
    'sm' => 'is-sm',
    'md' => 'is-md',
    'lg' => 'is-lg',
    'xl' => 'is-xl',
    '2xl' => 'is-2xl',
][$maxWidth ?? '2xl'];
@endphp

<div
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    id="{{ $id }}"
    class="jetstream-modal dialog-wrap"
    style="display: none;"
>
    <div x-show="show" class="veil" x-on:click="show = false" x-transition.opacity.duration.160ms></div>

    <div x-show="show" class="card dialog {{ $maxWidth }}"
                    role="dialog"
                    aria-modal="true"
                    @if ($labelledby) aria-labelledby="{{ $labelledby }}" @endif
                    x-trap.inert.noscroll="show"
                    x-transition.opacity.duration.160ms>
        {{ $slot }}
    </div>
</div>
