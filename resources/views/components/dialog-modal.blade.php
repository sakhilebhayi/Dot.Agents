@props(['id' => null, 'maxWidth' => null])

@php
    $titleId = 'dialog-'.md5($id ?? $attributes->wire('model'));
@endphp

<x-modal :id="$id" :maxWidth="$maxWidth" :labelledby="$titleId" {{ $attributes }}>
    <div class="card-head"><h2 id="{{ $titleId }}">{{ $title }}</h2></div>

    <div class="card-body">
        {{ $content }}
    </div>

    <div class="dialog-foot">
        {{ $footer }}
    </div>
</x-modal>
