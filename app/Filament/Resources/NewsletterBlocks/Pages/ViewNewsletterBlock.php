<?php

namespace App\Filament\Resources\NewsletterBlocks\Pages;

use App\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsletterBlock extends ViewRecord
{
    protected static string $resource = NewsletterBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
