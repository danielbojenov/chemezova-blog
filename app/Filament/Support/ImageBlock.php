<?php

namespace App\Filament\Support;

use App\Support\Images\ArticleImageProcessor;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageBlock
{
    /**
     * An article content block with an image upload (converted to WebP variants on save),
     * required alt text, and an optional caption.
     */
    public static function make(): Block
    {
        return Block::make('image')
            ->label('Image')
            ->icon(Heroicon::Photo)
            ->schema([
                FileUpload::make('image')
                    ->hiddenLabel()
                    ->image()
                    ->imageEditor()
                    ->required()
                    ->disk(ArticleImageProcessor::DISK)
                    ->directory(ArticleImageProcessor::TMP_DIRECTORY)
                    ->visibility('public')
                    ->maxSize(10240)
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => self::storageFileName($file),
                    ),
                TextInput::make('alt')
                    ->label('Alt text')
                    ->required()
                    ->maxLength(255),
                TextInput::make('caption')
                    ->label('Caption')
                    ->maxLength(500),
            ]);
    }

    /**
     * Slug of the original name plus a temporary marker stripped after conversion,
     * with a server-derived extension (the client extension is never trusted).
     */
    private static function storageFileName(TemporaryUploadedFile $file): string
    {
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return ($baseName !== '' ? $baseName : 'image')
            .'-tmp'.Str::lower(Str::random(6))
            .'.'.$file->extension();
    }
}
