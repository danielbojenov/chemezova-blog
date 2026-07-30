{{--
    Featured article.
    Mobile (design 1a): artwork on top, then eyebrow, headline, excerpt and byline.
    Desktop (design 2a): two columns — copy plus a call to action on the left,
    artwork on the right.
--}}
<section class="border-b border-rule">
    <div class="mx-auto max-w-[1280px] px-5 pt-6 pb-7 lg:grid lg:max-w-[1280px] lg:grid-cols-[1.05fr_1fr] lg:items-center lg:gap-14 lg:px-16 lg:py-14">
        {{-- Artwork — first in the flow on mobile, second column on desktop. --}}
        <div class="ph-cool flex h-[210px] items-center justify-center rounded-lg lg:col-start-2 lg:row-start-1 lg:h-[340px]">
            <span class="rounded bg-canvas px-2.5 py-1.5 font-mono text-[11px] text-[#6C8A73] lg:text-xs">
                hero photo — sunlight / capsules
            </span>
        </div>

        <div class="mt-3.5 flex flex-col gap-3.5 lg:col-start-1 lg:row-start-1 lg:mt-0 lg:gap-[18px]">
            <span class="text-[11px] font-semibold tracking-[0.14em] text-brand uppercase lg:text-xs">
                Featured · Vitamin D
            </span>

            <h1 class="font-serif text-[27px]/[1.2] font-bold tracking-[-0.02em] text-pretty text-ink lg:text-[44px]/[1.12]">
                <a href="#">Vitamin D in 2026: How Much You Actually Need</a>
            </h1>

            <p class="text-[15px]/[1.6] text-body lg:max-w-[480px] lg:text-[17px]/[1.6]">
                New research changed the recommended ranges. Here is what the evidence says
                about dosage, timing, and testing.
            </p>

            {{-- Byline --}}
            <div class="flex items-center gap-2 text-[12.5px] text-muted lg:mt-1 lg:gap-2.5 lg:text-[13.5px]">
                <span class="ph-avatar size-[26px] rounded-full lg:size-[30px]"></span>
                <span class="font-semibold text-ink-soft">Elena Chemezova</span>
                <span aria-hidden="true">·</span>
                <span>8 min<span class="hidden lg:inline"> read</span></span>
                <span aria-hidden="true">·</span>
                <span><span class="hidden lg:inline">Updated </span>Jul 2026</span>
            </div>

            {{-- Call to action — desktop only (2a); on mobile the headline itself is the link. --}}
            <a
                href="#"
                class="mt-1.5 hidden self-start rounded-md bg-brand px-7 py-[13px] text-[15px] font-semibold text-white hover:bg-brand-dark lg:inline-block"
            >
                Read the guide →
            </a>
        </div>
    </div>
</section>
