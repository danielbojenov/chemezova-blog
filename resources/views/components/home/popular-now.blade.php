{{-- "Popular now" sidebar card (design 2a). Desktop only — the mobile home page (1a) omits it. --}}
<section class="hidden flex-col gap-3.5 rounded-[10px] border border-rule bg-surface px-[26px] py-6 lg:flex">
    <h2 class="text-[11px] font-semibold tracking-[0.14em] text-muted uppercase">Popular now</h2>

    <ol class="flex flex-col gap-3.5">
        @foreach ([
            'Glycinate vs. Citrate: Which Form to Choose',
            'Reading the TOTOX Number',
            'Do You Need a Multivitamin?',
            'Vitamin D in 2026',
        ] as $index => $title)
            <li class="flex gap-3 font-serif text-[15.5px]/[1.35] font-semibold text-ink">
                <span class="text-[15px] font-bold text-numeral" aria-hidden="true">
                    {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                </span>

                <a href="#">{{ $title }}</a>
            </li>
        @endforeach
    </ol>
</section>
