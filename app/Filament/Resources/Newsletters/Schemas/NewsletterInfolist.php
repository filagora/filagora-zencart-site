<?php

namespace App\Filament\Resources\Newsletters\Schemas;

use App\Models\Newsletter;
use App\NewsletterEventType;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class NewsletterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('subject'),
                        TextEntry::make('preheader')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(static fn (NewsletterStatus|string|null $state): string => $state instanceof NewsletterStatus ? $state->label() : ucfirst((string) $state))
                            ->color(static fn (NewsletterStatus|string|null $state): string => $state instanceof NewsletterStatus ? $state->color() : 'gray'),
                        TextEntry::make('creator.email')
                            ->label('Created by')
                            ->placeholder('-'),
                        TextEntry::make('updater.email')
                            ->label('Updated by')
                            ->placeholder('-'),
                        TextEntry::make('scheduled_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('sent_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Delivery')
                    ->schema([
                        TextEntry::make('sends_queued')
                            ->label('Queued')
                            ->state(fn (Newsletter $record): int => self::sendCount($record, NewsletterSendStatus::Queued)),
                        TextEntry::make('sends_sending')
                            ->label('Sending')
                            ->state(fn (Newsletter $record): int => self::sendCount($record, NewsletterSendStatus::Sending)),
                        TextEntry::make('sends_sent')
                            ->label('Sent')
                            ->state(fn (Newsletter $record): int => self::sendCount($record, NewsletterSendStatus::Sent)),
                        TextEntry::make('sends_failed')
                            ->label('Failed')
                            ->state(fn (Newsletter $record): int => self::sendCount($record, NewsletterSendStatus::Failed)),
                        TextEntry::make('sends_skipped')
                            ->label('Skipped')
                            ->state(fn (Newsletter $record): int => self::sendCount($record, NewsletterSendStatus::Skipped)),
                    ])
                    ->columns(5)
                    ->visible(fn (Newsletter $record): bool => $record->sends()->exists()),
                Section::make('Engagement')
                    ->schema([
                        TextEntry::make('events_delivered')
                            ->label('Delivered')
                            ->state(fn (Newsletter $record): int => self::eventCount($record, NewsletterEventType::Delivered)),
                        TextEntry::make('events_opened')
                            ->label('Opened (provider)')
                            ->state(fn (Newsletter $record): int => self::eventCount($record, NewsletterEventType::Opened)),
                        TextEntry::make('events_bounced')
                            ->label('Bounced')
                            ->state(fn (Newsletter $record): int => self::eventCount($record, NewsletterEventType::Bounced)),
                        TextEntry::make('events_complained')
                            ->label('Complained')
                            ->state(fn (Newsletter $record): int => self::eventCount($record, NewsletterEventType::Complained)),
                        TextEntry::make('link_clicks_total')
                            ->label('Link Clicks')
                            ->state(fn (Newsletter $record): int => $record->linkClicks()->count()),
                        TextEntry::make('link_clicks_unique_links')
                            ->label('Links Clicked')
                            ->state(fn (Newsletter $record): int => $record->linkClicks()->distinct('block_key')->count('block_key')),
                    ])
                    ->columns(3)
                    ->visible(fn (Newsletter $record): bool => $record->sends()->exists() || $record->linkClicks()->exists()),
                Section::make('Content')
                    ->schema([
                        TextEntry::make('content_blocks')
                            ->label('Structured blocks')
                            ->state(fn (Newsletter $record): string => sprintf(
                                '%d block%s',
                                count($record->content_blocks ?? []),
                                count($record->content_blocks ?? []) === 1 ? '' : 's'
                            ))
                            ->helperText('Stored campaigns include shared header and footer blocks around the editable content blocks.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Click Breakdown')
                    ->schema([
                        View::make('filament.newsletters.click-breakdown')
                            ->viewData(fn (Newsletter $record): array => [
                                'clicks' => self::clickBreakdown($record),
                            ]),
                    ])
                    ->visible(fn (Newsletter $record): bool => $record->linkClicks()->exists()),
                Section::make('Rendered Preview')
                    ->schema([
                        View::make('filament.newsletters.rendered-preview')
                            ->viewData(fn (?Newsletter $record): array => [
                                'previewUrl' => $record instanceof Newsletter
                                    ? route('newsletters.preview', $record)
                                    : null,
                            ]),
                    ]),
            ]);
    }

    private static function sendCount(Newsletter $record, NewsletterSendStatus $status): int
    {
        return $record->sends()->where('status', $status->value)->count();
    }

    private static function eventCount(Newsletter $record, NewsletterEventType $type): int
    {
        return $record->sends()
            ->whereHas('events', fn ($query) => $query->where('type', $type->value))
            ->count();
    }

    /**
     * @return Collection<int, array{label: string, clicks: int, unique_recipients: int, newsletter_block_id: int|null}>
     */
    private static function clickBreakdown(Newsletter $record): Collection
    {
        return $record->linkClicks()
            ->selectRaw('block_key, label, newsletter_block_id, COUNT(*) as clicks, COUNT(DISTINCT newsletter_send_id) as unique_recipients')
            ->groupBy('block_key', 'label', 'newsletter_block_id')
            ->orderByDesc('clicks')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label ?: 'Untitled link',
                'clicks' => (int) $row->clicks,
                'unique_recipients' => (int) $row->unique_recipients,
                'newsletter_block_id' => $row->newsletter_block_id,
            ]);
    }
}
