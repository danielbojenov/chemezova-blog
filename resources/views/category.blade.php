{{--
    Category page — a deliberate stand-in.

    It exists so the navigation links configured in the admin resolve to a real page;
    the designed layout replaces this template in a follow-up.
--}}
<x-layouts.site :title="$category->name" :description="$category->description">
    <div class="mx-auto max-w-[1280px] px-5 py-8 lg:px-16 lg:py-12">
        <h1 class="font-serif text-[28px]/[1.2] font-bold tracking-[-0.02em] text-ink lg:text-[40px]/[1.15]">
            {{ $category->name }}
        </h1>

        @if ($category->description)
            <p class="mt-3 max-w-[640px] text-[15px]/[1.6] text-body lg:text-base/[1.65]">
                {{ $category->description }}
            </p>
        @endif

        <div class="mt-8 flex flex-col divide-y divide-rule-soft lg:mt-10">
            @forelse ($articles as $article)
                <article class="py-5 first:pt-0">
                    <h2 class="font-serif text-[17px]/[1.3] font-semibold text-ink lg:text-[22px]/[1.3]">
                        {{ $article->title }}
                    </h2>

                    @if ($article->excerpt)
                        <p class="mt-1.5 text-[14.5px]/[1.55] text-body">{{ $article->excerpt }}</p>
                    @endif
                </article>
            @empty
                <p class="text-[15px] text-muted">No articles in this category yet.</p>
            @endforelse
        </div>
    </div>
</x-layouts.site>
