<?php

namespace App\Filament\Resources\NewsletterBlocks\Schemas;

use App\Models\Newsletter;
use App\Models\NewsletterBlock;
use App\Models\NewsletterLinkClick;
use App\NewsletterBlockType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsletterBlockInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Block')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('key')->copyable(),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(static fn (NewsletterBlockType|string|null $state): string => $state instanceof NewsletterBlockType ? $state->label() : ucfirst((string) $state)),
                        TextEntry::make('is_system')
                            ->label('System block')
                            ->formatStateUsing(static fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('is_active')
                            ->label('Active')
                            ->formatStateUsing(static fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('updater.email')
                            ->label('Updated by')
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Used in')
                    ->schema([
                        TextEntry::make('usage')
                            ->label('Referenced by')
                            ->state(fn (NewsletterBlock $record): string => self::usageSummary($record)),
                    ])
                    ->visible(fn (NewsletterBlock $record): bool => ! $record->is_system),
                Section::make('Click Performance')
                    ->description('Aggregated across every campaign that has referenced this block.')
                    ->schema([
                        TextEntry::make('total_clicks')
                            ->label('Total Clicks')
                            ->state(fn (NewsletterBlock $record): int => NewsletterLinkClick::query()->where('newsletter_block_id', $record->id)->count()),
                        TextEntry::make('campaigns_clicked_in')
                            ->label('Campaigns Clicked In')
                            ->state(fn (NewsletterBlock $record): int => NewsletterLinkClick::query()->where('newsletter_block_id', $record->id)->distinct('newsletter_id')->count('newsletter_id')),
                        TextEntry::make('unique_recipients_clicked')
                            ->label('Unique Recipients')
                            ->state(fn (NewsletterBlock $record): int => NewsletterLinkClick::query()->where('newsletter_block_id', $record->id)->whereNotNull('newsletter_send_id')->distinct('newsletter_send_id')->count('newsletter_send_id')),
                    ])
                    ->columns(3)
                    ->visible(fn (NewsletterBlock $record): bool => ! $record->is_system && NewsletterLinkClick::query()->where('newsletter_block_id', $record->id)->exists()),
            ]);
    }

    private static function usageSummary(NewsletterBlock $record): string
    {
        $count = Newsletter::query()
            ->get(['id', 'content_blocks'])
            ->filter(fn (Newsletter $newsletter): bool => collect($newsletter->content_blocks ?? [])
                ->contains(fn (array $block): bool => ($block['type'] ?? null) === 'reusable' && (int) ($block['newsletter_block_id'] ?? 0) === $record->id))
            ->count();

        return $count === 1 ? 'Used in 1 newsletter' : "Used in {$count} newsletters";
    }
}
