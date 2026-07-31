<?php

namespace App\Filament\Support;

use App\Support\Images\ContentImageProcessor;
use App\Support\Images\TmpUploadName;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageBlock
{
    /**
     * A content block with an image upload (converted to WebP variants on save),
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
                    ->disk(ContentImageProcessor::DISK)
                    ->directory(ContentImageProcessor::TMP_DIRECTORY)
                    ->visibility('public')
                    ->maxSize(10240)
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => TmpUploadName::for($file),
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
}
