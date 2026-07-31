@php
    use App\Filament\Support\RichContent;

    $tldr = $getState();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="content-preview">
        @if (filled($tldr))
            <div class="acp-block acp-richtext">
                {{ RichContent::renderer($tldr) }}
            </div>
        @else
            <p class="acp-empty">No TLDR has been written for this article.</p>
        @endif
    </div>
</x-dynamic-component>

@include('filament.infolists.content-styles')
