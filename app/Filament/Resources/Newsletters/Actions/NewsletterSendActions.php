<?php

namespace App\Filament\Resources\Newsletters\Actions;

use App\Models\Newsletter;
use App\NewsletterStatus;
use App\Services\NewsletterCampaignMailer;
use App\Services\NewsletterCampaignSender;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class NewsletterSendActions
{
    public static function sendTest(): Action
    {
        return Action::make('send_test')
            ->label('Send test')
            ->icon('heroicon-o-paper-airplane')
            ->schema([
                TextInput::make('email')
                    ->label('Test recipient')
                    ->email()
                    ->required()
                    ->default(fn (): ?string => auth()->user()?->email)
                    ->maxLength(255),
            ])
            ->action(function (Newsletter $record, array $data): void {
                app(NewsletterCampaignMailer::class)->sendPreview($record, (string) $data['email']);

                Notification::make()
                    ->title('Newsletter test email sent')
                    ->body('The preview was sent to '.$data['email'].'.')
                    ->success()
                    ->send();
            });
    }

    public static function queueSend(): Action
    {
        return Action::make('queue_send')
            ->label('Queue send')
            ->color('success')
            ->icon('heroicon-o-envelope')
            ->visible(fn (Newsletter $record): bool => $record->status?->canBeQueued() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Queue newsletter send')
            ->modalDescription('This will create delivery records for currently subscribed, non-suppressed newsletter subscribers and queue one email job per recipient.')
            ->action(function (Newsletter $record): void {
                $result = app(NewsletterCampaignSender::class)->queue($record);

                if ($result['queued'] === 0) {
                    Notification::make()
                        ->title('No sendable newsletter subscribers found')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Newsletter send queued')
                    ->body($result['queued'].' delivery record(s) created; '.$result['dispatched'].' recipient job(s) dispatched today.')
                    ->success()
                    ->send();
            });
    }

    public static function scheduleSend(): Action
    {
        return Action::make('schedule_send')
            ->label(fn (Newsletter $record): string => $record->status === NewsletterStatus::Scheduled ? 'Reschedule' : 'Schedule')
            ->icon('heroicon-o-clock')
            ->visible(fn (Newsletter $record): bool => in_array($record->status, [NewsletterStatus::Draft, NewsletterStatus::Scheduled], true))
            ->schema([
                DateTimePicker::make('scheduled_at')
                    ->label('Send after')
                    ->required()
                    ->seconds(false)
                    ->minDate(now())
                    ->default(fn (Newsletter $record) => $record->scheduled_at),
            ])
            ->action(function (Newsletter $record, array $data): void {
                $record->fill([
                    'status' => NewsletterStatus::Scheduled,
                    'scheduled_at' => $data['scheduled_at'],
                ]);
                $record->save();

                Notification::make()
                    ->title('Newsletter send scheduled')
                    ->body('The campaign will be queued after '.$record->scheduled_at?->toDayDateTimeString().'.')
                    ->success()
                    ->send();
            });
    }

    public static function cancelSchedule(): Action
    {
        return Action::make('cancel_schedule')
            ->label('Cancel schedule')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->visible(fn (Newsletter $record): bool => $record->status === NewsletterStatus::Scheduled)
            ->requiresConfirmation()
            ->modalDescription('This newsletter will return to draft and will not send automatically.')
            ->action(function (Newsletter $record): void {
                $record->fill([
                    'status' => NewsletterStatus::Draft,
                    'scheduled_at' => null,
                ]);
                $record->save();

                Notification::make()
                    ->title('Newsletter schedule canceled')
                    ->success()
                    ->send();
            });
    }

    public static function statusBadge(Newsletter $record): string|Htmlable|null
    {
        if (! $record->status instanceof NewsletterStatus) {
            return null;
        }

        return new HtmlString(Blade::render(
            '<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>',
            ['color' => $record->status->color(), 'label' => $record->status->label()],
        ));
    }
}
