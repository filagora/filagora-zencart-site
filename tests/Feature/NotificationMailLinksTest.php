<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Notifications\ThreadUpdatedNotification;
use Filagora\Forums\Notifications\UserMentionedNotification;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\Messages\Models\PrivateMessage;
use Filagora\Messages\Notifications\MessageReceivedNotification;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The packages add the "view" button to their mails only when the site names the route to link to;
 * without it the mail reads fine but has nowhere to go.
 */
class NotificationMailLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_thread_update_mail_links_to_the_thread(): void
    {
        [$thread, $post, $recipient] = $this->threadWithReply();

        $mail = (new ThreadUpdatedNotification($thread, $post))->toMail($recipient);

        $this->assertSame(route('threads.show', $thread), $mail->actionUrl);
    }

    public function test_a_mention_mail_links_to_the_post_in_the_thread(): void
    {
        [$thread, $post, $recipient] = $this->threadWithReply();

        $mail = (new UserMentionedNotification($post))->toMail($recipient);

        $this->assertSame(route('threads.show', $thread).'#post-'.$post->getKey(), $mail->actionUrl);
    }

    public function test_a_new_message_mail_links_to_the_conversation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = PrivateConversation::query()->forceCreate(['created_by_id' => $sender->id, 'subject' => 'Hello']);
        $message = PrivateMessage::query()->forceCreate(['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'body' => 'Hi']);

        $mail = (new MessageReceivedNotification($conversation, $message))->toMail($recipient);

        $this->assertSame(route('messages.show', $conversation), $mail->actionUrl);
    }

    /**
     * @return array{0: Thread, 1: Post, 2: User}
     */
    private function threadWithReply(): array
    {
        $author = User::factory()->create();
        $recipient = User::factory()->create();
        $group = ForumGroup::query()->create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'A thread', 'slug' => 'a-thread']);
        $post = Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'A reply']);

        return [$thread, $post, $recipient];
    }
}
