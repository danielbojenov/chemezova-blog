{{--
    Standing page — a deliberate stand-in.

    It exists so the pages authored in the admin resolve to a real URL; the designed
    layout, with the content blocks and a hub's list of child pages, replaces this
    template in a follow-up.
--}}
<x-layouts.site :title="$page->meta_title ?: $page->title" :description="$page->meta_description">
    <div class="mx-auto max-w-[1280px] px-5 py-8 lg:px-16 lg:py-12">
        <h1 class="font-serif text-[28px]/[1.2] font-bold tracking-[-0.02em] text-ink lg:text-[40px]/[1.15]">
            {{ $page->title }}
        </h1>

        @if ($page->excerpt)
            <p class="mt-3 max-w-[640px] text-[15px]/[1.6] text-body lg:text-base/[1.65]">
                {{ $page->excerpt }}
            </p>
        @endif
    </div>
</x-layouts.site>
