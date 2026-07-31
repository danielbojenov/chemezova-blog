{{--
    Home page — designs 1a (mobile, 390px) and 2a (desktop, 1280px) of
    .files/Blog Pages Mobile.dc.html.

    The featured block reads real content; the remaining sections still carry the
    design's placeholder copy and are wired to articles, categories and tags in a
    follow-up.
--}}
<x-layouts.site description="Evidence-based guides to vitamins, minerals and supplements.">
    <x-home.topic-scroller />

    <x-home.hero :featured="$featured" />

    {{--
        Article list + sidebar. One column on mobile, where the sidebar cards simply
        continue below the list in the order design 1a shows them; a 1fr / 360px
        split from lg, as in design 2a.
    --}}
    <div class="mx-auto max-w-[1280px] px-5 py-6 lg:grid lg:grid-cols-[1fr_360px] lg:gap-14 lg:px-16 lg:py-12">
        <x-home.latest-articles />

        <aside class="mt-7 flex flex-col gap-7 lg:mt-0 lg:gap-6">
            <x-home.top10-promo />
            <x-home.popular-now />
            <x-home.newsletter />
            <x-home.browse-topics />
        </aside>
    </div>
</x-layouts.site>
