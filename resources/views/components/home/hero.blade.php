{{--
    Featured article.
    Mobile (design 1a): artwork on top, then eyebrow, headline, excerpt and byline.
    Desktop (design 2a): two columns — copy plus a call to action on the left,
    artwork on the right.

    Renders nothing when no article is featured, so a site with no featured tag — or none
    carrying it — simply opens on the article list.
--}}
@props(['featured'])

@if ($featured)
    <section class="border-b border-rule">
        <div class="mx-auto max-w-[1280px] px-5 pt-6 pb-7 lg:grid lg:max-w-[1280px] lg:grid-cols-[1.05fr_1fr] lg:items-center lg:gap-14 lg:px-16 lg:py-14">
            {{--
                Artwork — first in the flow on mobile, second column on desktop.

                The box is a fixed height at each breakpoint and the image covers it, so a
                horizontal and a vertical photograph both fill it at their own aspect ratio,
                never stretched; whatever does not fit is cropped evenly from the centre.
            --}}
            @if ($featured->imageUrl)
                <div class="h-[210px] overflow-hidden rounded-lg lg:col-start-2 lg:row-start-1 lg:h-[340px]">
                    <img
                        src="{{ $featured->imageUrl }}"
                        srcset="{{ $featured->imageMobileUrl }} 768w, {{ $featured->imageUrl }} 1920w"
                        sizes="(min-width: 1024px) 46vw, 100vw"
                        alt="{{ $featured->imageAlt }}"
                        class="h-full w-full object-cover object-center"
                        fetchpriority="high"
                    >
                </div>
            @else
                <div class="ph-cool h-[210px] rounded-lg lg:col-start-2 lg:row-start-1 lg:h-[340px]"></div>
            @endif

            <div class="mt-3.5 flex flex-col gap-3.5 lg:col-start-1 lg:row-start-1 lg:mt-0 lg:gap-[18px]">
                <span class="text-[11px] font-semibold tracking-[0.14em] text-brand uppercase lg:text-xs">
                    {{ $featured->eyebrow }}
                </span>

                <h1 class="font-serif text-[27px]/[1.2] font-bold tracking-[-0.02em] text-pretty text-ink lg:text-[44px]/[1.12]">
                    <a href="{{ $featured->article->url() }}">{{ $featured->article->title }}</a>
                </h1>

                @if ($featured->intro)
                    <p class="text-[15px]/[1.6] text-body lg:max-w-[480px] lg:text-[17px]/[1.6]">
                        {{ $featured->intro }}
                    </p>
                @endif

                {{-- Byline --}}
                <div class="flex items-center gap-2 text-[12.5px] text-muted lg:mt-1 lg:gap-2.5 lg:text-[13.5px]">
                    @if ($featured->authorAvatarUrl)
                        <img
                            src="{{ $featured->authorAvatarUrl }}"
                            alt=""
                            class="size-[26px] flex-none rounded-full object-cover lg:size-[30px]"
                            loading="lazy"
                        >
                    @endif

                    @if ($featured->authorName)
                        @if ($featured->authorUrl)
                            <a href="{{ $featured->authorUrl }}" class="font-semibold text-ink-soft hover:text-brand">{{ $featured->authorName }}</a>
                        @else
                            <span class="font-semibold text-ink-soft">{{ $featured->authorName }}</span>
                        @endif
                        <span aria-hidden="true">·</span>
                    @endif

                    <span>{{ $featured->readingMinutes }} min<span class="hidden lg:inline"> read</span></span>
                    <span aria-hidden="true">·</span>
                    <span><span class="hidden lg:inline">Updated </span>{{ $featured->article->updated_at->format('M Y') }}</span>
                </div>

                {{-- Call to action — desktop only (2a); on mobile the headline itself is the link. --}}
                <a
                    href="{{ $featured->article->url() }}"
                    class="mt-1.5 hidden self-start rounded-md bg-brand px-7 py-[13px] text-[15px] font-semibold text-white hover:bg-brand-dark lg:inline-block"
                >
                    {{ $featured->buttonLabel }} →
                </a>
            </div>
        </div>
    </section>
@endif
