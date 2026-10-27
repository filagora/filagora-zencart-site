<?php

namespace App\Filament\Resources\BugReports\Tables;

use App\BugReportStatus;
use App\Models\BugReport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BugReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('thread.title')
                    ->label(__('filament.resources.bug_reports.table.thread'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reportedBy.username')
                    ->label(__('filament.resources.bug_reports.table.reported_by'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assignedTo.username')
                    ->label(__('filament.resources.bug_reports.table.assigned_to'))
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof BugReportStatus) {
                            return $state->label();
                        }

                        return BugReportStatus::from((string) $state)->label();
                    })
                    ->colors([
                        'warning' => fn ($state): bool => $state instanceof BugReportStatus
                            ? $state === BugReportStatus::REPORTED
                            : (string) $state === BugReportStatus::REPORTED->value,
                        'info' => fn ($state): bool => $state instanceof BugReportStatus
                            ? $state === BugReportStatus::CONFIRMED
                            : (string) $state === BugReportStatus::CONFIRMED->value,
                        'success' => fn ($state): bool => $state instanceof BugReportStatus
                            ? $state === BugReportStatus::COMPLETED
                            : (string) $state === BugReportStatus::COMPLETED->value,
                        'primary' => fn ($state): bool => $state instanceof BugReportStatus
                            ? $state === BugReportStatus::FEATURE_REQUEST
                            : (string) $state === BugReportStatus::FEATURE_REQUEST->value,
                        'danger' => fn ($state): bool => $state instanceof BugReportStatus
                            ? $state === BugReportStatus::WONT_FIX
                            : (string) $state === BugReportStatus::WONT_FIX->value,
                    ])
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('hide_closed')
                    ->label('Hide closed')
                    ->query(fn (Builder $query): Builder => $query->whereNotIn('status', BugReportStatus::terminalValues()))
                    ->default(),
                SelectFilter::make('status')
                    ->options(collect(BugReportStatus::cases())->mapWithKeys(
                        fn (BugReportStatus $status): array => [$status->value => $status->label()]
                    )),
            ])
            ->recordActions([
                Action::make('viewThread')
                    ->label('View Thread')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (BugReport $record): string => route('threads.show', $record->thread_id))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
