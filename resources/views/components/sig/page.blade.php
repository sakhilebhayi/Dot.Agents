@props(['title', 'sub' => null, 'narrow' => false])

<div @class(['page', 'narrow' => $narrow])>
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            @if($sub)<p>{{ $sub }}</p>@endif
        </div>
        @isset($actions)<div class="page-actions">{{ $actions }}</div>@endisset
    </div>
    {{ $slot }}
</div>
