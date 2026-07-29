@php
    use App\Filament\Support\ArticleRichContent;

    $tldr = $getState();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="article-content-preview">
        @if (filled($tldr))
            <div class="acp-block acp-richtext">
                {{ ArticleRichContent::renderer($tldr) }}
            </div>
        @else
            <p class="acp-empty">No TLDR has been written for this article.</p>
        @endif
    </div>
</x-dynamic-component>

@include('filament.infolists.article-content-styles')
