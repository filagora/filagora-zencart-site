<?php

namespace App\Filament\Resources\NewsletterBlocks\Pages;

use App\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterBlock extends CreateRecord
{
    protected static string $resource = NewsletterBlockResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
