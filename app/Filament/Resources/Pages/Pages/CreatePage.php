<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Support\Images\ContentImageProcessor;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PageResource::fillPublishedAt($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Page) {
            app(ContentImageProcessor::class)->process($record);
        }
    }
}
