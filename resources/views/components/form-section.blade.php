@props(['submit'])

<section {{ $attributes->merge(['class' => 'card']) }}>
    <x-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <form wire:submit="{{ $submit }}">
        <div class="card-body">
            {{ $form }}
        </div>

        @if (isset($actions))
            <div class="acct-foot">
                {{ $actions }}
            </div>
        @endif
    </form>
</section>
