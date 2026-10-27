<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\Actions\NewsletterSendActions;
use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Models\Newsletter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewNewsletter extends ViewRecord
{
    protected static string $resource = NewsletterResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Newsletter $record */
        $record = $this->getRecord();

        return NewsletterSendActions::statusBadge($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('newsletters.preview', $this->record))
                ->openUrlInNewTab(),
            NewsletterSendActions::sendTest(),
            NewsletterSendActions::scheduleSend(),
            NewsletterSendActions::cancelSchedule(),
            NewsletterSendActions::queueSend(),
            EditAction::make()
                ->visible(fn (Newsletter $record): bool => $record->status?->isEditable() ?? false),
            DeleteAction::make()
                ->visible(fn (Newsletter $record): bool => $record->isDeletable()),
        ];
    }
}
