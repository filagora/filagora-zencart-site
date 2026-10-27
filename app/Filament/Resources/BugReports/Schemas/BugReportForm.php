<?php

namespace App\Filament\Resources\BugReports\Schemas;

use App\BugReportStatus;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BugReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('thread.title')
                    ->label(__('filament.resources.bug_reports.form.thread_id'))
                    ->content(fn ($record): string => (string) ($record?->thread?->title ?? '-')),
                Placeholder::make('reportedBy.username')
                    ->label(__('filament.resources.bug_reports.form.reported_by'))
                    ->content(fn ($record): string => (string) ($record?->reportedBy?->username ?? $record?->reportedBy?->email ?? __('filament.common.unknown'))),
                Textarea::make('description')
                    ->rows(4)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Select::make('status')
                    ->required()
                    ->options(collect(BugReportStatus::cases())->mapWithKeys(
                        fn (BugReportStatus $status): array => [$status->value => $status->label()]
                    )),
                Select::make('assigned_to_id')
                    ->label(__('filament.resources.bug_reports.table.assigned_to'))
                    ->relationship('assignedTo', 'username')
                    ->searchable()
                    ->preload(),
                Textarea::make('resolution_note')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ]);
    }
}
