<?php

namespace App\Filament\Resources\Newsletters\RelationManagers;

use App\NewsletterSendStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewsletterSendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = 'Recipient Deliveries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('id')
            ->columns([
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (NewsletterSendStatus|string|null $state): string => $state instanceof NewsletterSendStatus ? $state->label() : ucfirst((string) $state))
                    ->color(static fn (NewsletterSendStatus|string|null $state): string => $state instanceof NewsletterSendStatus ? $state->color() : 'gray')
                    ->sortable(),
                TextColumn::make('queued_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('provider_message_id')
                    ->label('Provider Message ID')
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error')
                    ->limit(60)
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(NewsletterSendStatus::options()),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
