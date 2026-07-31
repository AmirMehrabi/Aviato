@props(['name'])

<svg {{ $attributes->class('size-5 shrink-0')->merge([
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => '1.9',
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
    'aria-hidden' => 'true',
]) }}>
    @switch($name)
        @case('view')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/>
            @break
        @case('edit')
            <path d="M13.5 6.5 17.5 10.5M4 20l4.2-.8L19 8.4a2.8 2.8 0 0 0-4-4L4.2 15.2 4 20Z"/>
            @break
        @case('login')
            <path d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3M10 12h11m-4-4 4 4-4 4"/>
            @break
        @case('activate')
            <circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 8.5"/>
            @break
        @case('suspend')
            <circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>
            @break
        @case('delete')
            <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/>
            @break
        @case('transfer')
            <path d="M7 7h13m0 0-3-3m3 3-3 3M17 17H4m0 0 3 3m-3-3 3-3"/>
            @break
        @case('sync')
            <path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 8a7 7 0 0 1 11.5-2.2L20 8M4 16l2.4 2.2A7 7 0 0 0 17.9 16"/>
            @break
        @case('test')
            <path d="M9 3h6m-5 0v5l-5.3 9.2A2.5 2.5 0 0 0 6.9 21h10.2a2.5 2.5 0 0 0 2.2-3.8L14 8V3"/><path d="M8 15h8"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>
            @break
        @case('sort-asc')
            <path d="m8 9 4-4 4 4M12 5v14"/>
            @break
        @case('sort-desc')
            <path d="m8 15 4 4 4-4M12 19V5"/>
            @break
        @case('sort')
            <path d="m8 9 4-4 4 4M8 15l4 4 4-4"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
    @endswitch
</svg>
