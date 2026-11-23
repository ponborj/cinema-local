@props(['name'])
<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('play')<path d="m8 5 11 7-11 7Z" />@break
        @case('folder')<path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3Z" />@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" />@break
        @case('tv')<rect x="3" y="4" width="18" height="13" rx="2" /><path d="M8 21h8m-4-4v4" />@break
        @case('grid')<rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />@break
        @case('film')<rect x="3" y="3" width="18" height="18" rx="2" /><path d="M7 3v18m10-18v18M3 8h4m-4 8h4m10-8h4m-4 8h4" />@break
        @case('refresh')<path d="M20 8a8 8 0 1 0 0 8m0-13v5h-5" />@break
        @case('fullscreen')<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5" />@break
        @case('volume')<path d="M11 4 6 8H3v8h3l5 4Zm4 4a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14" />@break
        @case('captions')<rect x="2" y="5" width="20" height="14" rx="3" /><path d="M10 10a2 2 0 1 0 0 4m8-4a2 2 0 1 0 0 4" />@break
        @case('clock')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
    @endswitch
</svg>
