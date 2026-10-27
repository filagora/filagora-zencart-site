<?php

namespace App\Filament\Resources\NewsletterSubscribers\Tables;

use App\Models\NewsletterSubscriber;
use App\NewsletterSubscriberStatus;
use App\Services\NewsletterSubscriberService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (NewsletterSubscriberStatus|string|null $state): string => $state instanceof NewsletterSubscriberStatus ? $state->label() : ucfirst((string) $state))
                    ->color(static fn (NewsletterSubscriberStatus|string|null $state): string => $state instanceof NewsletterSubscriberStatus ? $state->color() : 'gray')
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('subscribed_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('unsubscribed_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(NewsletterSubscriberStatus::options()),
                SelectFilter::make('source')
                    ->options(fn (): array => NewsletterSubscriber::query()
                        ->whereNotNull('source')
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source', 'source')
                        ->all()),
                Filter::make('subscribed_at')
                    ->schema([
                        DatePicker::make('subscribed_from'),
                        DatePicker::make('subscribed_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['subscribed_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('subscribed_at', '>=', $date))
                            ->when($data['subscribed_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('subscribed_at', '<=', $date));
                    }),
                Filter::make('unsubscribed_at')
                    ->schema([
                        DatePicker::make('unsubscribed_from'),
                        DatePicker::make('unsubscribed_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['unsubscribed_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('unsubscribed_at', '>=', $date))
                            ->when($data['unsubscribed_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('unsubscribed_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('unsubscribe')
                    ->label('Unsubscribe')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->visible(fn (NewsletterSubscriber $record): bool => $record->status !== NewsletterSubscriberStatus::Unsubscribed)
                    ->requiresConfirmation()
                    ->modalDescription('This preserves the subscriber record and marks it as suppressed so it is not mailed again.')
                    ->action(function (NewsletterSubscriber $record): void {
                        app(NewsletterSubscriberService::class)->unsubscribe($record, 'admin');

                        Notification::make()
                            ->title('Newsletter subscriber unsubscribed')
                            ->success()
                            ->send();
                    }),
                Action::make('confirm_subscription')
                    ->label('Confirm subscription')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (NewsletterSubscriber $record): bool => $record->status !== NewsletterSubscriberStatus::Subscribed)
                    ->modalHeading('Confirm newsletter subscription')
                    ->modalDescription('Only use this after a verified subscriber request. This will make the email sendable.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Staff note')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (NewsletterSubscriber $record, array $data): void {
                        $record->fill([
                            'source' => 'admin',
                            'consent_text' => 'Manual staff confirmation: '.trim((string) $data['reason']),
                            'consent_version' => 'admin-v1',
                            'consent_ip' => null,
                            'consent_user_agent' => null,
                        ]);
                        $record->save();

                        app(NewsletterSubscriberService::class)->confirm($record);

                        Notification::make()
                            ->title('Newsletter subscription confirmed')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeCells)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_unsubscribe')
                        ->label('Unsubscribe selected')
                        ->color('danger')
                        ->icon('heroicon-o-no-symbol')
                        ->requiresConfirmation()
                        ->modalDescription('This preserves the subscriber records and marks each as suppressed so they are not mailed again.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $service = app(NewsletterSubscriberService::class);
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->status !== NewsletterSubscriberStatus::Unsubscribed) {
                                    $service->unsubscribe($record, 'admin_bulk');
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title($count.' subscriber(s) unsubscribed')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('bulk_export')
                        ->label('Export selected to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => self::exportToCsv($records)),
                ]),
            ]);
    }

    private static function exportToCsv(Collection $records): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['email', 'name', 'status', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at']);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->email,
                    $record->name,
                    $record->status instanceof NewsletterSubscriberStatus ? $record->status->value : (string) $record->status,
                    $record->source,
                    $record->subscribed_at?->toDateTimeString(),
                    $record->confirmed_at?->toDateTimeString(),
                    $record->unsubscribed_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d-His').'.csv');
    }
}
