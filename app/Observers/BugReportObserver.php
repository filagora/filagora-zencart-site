<?php

namespace App\Observers;

use App\BugReportStatus;
use App\Models\BugReport;
use App\Notifications\BugReportUpdatedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Tells everyone following a thread when a bug report on it is opened or changed. It sits on the
 * model so the thread page and the staff screen both trigger it.
 */
class BugReportObserver
{
    public function created(BugReport $bugReport): void
    {
        $this->notifySubscribers($bugReport, [__('notifications.bug_report.changes.reported', ['status' => $bugReport->status->label()])]);
    }

    public function updated(BugReport $bugReport): void
    {
        $changes = [];

        if ($bugReport->wasChanged('status')) {
            $previous = $bugReport->getOriginal('status');
            $changes[] = __('notifications.bug_report.changes.status', [
                'from' => $previous instanceof BugReportStatus ? $previous->label() : (string) $previous,
                'to' => $bugReport->status->label(),
            ]);
        }

        if ($bugReport->wasChanged('assigned_to_id')) {
            $assignee = $bugReport->assignedTo;
            $changes[] = $assignee === null
                ? __('notifications.bug_report.changes.unassigned')
                : __('notifications.bug_report.changes.assigned', ['name' => $assignee->username ?? $assignee->name]);
        }

        if ($bugReport->wasChanged('resolution_note') && filled($bugReport->resolution_note)) {
            $changes[] = __('notifications.bug_report.changes.note', ['note' => $bugReport->resolution_note]);
        }

        if ($changes !== []) {
            $this->notifySubscribers($bugReport, $changes);
        }
    }

    /**
     * @param  list<string>  $changes
     */
    private function notifySubscribers(BugReport $bugReport, array $changes): void
    {
        $actorId = auth()->id();

        $subscribers = $bugReport->thread->subscribedUsers()
            ->when($actorId !== null, fn ($query) => $query->where('users.id', '!=', $actorId))
            ->get();

        if ($subscribers->isNotEmpty()) {
            Notification::send($subscribers, new BugReportUpdatedNotification($bugReport, $changes, $actorId));
        }
    }
}
