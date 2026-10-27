<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Services\NewsletterSubscriberService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterSubscriber extends EditRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

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
        $email = trim((string) ($data['email'] ?? ''));

        $data['email'] = $email;
        $data['normalized_email'] = app(NewsletterSubscriberService::class)->normalizeEmail($email);
        $data['source'] = trim((string) ($data['source'] ?? '')) ?: 'admin';

        return $data;
    }
}
