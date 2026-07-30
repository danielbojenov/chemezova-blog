@php
    use App\Enums\ImageVariant;
    use App\Filament\Resources\Products\ProductResource;
    use Illuminate\Support\Facades\Storage;

    // The stored path is the Original (suffix-less) variant; the small -thumbnail
    // sibling is enough for an at-a-glance preview while reordering blocks.
    $thumbnail = $product?->image === null
        ? null
        : ImageVariant::Thumbnail->pathFor($product->image);

    // Opened in a new tab so an unsaved article is never navigated away from.
    $editUrl = $product === null
        ? null
        : ProductResource::getUrl('edit', ['record' => $product]);
@endphp

<div class="pcp">
    @if ($product === null)
        <p class="pcp-empty">Select a product to preview its card.</p>
    @else
        @if (filled($thumbnail))
            <img class="pcp-image" src="{{ Storage::disk('public')->url($thumbnail) }}" alt="{{ $product->name }}">
        @else
            <div class="pcp-image pcp-image-empty">No image</div>
        @endif

        <div class="pcp-main">
            <p class="pcp-name">
                <a href="{{ $editUrl }}" target="_blank" rel="noopener">
                    {{ $product->name }}
                    <span class="pcp-external" aria-hidden="true">&#8599;</span>
                    <span class="pcp-sr">Edit this product in a new tab</span>
                </a>
            </p>

            @if ($product->brand)
                <p class="pcp-brand">{{ $product->brand->name }}</p>
            @endif

            <p class="pcp-facts">
                @if (filled($product->rating))
                    <span>{{ number_format((float) $product->rating, 1) }} / 5</span>
                @endif
                @if (filled($product->price))
                    <span>{{ $product->currency }} {{ number_format((float) $product->price, 2) }}</span>
                @endif
                @if (filled($product->price_per_dose))
                    <span>{{ $product->currency }} {{ number_format((float) $product->price_per_dose, 2) }}/dose</span>
                @endif
                @if (filled($product->doses_per_pack))
                    <span>{{ $product->doses_per_pack }} doses</span>
                @endif
                @if ($product->form)
                    <span>{{ $product->form->getLabel() }}</span>
                @endif
                @if ($product->composition)
                    <span>{{ $product->composition->getLabel() }}</span>
                @endif
                @if ($product->primaryIngredient)
                    <span>{{ $product->primaryIngredient->name }}</span>
                @endif
            </p>

            @if ($product->ingredients->isNotEmpty())
                <p class="pcp-ingredients">
                    @foreach ($product->ingredients as $ingredient)
                        <span>{{ $ingredient->name }}</span>
                    @endforeach
                </p>
            @endif

            @if ($product->status === \App\Enums\ProductStatus::Draft)
                <p class="pcp-draft">This product is still a draft — finish it in the catalog.</p>
            @endif
        </div>
    @endif
</div>

@once
    <style>
        /* Self-contained so the preview does not depend on Filament's utility
           classes remaining stable across upgrades. */
        .pcp {
            --pcp-border: rgb(228 228 231);
            --pcp-muted: rgb(113 113 122);
            --pcp-warning: rgb(180 83 9);
            display: flex;
            gap: 0.75rem;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid var(--pcp-border);
            border-radius: 0.5rem;
        }
        .dark .pcp {
            --pcp-border: rgb(255 255 255 / 0.1);
            --pcp-muted: rgb(161 161 170);
            --pcp-warning: rgb(251 191 36);
        }
        .pcp-image {
            width: 3.5rem;
            height: 3.5rem;
            flex: none;
            object-fit: cover;
            border-radius: 0.375rem;
            border: 1px solid var(--pcp-border);
        }
        .pcp-image-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            text-align: center;
            color: var(--pcp-muted);
        }
        .pcp-main { min-width: 0; }
        .pcp-name { font-weight: 600; font-size: 0.9rem; }
        .pcp-name a {
            color: inherit;
            text-decoration: none;
        }
        .pcp-name a:hover { text-decoration: underline; }
        .pcp-external { margin-left: 0.15rem; font-size: 0.75rem; color: var(--pcp-muted); }
        /* Visible to screen readers only — the arrow alone is not a label. */
        .pcp-sr {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .pcp-brand { font-size: 0.8rem; color: var(--pcp-muted); }
        .pcp-facts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.25rem;
            font-size: 0.78rem;
            color: var(--pcp-muted);
        }
        .pcp-ingredients {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            margin-top: 0.35rem;
        }
        .pcp-ingredients span {
            font-size: 0.7rem;
            padding: 0.1rem 0.4rem;
            border-radius: 999px;
            border: 1px solid var(--pcp-border);
            color: var(--pcp-muted);
        }
        .pcp-draft { margin-top: 0.25rem; font-size: 0.75rem; color: var(--pcp-warning); }
        .pcp-empty { font-size: 0.85rem; color: var(--pcp-muted); }
    </style>
@endonce
