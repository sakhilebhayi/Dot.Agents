@props(['name'])

@php
    $paths = [
        'panel' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/>',
        'approve' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'run' => '<path d="M4 12h4l3 7 4-14 3 7h2"/>',
        'agents' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c.5-4 3-6 6-6s5.5 2 6 6M16 5a3.5 3.5 0 0 1 0 6.5M18 14c2 .7 3 2.8 3 6"/>',
        'team' => '<path d="M4 21V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v15M16 10h3a1 1 0 0 1 1 1v10M3 21h18M8 8h4M8 12h4M8 16h4"/>',
        'template' => '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><path d="M16.5 13v7M13 16.5h7"/>',
        'workflow' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 6h7M6 8.5V13a5 5 0 0 0 5 5h4.5"/>',
        'tool' => '<path d="M14.5 6.5a4 4 0 0 0-5.3 5l-6 6 2.3 2.3 6-6a4 4 0 0 0 5-5.3l-2.6 2.6-2-.4-.4-2z"/>',
        'knowledge' => '<path d="M12 6c-2-1.5-5-2-8-2v15c3 0 6 .5 8 2 2-1.5 5-2 8-2V4c-3 0-6 .5-8 2zM12 6v15"/>',
        'memory' => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 3v3M15 3v3M9 18v3M15 18v3M3 9h3M3 15h3M18 9h3M18 15h3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'usage' => '<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>',
        'audit' => '<path d="M6 3h9l4 4v14H6zM14 3v5h5M9 12h6M9 16h6"/>',
        'decision' => '<path d="M12 3v18M5 7l14-2M5 7l-3 7c1.5 1 4.5 1 6 0zM19 5l-3 7c1.5 1 4.5 1 6 0z"/>',
        'shield' => '<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c.5-4 3-6 6-6s5.5 2 6 6"/>',
        'billing' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4"/>',
        'moon' => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'out' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 8l-4 4 4 4M6 12h10"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    ];
@endphp

<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.6', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>{!! $paths[$name] ?? '' !!}</svg>
