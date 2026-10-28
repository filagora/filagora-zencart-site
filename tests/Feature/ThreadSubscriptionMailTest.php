<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ThreadDigestNotification;
use App\Notifications\ThreadUpdatedNotification;
use Filagora\Forums\Enums\DeliveryMode;
use Filagora\Forums\Events\SubscribersToNotify;
use Filagora\Forums\Events\ThreadDigestReady;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscriptionDigestItem;
use Filagora\Forums\Notifications\ThreadDigestNotification as PackageThreadDigestNotification;
use Filagora\Forums\Notifications\ThreadUpdatedNotification as PackageThreadUpdatedNotification;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * The thread-update and digest mail reads as it did on the old site: wording, the view and manage
 * buttons, and a footer that lets the member unsubscribe (also as List-Unsubscribe headers).
 */
class ThreadSubscriptionMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_thread_update_mail_has_the_old_wording_buttons_and_unsubscribe_footer(): void
    {
        [$thread, $post, $recipient] = $this->threadWithReply();

        $mail = (new ThreadUpdatedNotification($thread, $post))->toMail($recipient);
        $html = (string) $mail->render();

        $this->assertSame('Thread updated: A thread', $mail->subject);
        $this->assertStringContainsString('posted a new reply in a thread you subscribed to.', $html);
        $this->assertStringContainsString('View Reply', $html);
        $this->assertStringContainsString('Manage Subscriptions', $html);
        $this->assertStringContainsString('You are receiving this because you subscribed to this thread.', $html);
        $this->assertStringContainsString('Unsubscribe from instant thread update emails:', $html);
        $this->assertStringContainsString(route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$post->getKey(), $html);
        $this->assertStringContainsString('email-preferences/unsubscribe/'.$recipient->getKey().'/forum.thread_updates_instant', $html);

        $symfony = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($symfony);
        }
        $this->assertTrue($symfony->getHeaders()->has('List-Unsubscribe'));
        $this->assertTrue($symfony->getHeaders()->has('List-Unsubscribe-Post'));
    }

    public function test_a_digest_mail_lists_the_threads_and_carries_an_unsubscribe_footer(): void
    {
        [$thread, $post, $recipient] = $this->threadWithReply();
        $item = new ThreadSubscriptionDigestItem(['thread_id' => $thread->getKey(), 'post_id' => $post->getKey()]);
        $item->setRelation('thread', $thread)->setRelation('post', $post);

        $mail = (new ThreadDigestNotification(DeliveryMode::WEEKLY, [$item]))->toMail($recipient);
        $html = (string) $mail->render();

        $this->assertSame('Weekly thread subscription digest', $mail->subject);
        $this->assertStringContainsString('Here are new replies from threads you subscribe to.', $html);
        $this->assertStringContainsString('A thread', $html);
        $this->assertStringContainsString('Unsubscribe from thread digest emails:', $html);
        $this->assertStringContainsString('email-preferences/unsubscribe/'.$recipient->getKey().'/forum.thread_updates_digest', $html);
    }

    public function test_the_forums_events_send_the_sites_mail_and_not_the_packages(): void
    {
        Notification::fake();
        [$thread, $post, $recipient] = $this->threadWithReply();

        event(new SubscribersToNotify($post, $thread, [$recipient]));
        event(new ThreadDigestReady($recipient, DeliveryMode::DAILY, []));

        Notification::assertSentTo($recipient, ThreadUpdatedNotification::class);
        Notification::assertSentTo($recipient, ThreadDigestNotification::class);
        Notification::assertNotSentTo($recipient, PackageThreadUpdatedNotification::class);
        Notification::assertNotSentTo($recipient, PackageThreadDigestNotification::class);
    }

    /**
     * @return array{0: Thread, 1: Post, 2: User}
     */
    private function threadWithReply(): array
    {
        $author = User::factory()->create(['username' => 'reply_author']);
        $recipient = User::factory()->create();
        $group = ForumGroup::query()->create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'A thread', 'slug' => 'a-thread']);
        $post = Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'A reply']);

        return [$thread, $post, $recipient];
    }
}
