<?php

namespace App\Filament\Resources\NewsletterBlocks\Tables;

use App\Models\NewsletterBlock;
use App\NewsletterBlockType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NewsletterBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(static fn (NewsletterBlockType|string|null $state): string => $state instanceof NewsletterBlockType ? $state->label() : ucfirst((string) $state))
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(NewsletterBlockType::options()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (NewsletterBlock $record): bool => ! $record->is_system),
            ]);
    }
}
