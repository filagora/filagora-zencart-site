<?php

namespace App\Listeners;

use App\Notifications\BugReportUpdatedNotification;
use Filagora\ForumBugReports\Events\BugReportChanged;
use Illuminate\Support\Facades\Notification;

/**
 * Tells everyone following a thread when a bug report on it is opened or changed, except the person
 * who did it.
 */
class SendBugReportNotification
{
    public function handle(BugReportChanged $event): void
    {
        $subscribers = $event->bugReport->thread->subscribedUsers()
            ->when($event->actorId !== null, fn ($query) => $query->where('users.id', '!=', $event->actorId))
            ->get();

        if ($subscribers->isNotEmpty()) {
            Notification::send($subscribers, new BugReportUpdatedNotification($event->bugReport, $event->changes, $event->actorId));
        }
    }
}
