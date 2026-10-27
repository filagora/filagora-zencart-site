<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\NewsletterSubscriberStatus;
use App\Services\NewsletterSubscriberService;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterSubscriber extends CreateRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $email = trim((string) ($data['email'] ?? ''));

        $data['email'] = $email;
        $data['normalized_email'] = app(NewsletterSubscriberService::class)->normalizeEmail($email);
        $data['status'] = NewsletterSubscriberStatus::Pending;
        $data['source'] = trim((string) ($data['source'] ?? '')) ?: 'admin';

        return $data;
    }
}
