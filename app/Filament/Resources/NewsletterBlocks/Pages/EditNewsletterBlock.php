<?php

namespace App\Filament\Resources\NewsletterBlocks\Pages;

use App\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterBlock extends EditRecord
{
    protected static string $resource = NewsletterBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
