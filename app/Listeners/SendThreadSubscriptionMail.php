<?php

namespace App\Listeners;

use App\Notifications\ThreadDigestNotification;
use App\Notifications\ThreadUpdatedNotification;
use Filagora\Forums\Events\SubscribersToNotify;
use Filagora\Forums\Events\ThreadDigestReady;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the site's own thread-update and digest mail from the forums package's events, in place of
 * the package's default mail (switched off in config/filagora-forums.php).
 */
class SendThreadSubscriptionMail
{
    public function updated(SubscribersToNotify $event): void
    {
        if ($event->recipients !== []) {
            Notification::send($event->recipients, new ThreadUpdatedNotification($event->thread, $event->post));
        }
    }

    public function digest(ThreadDigestReady $event): void
    {
        Notification::send($event->user, new ThreadDigestNotification($event->frequency, $event->items));
    }
}
