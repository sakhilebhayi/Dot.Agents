<section {{ $attributes->merge(['class' => 'card']) }}>
    <x-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
        @isset($aside)
            <x-slot name="aside">{{ $aside }}</x-slot>
        @endisset
    </x-section-title>

    <div class="card-body">
        {{ $content }}
    </div>
</section>
