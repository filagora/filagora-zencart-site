<?php

namespace App\Listeners;

use App\Notifications\ForumThreadCreatedNotification;
use Filagora\Forums\Events\ForumSubscribersToNotify;
use Filagora\Messages\Models\IgnoredUser;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Push\Notifications\WebPushAlert;
use Filagora\Push\Services\PushSubscriptions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tells a forum's subscribers about a new thread, leaving out anyone who ignores its author. The
 * forums package decides who may read the thread; ignoring is the site's rule.
 */
class SendForumThreadNotifications
{
    public function __construct(
        private readonly Preferences $preferences,
        private readonly PushSubscriptions $pushSubscriptions,
    ) {}

    public function send(ForumSubscribersToNotify $event): void
    {
        $recipients = $this->withoutThoseIgnoring($event->recipients, $event->thread->author_id);

        if ($recipients === []) {
            return;
        }

        $notification = new ForumThreadCreatedNotification($event->thread);

        Notification::send($recipients, $notification);

        foreach ($recipients as $recipient) {
            $this->push($recipient, $event->thread, $notification);
        }
    }

    /**
     * @param  list<Authenticatable>  $recipients
     * @return list<Authenticatable>
     */
    private function withoutThoseIgnoring(array $recipients, int|string|null $authorId): array
    {
        if ($authorId === null) {
            return $recipients;
        }

        $ignoring = IgnoredUser::query()
            ->where('ignored_user_id', $authorId)
            ->whereIn('user_id', array_map(fn (Authenticatable $user) => $user->getAuthIdentifier(), $recipients))
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return array_values(array_filter(
            $recipients,
            fn (Authenticatable $user): bool => ! in_array((string) $user->getAuthIdentifier(), $ignoring, true),
        ));
    }

    private function push(Authenticatable $recipient, object $thread, ForumThreadCreatedNotification $notification): void
    {
        if (! $this->preferences->allows($recipient, ForumThreadCreatedNotification::CATEGORY, NotificationChannel::PUSH)) {
            return;
        }

        $subscriptions = $this->pushSubscriptions->for($recipient);

        if ($subscriptions->isNotEmpty()) {
            Notification::route('WebPush', $subscriptions)->notify(new WebPushAlert(
                $notification->title(),
                Str::limit($notification->summary(), 140),
                route('threads.show', $thread),
                'forum-'.$thread->forum_id,
            ));
        }
    }
}
