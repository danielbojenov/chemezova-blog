{{--
    Newsletter sign-up (designs 1a and 2a). Inline under the TOP 10 promo on mobile,
    in the sidebar on desktop. The form does not submit anywhere yet.
--}}
<section class="flex flex-col gap-2.5 rounded-[10px] border border-rule bg-surface px-[22px] py-6 lg:px-[26px]">
    <h2 class="font-serif text-xl font-bold text-ink lg:text-[19px]">The Nutrient Letter</h2>

    <p class="text-sm/[1.55] text-body lg:text-[13.5px]/[1.55]">
        One evidence-based guide in your inbox, every other week.
        <span class="lg:hidden">No spam.</span>
    </p>

    <form class="mt-1 flex flex-col gap-2">
        <label for="newsletter-email" class="sr-only">Email address</label>

        <input
            id="newsletter-email"
            type="email"
            name="email"
            placeholder="your@email.com"
            class="rounded-md border border-rule bg-canvas px-3.5 py-3 text-sm text-ink placeholder:text-faint focus:border-brand focus:outline-none lg:text-[13.5px]"
        >

        <button
            type="submit"
            class="rounded-md bg-brand px-3.5 py-3 text-sm font-semibold text-white hover:bg-brand-dark lg:text-[13.5px]"
        >
            Subscribe
        </button>
    </form>
</section>
