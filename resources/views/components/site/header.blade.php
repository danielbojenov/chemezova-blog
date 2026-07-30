{{--
    Site header.
    Mobile (design 1a): wordmark + circular search and menu buttons; the menu button
    opens a fullscreen overlay holding the same links, centred.
    Desktop (design 2a): wordmark + inline topic navigation + a rounded search field.

    The links are configured in Site settings → Navigation and reach this component
    through the composer registered in AppServiceProvider.
--}}
@php($navigationLinks ??= [])

<header
    x-data="{ open: false }"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    @keydown.escape.window="open = false"
    {{-- The overlay is mobile-only, so widening past the lg breakpoint dismisses it. --}}
    @resize.window="if (window.innerWidth >= 1024) open = false"
    class="border-b border-rule"
>
    <div class="mx-auto flex max-w-[1280px] items-center justify-between px-5 py-4 lg:px-16 lg:py-5">
        <a href="{{ route('home') }}" class="font-serif text-xl font-bold tracking-[-0.02em] text-ink lg:text-2xl">
            chemezova<span class="text-brand">.</span>com
        </a>

        {{-- Desktop navigation (2a) --}}
        <nav class="hidden items-center gap-[30px] text-[14.5px] font-medium text-ink-soft lg:flex">
            @foreach ($navigationLinks as $link)
                <a
                    href="{{ $link->url }}"
                    class="nav-link @if ($link->isCurrent) text-ink @endif"
                    @if ($link->isCurrent) aria-current="page" @endif
                >{{ $link->label }}</a>
            @endforeach

            <button
                type="button"
                class="flex items-center gap-2 rounded-full border border-rule px-[18px] py-[9px] text-[13.5px] text-faint hover:text-ink-soft"
            >
                <span aria-hidden="true">⌕</span>
                Search…
            </button>
        </nav>

        {{-- Mobile controls (1a) --}}
        <div class="flex items-center gap-2 lg:hidden">
            <button
                type="button"
                aria-label="Search"
                class="flex size-[38px] items-center justify-center rounded-full border border-rule text-base text-body"
            >
                <span aria-hidden="true">⌕</span>
            </button>

            @if ($navigationLinks !== [])
                <button
                    type="button"
                    aria-label="Open menu"
                    aria-controls="mobile-menu"
                    :aria-expanded="open"
                    @click="open = true"
                    class="flex size-[38px] flex-col items-center justify-center gap-1 rounded-full border border-rule"
                >
                    <span class="h-[1.5px] w-[15px] bg-ink"></span>
                    <span class="h-[1.5px] w-[15px] bg-ink"></span>
                </button>
            @endif
        </div>
    </div>

    {{-- Fullscreen mobile menu. Not part of the design files; matches their tokens. --}}
    @if ($navigationLinks !== [])
        <div
            id="mobile-menu"
            x-show="open"
            x-cloak
            x-transition.opacity.duration.200ms
            class="fixed inset-0 z-50 flex flex-col bg-canvas lg:hidden"
        >
            <div class="flex items-center justify-between px-5 py-4">
                <span class="font-serif text-xl font-bold tracking-[-0.02em] text-ink">
                    chemezova<span class="text-brand">.</span>com
                </span>

                <button
                    type="button"
                    aria-label="Close menu"
                    @click="open = false"
                    class="flex size-[38px] items-center justify-center rounded-full border border-rule text-lg text-body"
                >
                    <span aria-hidden="true">✕</span>
                </button>
            </div>

            <nav class="flex flex-1 flex-col items-center justify-center gap-7 px-5 pb-16 text-center">
                @foreach ($navigationLinks as $link)
                    <a
                        href="{{ $link->url }}"
                        @click="open = false"
                        class="font-serif text-[26px] font-semibold tracking-[-0.01em] {{ $link->isCurrent ? 'text-brand' : 'text-ink' }}"
                        @if ($link->isCurrent) aria-current="page" @endif
                    >{{ $link->label }}</a>
                @endforeach
            </nav>
        </div>
    @endif
</header>
