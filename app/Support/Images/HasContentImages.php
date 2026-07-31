<?php

namespace App\Support\Images;

/**
 * A model whose images {@see ContentImageProcessor} manages: `image` blocks inside a
 * content Builder, plus an optional single featured image column.
 *
 * Those two columns are part of the contract, declared here because an interface cannot
 * require properties outright; the only behaviour the processor needs on top of them is
 * where the converted files should live.
 *
 * @property array<int, mixed>|null $content
 * @property string|null $featured_image
 */
interface HasContentImages
{
    /**
     * The disk directory holding this record's converted images, without a trailing
     * slash. Must be unique per record, since the processor treats every unreferenced
     * file inside it as an orphan and deletes it.
     */
    public function imageDirectory(): string;
}
