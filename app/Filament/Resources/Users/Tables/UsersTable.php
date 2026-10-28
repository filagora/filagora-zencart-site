<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\UserOperatorAccessManager;
use App\Support\UserOperatorAccessAssignment;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('operator_access')
                    ->label('Operator Access')
                    ->state(function (User $record): string {
                        $entitlements = app(UserOperatorAccessManager::class)->selectedOperatorEntitlements($record);

                        return $entitlements !== []
                            ? implode(', ', UserOperatorAccessAssignment::labelsFor($entitlements))
                            : 'None';
                    }),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),
                IconColumn::make('is_banned')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_banned')
                    ->label('Ban Status')
                    ->options([
                        '1' => 'Banned',
                        '0' => 'Active',
                    ])
                    ->query(function ($query, array $data) {
                        if (! array_key_exists('value', $data) || $data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('is_banned', $data['value'] === '1');
                    }),
                SelectFilter::make('is_verified')
                    ->label('Verification')
                    ->options([
                        '1' => 'Verified',
                        '0' => 'Unverified',
                    ])
                    ->query(function ($query, array $data) {
                        if (! array_key_exists('value', $data) || $data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $data['value'] === '1'
                            ? $query->whereNotNull('email_verified_at')
                            : $query->whereNull('email_verified_at');
                    }),
                SelectFilter::make('allow_private_messages')
                    ->label('Private Messages')
                    ->options([
                        '1' => 'Allowed',
                        '0' => 'Disabled',
                    ])
                    ->query(function ($query, array $data) {
                        if (! array_key_exists('value', $data) || $data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('allow_private_messages', $data['value'] === '1');
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeCells);
    }
}
