@props(['name', 'label', 'type' => 'text', 'value' => null])

<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" {{ $attributes }}>
</div>
