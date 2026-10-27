<?php

namespace App\Filament\Resources\Newsletters\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsletterLinkClicksRelationManager extends RelationManager
{
    protected static string $relationship = 'linkClicks';

    protected static ?string $title = 'Link Clicks';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('clicked_at', 'desc')
            ->columns([
                TextColumn::make('label')
                    ->placeholder('Untitled link')
                    ->searchable(),
                TextColumn::make('destination_url')
                    ->label('Destination')
                    ->limit(50)
                    ->copyable(),
                TextColumn::make('send.email')
                    ->label('Recipient')
                    ->placeholder('Preview / test send')
                    ->searchable(),
                TextColumn::make('clicked_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('block_key')
                    ->label('Block Key')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
