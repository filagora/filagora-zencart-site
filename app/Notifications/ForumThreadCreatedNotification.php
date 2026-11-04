<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\CoalescingDatabaseChannel;
use App\Support\LegacyTextDecoder;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A new thread in a forum the member subscribes to, by mail and in the bell. The forums package's
 * own mail is switched off, so this one, with lara-forum's wording, goes out instead. Browser push
 * is sent by `SendForumThreadNotifications`.
 */
class ForumThreadCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const CATEGORY = 'forum.new_threads';

    /**
     * A thread deleted before the worker runs is not announced.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly Thread $thread) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $preferences = app(Preferences::class);
        $channels = [];

        if ($preferences->canDeliver($notifiable, self::CATEGORY)) {
            $channels[] = 'mail';
        }

        if ($preferences->allows($notifiable, self::CATEGORY, NotificationChannel::IN_APP)) {
            $channels[] = CoalescingDatabaseChannel::class;
        }

        return $channels;
    }

    /**
     * @return array{title: string, body: string, url: string, category: string, actor_id: int|null, coalesce_key: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => Str::limit($this->summary(), 160),
            'url' => route('threads.show', $this->thread),
            'category' => self::CATEGORY,
            'actor_id' => $this->thread->author_id !== null ? (int) $this->thread->author_id : null,
            'coalesce_key' => 'forum:'.$this->thread->forum_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $forumName = LegacyTextDecoder::decode($this->thread->forum?->name);
        $excerpt = $this->excerpt();
        $managePreferencesUrl = route('profile.show', ['tab' => 'settings']);
        $unsubscribe = app(Unsubscribe::class);

        $message = (new MailMessage)
            ->subject(__('notifications.forum_thread_created.subject', ['forum' => $forumName, 'thread' => $this->title()]))
            ->markdown('emails.notifications.forum-thread-created', [
                'forumName' => $forumName,
                'threadTitle' => $this->title(),
                'authorName' => $this->authorName(),
                'excerpt' => $excerpt !== '' ? $excerpt : __('notifications.forum_thread_created.fallback_excerpt'),
                'threadUrl' => route('threads.show', $this->thread),
                'manageSubscriptionsUrl' => route('profile.show', ['tab' => 'subscriptions']),
                'managePreferencesUrl' => $managePreferencesUrl,
                'unsubscribeUrl' => $notifiable instanceof User
                    ? $unsubscribe->url($notifiable, self::CATEGORY)
                    : $managePreferencesUrl,
            ]);

        return $notifiable instanceof User
            ? $unsubscribe->decorate($message, $notifiable, self::CATEGORY)
            : $message;
    }

    /**
     * "Author: the opening words", for the bell and the browser push.
     */
    public function summary(): string
    {
        $excerpt = $this->excerpt();

        return $this->authorName().': '.($excerpt !== '' ? $excerpt : __('notifications.forum_thread_created.fallback_excerpt'));
    }

    /**
     * The thread's title as readers see it: imported ones can hold old forum entities.
     */
    public function title(): string
    {
        return LegacyTextDecoder::decode($this->thread->title);
    }

    private function authorName(): string
    {
        $author = $this->thread->author;

        return $author?->username ?? $author?->name ?? $author?->email ?? 'A user';
    }

    private function excerpt(): string
    {
        $first = Models::class('post')::query()
            ->where('thread_id', $this->thread->getKey())
            ->approved()
            ->where('is_deleted', false)
            ->oldest('id')
            ->first();

        return $first === null ? '' : Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $first->body)) ?? ''), 180);
    }
}
