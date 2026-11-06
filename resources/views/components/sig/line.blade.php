@props(['stops'])

{{-- The run drawn as stops. Each stop: label, detail, state of done, current, held, failed or ahead. --}}
<ol {{ $attributes->class('line') }} style="list-style:none;margin:0">
    @foreach($stops as $stop)
        <li @class(['stop-point', $stop['state'] ?? 'ahead'])>
            <i aria-hidden="true"></i>
            <b>{{ $stop['label'] }}</b>
            <span>{{ $stop['detail'] ?? '' }}</span>
        </li>
    @endforeach
</ol>
