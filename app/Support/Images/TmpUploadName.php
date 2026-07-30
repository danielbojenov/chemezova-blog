<?php

namespace App\Support\Images;

use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Names a staged upload so the image processors can recognise it later.
 *
 * Every deferred upload lands in a tmp directory first and is only moved into
 * its record's directory once that record exists, so the filename has to carry
 * the marker the processors match against with their `TMP_SUFFIX_PATTERN`.
 */
final class TmpUploadName
{
    /**
     * Slug of the original name plus a temporary marker stripped after conversion,
     * with a server-derived extension (the client extension is never trusted).
     */
    public static function for(TemporaryUploadedFile $file): string
    {
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return ($baseName !== '' ? $baseName : 'image')
            .'-tmp'.Str::lower(Str::random(6))
            .'.'.$file->extension();
    }
}
