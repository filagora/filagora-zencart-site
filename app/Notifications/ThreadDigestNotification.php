<?php

namespace App\Notifications;

use App\Http\Controllers\ForumController;
use App\Models\User;
use Filagora\Forums\Enums\DeliveryMode;
use Filagora\Forums\Models\ThreadSubscriptionDigestItem;
use Filagora\Forums\Support\Models;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\StandardCategories;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily or weekly digest of replies in followed threads, with lara-forum's wording and an
 * unsubscribe footer. The forums package's own digest mail is switched off in favour of this one.
 */
class ThreadDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const MAX_ITEMS = 20;

    /**
     * @param  list<ThreadSubscriptionDigestItem>  $items
     */
    public function __construct(private readonly DeliveryMode $frequency, private readonly array $items) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ! $notifiable instanceof User || app(Preferences::class)->canDeliver($notifiable, StandardCategories::THREAD_UPDATES_DIGEST)
            ? ['mail']
            : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subjectPrefix = $this->frequency === DeliveryMode::WEEKLY
            ? __('notifications.thread.frequency.weekly')
            : __('notifications.thread.frequency.daily');
        $items = [];

        foreach (array_slice($this->items, 0, self::MAX_ITEMS) as $item) {
            $thread = $item->thread;
            $post = $item->post;

            if ($thread === null || $post === null) {
                continue;
            }

            $items[] = [
                'title' => $thread->title,
                'url' => route('threads.show', ['thread' => $thread, 'page' => $this->targetPage($thread->getKey(), $post->getKey())]).'#post-'.$post->getKey(),
            ];
        }

        $managePreferencesUrl = route('profile.show', ['tab' => 'settings']);
        $unsubscribe = app(Unsubscribe::class);

        $message = (new MailMessage)
            ->subject(__('notifications.thread.subjects.digest', ['prefix' => $subjectPrefix]))
            ->markdown('emails.notifications.thread-digest', [
                'subjectPrefix' => $subjectPrefix,
                'items' => $items,
                'hasMore' => count($this->items) > self::MAX_ITEMS,
                'profileUrl' => $managePreferencesUrl,
                'unsubscribeUrl' => $notifiable instanceof User
                    ? $unsubscribe->url($notifiable, StandardCategories::THREAD_UPDATES_DIGEST)
                    : $managePreferencesUrl,
            ]);

        return $notifiable instanceof User
            ? $unsubscribe->decorate($message, $notifiable, StandardCategories::THREAD_UPDATES_DIGEST)
            : $message;
    }

    private function targetPage(int|string $threadId, int|string $postId): int
    {
        $position = Models::class('post')::query()
            ->where('thread_id', $threadId)
            ->approved()
            ->where('is_deleted', false)
            ->where('id', '<=', $postId)
            ->count();

        return max(1, (int) ceil($position / ForumController::THREAD_POSTS_PER_PAGE));
    }
}
