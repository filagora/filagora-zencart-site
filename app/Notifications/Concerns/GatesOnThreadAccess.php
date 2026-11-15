<?php

namespace App\Notifications\Concerns;

use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * For queued notifications that link into a thread. Someone who could read it when the notification
 * was created may not by the time a worker sends it (a forum locked down in between), so `via()`
 * asks again and sends nothing if they cannot.
 */
trait GatesOnThreadAccess
{
    protected function recipientCanReadThread(object $notifiable, ?Model $thread): bool
    {
        if (! $notifiable instanceof Authenticatable || ! $thread instanceof Thread) {
            return true;
        }

        return app(ForumAccess::class)->canReadThread($thread, $notifiable);
    }
}
