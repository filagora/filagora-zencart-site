<?php

namespace App\Notifications;

use App\Notifications\Channels\CoalescingDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * One row in a member's notification bell. The packages announce what happened and send their own
 * mail; this is the site's in-app side of the same events. Rows that share a `coalesce` key while
 * still unread fold into one with a running count ("12 replies") rather than piling up.
 */
class BellNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly string $category,
        private readonly int|string|null $actorId = null,
        private readonly ?string $coalesce = null,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [CoalescingDatabaseChannel::class];
    }

    /**
     * `actor_id` records the person named in the title so erasing them can remove what mentions them.
     *
     * @return array{title: string, body: string, url: string, category: string, actor_id: int|string|null, coalesce_key: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => Str::limit($this->body, 160),
            'url' => $this->url,
            'category' => $this->category,
            'actor_id' => $this->actorId,
            'coalesce_key' => $this->coalesce,
        ];
    }
}
