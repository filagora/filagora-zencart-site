<?php

namespace App\Notifications;

use App\Forum\PostLinks;
use App\Models\User;
use Filagora\Forums\Events\PostModerated;
use Filagora\Forums\Events\PostReported;
use Filagora\Forums\Events\SubscribersToNotify as ThreadSubscribersToNotify;
use Filagora\Forums\Events\ThreadModerated;
use Filagora\Forums\Events\UsersMentioned;
use Filagora\Messages\Events\MessageReceived;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\StandardCategories;
use Filagora\Plugins\Events\ReleaseRejected;
use Filagora\Plugins\Events\SubscribersToNotify as PluginSubscribersToNotify;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The site's in-app side of what the packages announce: a followed thread has a new reply, someone
 * was mentioned or sent a message, a moderator acted on someone's post, a followed plugin has a
 * new release or one was rejected. The packages send the mail; each listener here adds a bell row
 * for the people whose notification choices allow it.
 */
class BellListeners
{
    /** Registered by the Zen Cart profile. */
    private const PLUGIN_UPDATES = 'plugins.release_updates';

    public function __construct(
        private readonly Preferences $preferences,
        private readonly PostLinks $links,
    ) {}

    public function threadUpdated(ThreadSubscribersToNotify $event): void
    {
        $post = $event->post;
        $author = $post->author;
        $name = $this->nameOf($author);
        $url = $this->links->urlFor($event->thread, $post);

        foreach ($this->users($event->recipients) as $user) {
            if ($this->wants($user, StandardCategories::THREAD_UPDATES_INSTANT)) {
                $user->notify(new BellNotification((string) $event->thread->title, $name.': '.$this->plain((string) $post->body), $url, StandardCategories::THREAD_UPDATES_INSTANT, $author?->getKey(), 'thread:'.$event->thread->getKey()));
            }
        }
    }

    public function mentioned(UsersMentioned $event): void
    {
        $post = $event->post;
        $thread = $post->thread;
        $url = $thread !== null ? $this->links->urlFor($thread, $post) : route('forums.index');

        foreach ($this->users($event->users) as $user) {
            if ($this->wants($user, StandardCategories::MENTION)) {
                $user->notify(new BellNotification(__('notifications.mention.push.title', ['author' => $this->nameOf($post->author)]), $this->plain((string) $post->body), $url, StandardCategories::MENTION, $post->author?->getKey()));
            }
        }
    }

    public function messageReceived(MessageReceived $event): void
    {
        $sender = $event->message->sender;
        $url = route('messages.show', $event->conversation);

        foreach ($this->users($event->recipients) as $user) {
            if ($this->wants($user, StandardCategories::MESSAGES_RECEIVED)) {
                $user->notify(new BellNotification(__('notifications.private_message.push.title', ['sender' => $this->nameOf($sender)]), $this->plain((string) $event->message->body), $url, StandardCategories::MESSAGES_RECEIVED, $sender?->getKey()));
            }
        }
    }

    /**
     * Mails the forum support address about a new post report, when one is set.
     */
    public function postReported(PostReported $event): void
    {
        $address = trim((string) config('forums.support_email'));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
            $event->report->loadMissing('reporter:id,username,email');
            Notification::route('mail', $address)->notify(new PostReportSubmittedNotification($event->report));
        }
    }

    /**
     * The moderator actions the post's author is told about; approving or restoring is not news.
     *
     * @var list<string>
     */
    private const ADVERSE_ACTIONS = ['delete', 'reject', 'reject_permanently', 'move'];

    public function postModerated(PostModerated $event): void
    {
        $post = $event->post;

        // Not a moderator acting on their own post, and only for adverse actions.
        if (! in_array($event->action, self::ADVERSE_ACTIONS, true) || (string) $post->author_id === (string) $event->moderator->getAuthIdentifier()) {
            return;
        }

        $thread = $post->thread;

        $this->tellAuthor(
            $post->author,
            $event->action,
            $event->reason,
            $thread !== null ? route('threads.show', $thread) : route('forums.index'),
        );
    }

    public function threadModerated(ThreadModerated $event): void
    {
        $thread = $event->thread;

        $this->tellAuthor($thread->author, $event->action, $event->reason, route('threads.show', $thread));
    }

    public function pluginUpdated(PluginSubscribersToNotify $event): void
    {
        foreach ($this->users($event->recipients) as $user) {
            if (! $this->wants($user, self::PLUGIN_UPDATES)) {
                continue;
            }

            $user->notify(new BellNotification(
                __('notifications.plugin_updated.title', ['plugin' => $event->plugin->name, 'version' => $event->release->displayVersion()]),
                trim((string) $event->release->changelog),
                route('plugins.show', $event->plugin),
                self::PLUGIN_UPDATES,
                null,
                'plugin:'.$event->plugin->getKey(),
            ));
        }
    }

    public function releaseRejected(ReleaseRejected $event): void
    {
        $author = $event->release->uploadedBy ?? $event->plugin->owner;

        if ($author instanceof User && ($event->except === null || $author->getKey() !== $event->except->getAuthIdentifier())) {
            $author->notify(new BellNotification(
                __('notifications.plugin_release_rejected.title', ['plugin' => $event->plugin->name, 'version' => $event->release->displayVersion()]),
                (string) $event->note,
                route('plugins.show', $event->plugin),
                self::PLUGIN_UPDATES,
            ));
        }
    }

    private function tellAuthor(?object $author, string $action, ?string $reason, string $url): void
    {
        if (! $author instanceof User || ! $this->wants($author, StandardCategories::MODERATION_OWN_CONTENT)) {
            return;
        }

        $title = __(match ($action) {
            'delete' => 'notifications.post_moderated.push.title.removed',
            'reject', 'reject_permanently' => 'notifications.post_moderated.push.title.rejected',
            'move' => 'notifications.post_moderated.push.title.moved',
            default => 'notifications.post_moderated.push.title.default',
        });

        $author->notify(new BellNotification($title, $reason !== null && $reason !== '' ? $reason : __('notifications.post_moderated.push.body_fallback'), $url, StandardCategories::MODERATION_OWN_CONTENT));
    }

    private function wants(Authenticatable $user, string $category): bool
    {
        return $this->preferences->allows($user, $category, NotificationChannel::IN_APP);
    }

    /**
     * @param  iterable<mixed>  $candidates
     * @return list<User>
     */
    private function users(iterable $candidates): array
    {
        $users = [];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof User) {
                $users[] = $candidate;
            }
        }

        return $users;
    }

    private function nameOf(?object $user): string
    {
        return (string) ($user?->username ?? $user?->name ?? $user?->email ?? 'A user');
    }

    private function plain(string $body): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($body))), 160);
    }
}
