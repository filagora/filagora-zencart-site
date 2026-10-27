<?php

namespace App\Filament\Resources\BugReports\Pages;

use App\BugReportStatus;
use App\Filament\Resources\BugReports\BugReportResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBugReport extends EditRecord
{
    protected static string $resource = BugReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewThread')
                ->label('View Thread')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('threads.show', $this->record->thread_id))
                ->openUrlInNewTab(),
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $status = BugReportStatus::from((string) ($data['status'] ?? ''));

        if ($status === BugReportStatus::CONFIRMED) {
            $data['confirmed_at'] = $this->record->confirmed_at ?? now();
            $data['completed_at'] = null;

            return $data;
        }

        if ($status->isTerminal()) {
            $data['completed_at'] = $this->record->completed_at ?? now();

            return $data;
        }

        $data['confirmed_at'] = null;
        $data['completed_at'] = null;

        return $data;
    }
}
