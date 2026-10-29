<?php

namespace App\Notifications;

use App\Http\Controllers\ForumController;
use App\Models\User;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\StandardCategories;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A reply in a thread the member follows, mailed to followers who take instant mail. The forums
 * package's own mail is switched off (`filagora-forums.subscriptions.mail.enabled`) so this one,
 * with lara-forum's wording, "view reply" and "manage subscriptions" buttons and unsubscribe
 * footer, goes out instead.
 */
class ThreadUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * A reply deleted before the worker runs is not mailed, and an edited one is mailed as it now reads.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly Thread $thread, private readonly Post $post) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $preferences = app(Preferences::class);

        return $preferences->mode($notifiable, StandardCategories::THREAD_UPDATES_DIGEST_FREQUENCY) === 'instant'
            && $preferences->canDeliver($notifiable, StandardCategories::THREAD_UPDATES_INSTANT)
            ? ['mail']
            : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $author = $this->post->author;
        $authorName = $author?->username ?? $author?->name ?? $author?->email ?? 'A user';
        $excerpt = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $this->post->body)) ?? ''), 180);
        $managePreferencesUrl = route('profile.show', ['tab' => 'settings']);
        $unsubscribe = app(Unsubscribe::class);

        $message = (new MailMessage)
            ->subject(__('notifications.thread.subjects.updated', ['thread' => $this->thread->title]))
            ->markdown('emails.notifications.thread-updated', [
                'threadTitle' => $this->thread->title,
                'authorName' => $authorName,
                'excerpt' => $excerpt !== '' ? $excerpt : __('notifications.thread.email.updated.fallback_excerpt'),
                'threadUrl' => route('threads.show', ['thread' => $this->thread, 'page' => $this->targetPage()]).'#post-'.$this->post->getKey(),
                'manageSubscriptionsUrl' => route('profile.show', ['tab' => 'subscriptions']),
                'managePreferencesUrl' => $managePreferencesUrl,
                'unsubscribeUrl' => $notifiable instanceof User
                    ? $unsubscribe->url($notifiable, StandardCategories::THREAD_UPDATES_INSTANT)
                    : $managePreferencesUrl,
            ]);

        return $notifiable instanceof User
            ? $unsubscribe->decorate($message, $notifiable, StandardCategories::THREAD_UPDATES_INSTANT)
            : $message;
    }

    /**
     * The page of the thread the reply lands on, from its place among the visible replies.
     */
    private function targetPage(): int
    {
        $position = Models::class('post')::query()
            ->where('thread_id', $this->thread->getKey())
            ->approved()
            ->where('is_deleted', false)
            ->where('id', '<=', $this->post->getKey())
            ->count();

        return max(1, (int) ceil($position / ForumController::THREAD_POSTS_PER_PAGE));
    }
}
