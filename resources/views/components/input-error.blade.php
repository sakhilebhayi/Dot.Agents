@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'error']) }} role="alert">{{ $message }}</p>
@enderror
