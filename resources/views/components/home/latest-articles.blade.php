{{-- "Latest articles" list (designs 1a and 2a) — the main column of the home page. --}}
<section>
    <div class="mb-1 flex items-baseline justify-between lg:mb-1.5">
        <h2 class="text-xs font-semibold tracking-[0.12em] text-ink-soft uppercase lg:text-[13px]">
            Latest articles
        </h2>

        <a href="#" class="text-[13px] font-medium text-brand hover:text-brand-dark lg:text-sm">View all →</a>
    </div>

    <div class="flex flex-col divide-y divide-rule-soft">
        <x-home.article-row
            topic="Magnesium"
            title="Glycinate vs. Citrate: Which Form to Choose"
            excerpt="The absorption data, side effects, and when each form makes sense."
            reading-time="6 min read"
            date="Jun 2026"
        />

        <x-home.article-row
            topic="Omega-3"
            title="Fish Oil Freshness: Reading the TOTOX Number"
            excerpt="Rancid fish oil is more common than you'd think. Here's how to spot it before you buy."
            reading-time="9 min read"
            date="Jun 2026"
        />

        <x-home.article-row
            topic="Basics"
            title="Do You Need a Multivitamin? An Honest Look"
            excerpt="Who actually benefits, who's wasting money, and what the cohort studies say."
            reading-time="7 min read"
            date="May 2026"
        />

        <x-home.article-row
            topic="Vitamin B12"
            title="B12 on a Plant-Based Diet: Cyanocobalamin vs. Methyl"
            excerpt="The two forms compared — and why the cheaper one usually wins."
            reading-time="5 min read"
            date="May 2026"
        />
    </div>
</section>
