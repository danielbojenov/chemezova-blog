{{-- "Browse topics" tag pills (design 2a). Desktop only — on mobile the chip row at the top of the page covers this. --}}
<section class="hidden flex-col gap-3 px-0.5 py-1 lg:flex">
    <h2 class="text-[11px] font-semibold tracking-[0.14em] text-muted uppercase">Browse topics</h2>

    <div class="flex flex-wrap gap-2">
        @foreach (['Vitamin D', 'Magnesium', 'Omega-3', 'B12', 'Zinc', 'Collagen'] as $topic)
            <a
                href="#"
                class="rounded-full bg-brand-tint px-3.5 py-[7px] text-[13px] font-medium text-brand hover:text-brand-dark"
            >
                {{ $topic }}
            </a>
        @endforeach
    </div>
</section>
