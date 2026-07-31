{{--
    Article page — a deliberate stand-in.

    It exists so articles authored in the admin resolve to a real URL; the designed
    layout, with the TLDR, the content blocks and the product cards, replaces this
    template in a follow-up.
--}}
<x-layouts.site :title="$article->meta_title ?: $article->title" :description="$article->meta_description">
    <div class="mx-auto max-w-[1280px] px-5 py-8 lg:px-16 lg:py-12">
        <h1 class="font-serif text-[28px]/[1.2] font-bold tracking-[-0.02em] text-ink lg:text-[40px]/[1.15]">
            {{ $article->title }}
        </h1>

        @if ($article->excerpt)
            <p class="mt-3 max-w-[640px] text-[15px]/[1.6] text-body lg:text-base/[1.65]">
                {{ $article->excerpt }}
            </p>
        @endif
    </div>
</x-layouts.site>
