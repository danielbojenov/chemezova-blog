{{--
    Site footer.
    Mobile (design 1a): brand block, link columns, disclaimer and copyright stacked.
    Desktop (design 2a): brand block beside three labelled columns, then a ruled row
    holding the disclaimer and copyright side by side.
--}}
<footer class="border-t border-rule bg-surface-alt">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-[18px] px-5 pt-7 pb-8 lg:gap-[26px] lg:px-16 lg:pt-10 lg:pb-11">
        <div class="flex flex-col gap-[18px] lg:flex-row lg:justify-between lg:gap-10">
            {{-- Brand --}}
            <div class="flex max-w-[320px] flex-col gap-1 lg:gap-1.5">
                <span class="font-serif text-lg font-bold text-ink lg:text-xl">
                    chemezova<span class="text-brand">.</span>com
                </span>
                <span class="text-[12.5px] text-muted italic lg:text-[13.5px]">
                    Evidence-based nutrition, made simple.
                </span>
            </div>

            {{-- Link columns. Two up on mobile, three labelled columns from lg. --}}
            <nav class="grid grid-cols-2 gap-x-6 gap-y-5 text-[13px] font-medium text-ink-soft lg:flex lg:gap-16 lg:text-[13.5px]">
                <div class="flex flex-col gap-2 lg:gap-2.5">
                    <span class="hidden text-[11px] font-semibold tracking-[0.12em] text-muted uppercase lg:block">Topics</span>
                    <a href="#" class="hover:text-ink">Vitamins</a>
                    <a href="#" class="hover:text-ink">Minerals</a>
                    <a href="#" class="hover:text-ink">Supplements</a>
                    <a href="#" class="hover:text-ink">TOP 10</a>
                </div>

                <div class="flex flex-col gap-2 lg:gap-2.5">
                    <span class="hidden text-[11px] font-semibold tracking-[0.12em] text-muted uppercase lg:block">Site</span>
                    <a href="#" class="hover:text-ink">About Elena</a>
                    <a href="#" class="hover:text-ink">Contact</a>
                    <a href="#" class="hover:text-ink">How we test</a>
                </div>

                <div class="flex flex-col gap-2 lg:gap-2.5">
                    <span class="hidden text-[11px] font-semibold tracking-[0.12em] text-muted uppercase lg:block">Legal</span>
                    <a href="#" class="hover:text-ink">Privacy Policy</a>
                    <a href="#" class="hover:text-ink">Affiliate Disclosure</a>
                    <a href="#" class="hover:text-ink">Terms of Use</a>
                </div>
            </nav>
        </div>

        {{-- Disclaimer + copyright. Ruled off and set on one row from lg. --}}
        <div class="flex flex-col gap-[18px] text-[11.5px] text-faint lg:flex-row lg:justify-between lg:gap-8 lg:border-t lg:border-rule-strong lg:pt-5 lg:text-xs">
            <span class="leading-[1.5] lg:max-w-[720px]">
                Content on this site is for informational purposes and is not medical advice.
                Some links are affiliate links — we may earn a commission at no cost to you.
            </span>
            <span class="lg:whitespace-nowrap">© {{ now()->year }} chemezova.com</span>
        </div>
    </div>
</footer>
