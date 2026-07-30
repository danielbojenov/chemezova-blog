@props([
    'topic',
    'title',
    'excerpt',
    'readingTime',
    'date',
])

{{--
    One row of the "Latest articles" list.
    Mobile (design 1a): square thumbnail, title and reading time only.
    Desktop (design 2a): wide thumbnail, larger title, excerpt and a dated meta line.
--}}
<article class="flex gap-3.5 py-5 first:pt-0 last:pb-0 lg:gap-6 lg:py-[13px]">
    <div class="flex flex-1 flex-col gap-1.5 lg:gap-2">
        <span class="text-[11px] font-semibold tracking-[0.1em] text-brand uppercase lg:text-[11.5px]">
            {{ $topic }}
        </span>

        <h3 class="font-serif text-[17px]/[1.3] font-semibold text-ink lg:text-[22px]/[1.3]">
            <a href="#">{{ $title }}</a>
        </h3>

        <p class="hidden text-[14.5px]/[1.55] text-body lg:block">{{ $excerpt }}</p>

        <span class="text-[12.5px] text-muted lg:text-[13px]">
            {{ $readingTime }}<span class="hidden lg:inline"> · {{ $date }}</span>
        </span>
    </div>

    <div class="ph-warm size-[86px] flex-none rounded-md lg:h-[126px] lg:w-[190px]"></div>
</article>
