{{--
    Topic chips (design 1a). Mobile only — on desktop the same topics live in the
    header navigation, so this row is dropped from lg up.
--}}
<nav
    aria-label="Topics"
    class="no-scrollbar flex gap-2 overflow-x-auto border-b border-rule px-5 py-3.5 lg:hidden"
>
    <a href="#" class="rounded-full bg-ink px-3.5 py-[7px] text-[13px] font-semibold whitespace-nowrap text-canvas">All</a>
    <a href="#" class="rounded-full border border-rule px-3.5 py-[7px] text-[13px] font-medium whitespace-nowrap text-ink-soft">Vitamins</a>
    <a href="#" class="rounded-full border border-rule px-3.5 py-[7px] text-[13px] font-medium whitespace-nowrap text-ink-soft">Minerals</a>
    <a href="#" class="rounded-full border border-rule px-3.5 py-[7px] text-[13px] font-medium whitespace-nowrap text-ink-soft">Omega-3</a>
    <a href="#" class="rounded-full border border-brand-line bg-brand-tint px-3.5 py-[7px] text-[13px] font-semibold whitespace-nowrap text-brand">TOP 10</a>
</nav>
