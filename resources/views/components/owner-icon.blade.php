@props(['name'])
<svg aria-hidden="true" class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
    stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('home')
            <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8" />
        @break

        @case('chart')
            <path d="M4 3v18h17M8 16v-4m5 4V7m5 9V4" />
        @break

        @case('search')
            <circle cx="10" cy="10" r="6" />
            <path d="m15 15 6 6" />
        @break

        @case('profile')
            <circle cx="12" cy="7" r="4" />
            <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
        @break

        @case('logout')
            <path d="M9 3H4v18h5M9 12h12m-4-4 4 4-4 4" />
        @break

        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
        @break

        @case('close')
            <path d="m6 6 12 12M6 18 18 6" />
        @break

        @case('requests')
            <path d="M7 3h10l3 3v15H4V3h3M8 10h8M8 14h8M8 18h5" />
        @break

        @case('brands')
            <path d="M3 3h8l10 10-8 8L3 11Z" />
            <circle cx="8" cy="8" r="1" />
        @break

        @case('concerns')
            <path d="M20 3C9 3 3 8 5 15c6 5 15-1 15-12ZM4 21l11-12" />
        @break

        @default
            <path d="m12 3 9 5v9l-9 5-9-5V8Zm0 9 9-4M3 8l9 4v10" />
    @endswitch
</svg>
