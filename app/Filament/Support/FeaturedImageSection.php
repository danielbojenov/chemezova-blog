<?php

namespace App\Filament\Support;

use App\Support\Images\ContentImageProcessor;
use App\Support\Images\TmpUploadName;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FeaturedImageSection
{
    /**
     * A record's single lead image, uploaded exactly like a content image block
     * but stored in its own column so the public site can select it in SQL rather
     * than parsing the content JSON. Shared by articles and pages.
     *
     * Uploads stage in the shared `content/tmp` directory; ContentImageProcessor
     * moves them into the record's own `featured/` subdirectory on save.
     */
    public static function make(): Section
    {
        return Section::make('Featured image')
            ->columnSpanFull()
            ->schema([
                FileUpload::make('featured_image')
                    ->hiddenLabel()
                    ->image()
                    ->imageEditor()
                    ->disk(ContentImageProcessor::DISK)
                    ->directory(ContentImageProcessor::TMP_DIRECTORY)
                    ->visibility('public')
                    ->maxSize(10240)
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => TmpUploadName::for($file),
                    ),
                TextInput::make('featured_image_alt')
                    ->label('Alt text')
                    ->required(fn (Get $get): bool => filled($get('featured_image')))
                    ->maxLength(255),
                TextInput::make('featured_image_caption')
                    ->label('Caption')
                    ->maxLength(500),
            ]);
    }
}
