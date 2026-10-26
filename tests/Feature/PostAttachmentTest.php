<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\PostAttachment;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PostAttachmentTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_creating_a_thread_with_an_image_attachment_stores_it_and_renders_inline(): void
    {
        $this->markTestSkipped('Image thumbnails need intervention/image, which the site does not install yet: a dependency to approve.');

        Storage::fake('local');
        [$forum] = $this->createPublicForum();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'Thread with attachment',
            'body' => 'See attached screenshot',
            'attachments' => [UploadedFile::fake()->image('screenshot.png', 100, 80)],
        ]);

        $thread = Thread::query()->where('title', 'Thread with attachment')->firstOrFail();
        $post = Post::query()->where('thread_id', $thread->id)->firstOrFail();

        $response->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $attachment = PostAttachment::query()->where('post_id', $post->id)->firstOrFail();
        $this->assertSame('screenshot.png', $attachment->original_filename);
        $this->assertSame('local', $attachment->storage_disk);
        Storage::disk('local')->assertExists($attachment->storage_path);

        $threadResponse = $this->actingAs($user)->get(route('threads.show', $thread));
        $threadResponse->assertOk();
        $threadResponse->assertSee(route('post.attachments.thumbnail', $attachment), false);
    }

    public function test_inline_pasted_image_is_not_duplicated_in_the_attachments_strip(): void
    {
        Storage::fake('local');
        [$forum] = $this->createPublicForum();
        $user = User::factory()->create();

        $uploadResponse = $this->actingAs($user)->post(route('post.attachments.inline-upload'), [
            'image' => UploadedFile::fake()->image('inline.png'),
        ]);
        $attachment = PostAttachment::query()->findOrFail($uploadResponse->json('attachment_id'));
        $imageUrl = $uploadResponse->json('url');

        $this->actingAs($user)->post(route('threads.create', $forum), [
            'title' => 'Thread with inline image only',
            'body' => "Check this out ![Inline image]({$imageUrl})",
        ]);

        $thread = Thread::query()->where('title', 'Thread with inline image only')->firstOrFail();

        $response = $this->actingAs($user)->get(route('threads.show', $thread));
        $response->assertOk();

        // Rendered inline, at the position the author actually pasted it.
        $response->assertSee(
            route('post.attachments.show', ['attachment' => $attachment, 'disposition' => 'inline']),
            false
        );

        // Not also duplicated in the separate attachments strip below the post.
        $response->assertDontSee(route('post.attachments.thumbnail', $attachment), false);
    }

    public function test_replying_with_an_attachment_stores_it_on_the_new_post(): void
    {
        Storage::fake('local');
        [$forum, $thread] = $this->createPublicForum(withThread: true);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'Here is a log file',
            'attachments' => [UploadedFile::fake()->create('error.txt', 5, 'text/plain')],
        ]);

        $post = Post::query()->where('thread_id', $thread->id)->where('body', 'Here is a log file')->firstOrFail();
        $attachment = PostAttachment::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertSame('error.txt', $attachment->original_filename);
        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_removing_an_attachment_via_edit_deletes_the_row_and_file(): void
    {
        Storage::fake('local');
        [$forum, $thread, $post, $author] = $this->createPublicForum(withThread: true, withPost: true);

        $this->actingAs($author)->post(route('threads.reply', $thread), [
            'body' => 'placeholder body to attach a file to',
        ]);

        $attachment = PostAttachment::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'uploader_id' => $author->id,
            'original_filename' => 'to-remove.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'filesize_bytes' => 100,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/uploads/2026/07/to-remove.png',
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'bytes');

        $response = $this->actingAs($author)->put(route('posts.update', $post), [
            'body' => $post->body,
            'remove_attachments' => [$attachment->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('post_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing('attachments/uploads/2026/07/to-remove.png');
    }

    public function test_adding_an_attachment_via_edit_stores_it(): void
    {
        Storage::fake('local');
        [$forum, $thread, $post, $author] = $this->createPublicForum(withThread: true, withPost: true);

        $response = $this->actingAs($author)->put(route('posts.update', $post), [
            'body' => $post->body,
            'attachments' => [UploadedFile::fake()->image('added.jpg')],
        ]);

        $response->assertRedirect();
        $attachment = PostAttachment::query()->where('post_id', $post->id)->firstOrFail();
        $this->assertSame('added.jpg', $attachment->original_filename);
    }

    public function test_oversized_attachment_is_rejected(): void
    {
        Storage::fake('local');
        [$forum, $thread] = $this->createPublicForum(withThread: true);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'too big',
            'attachments' => [UploadedFile::fake()->create('huge.zip', 10241)],
        ]);

        $response->assertSessionHasErrors('attachments.0', null, 'reply');
        $this->assertDatabaseCount('post_attachments', 0);
    }

    public function test_disallowed_mime_type_is_rejected(): void
    {
        Storage::fake('local');
        [$forum, $thread] = $this->createPublicForum(withThread: true);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'not allowed',
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ]);

        $response->assertSessionHasErrors('attachments.0', null, 'reply');
        $this->assertDatabaseCount('post_attachments', 0);
    }

    public function test_more_than_five_attachments_is_rejected(): void
    {
        Storage::fake('local');
        [$forum, $thread] = $this->createPublicForum(withThread: true);
        $user = User::factory()->create();

        $files = collect(range(1, 6))->map(fn (int $i) => UploadedFile::fake()->image("file-{$i}.png"))->all();

        $response = $this->actingAs($user)->post(route('threads.reply', $thread), [
            'body' => 'too many files',
            'attachments' => $files,
        ]);

        $response->assertSessionHasErrors('attachments', null, 'reply');
        $this->assertDatabaseCount('post_attachments', 0);
    }

    public function test_new_attachment_show_route_is_forbidden_for_guest_on_staff_only_forum(): void
    {
        Storage::fake('local');
        [$forum, $thread, $post, $author] = $this->createPublicForum(
            withThread: true,
            withPost: true,
            forumOverrides: ['visibility' => ForumVisibility::STAFF_ONLY],
        );

        $attachment = PostAttachment::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'uploader_id' => $author->id,
            'original_filename' => 'secret.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'filesize_bytes' => 100,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/uploads/2026/07/secret.png',
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'bytes');

        $this->get(route('post.attachments.show', $attachment))->assertForbidden();
    }

    public function test_new_attachment_show_route_works_for_public_forum(): void
    {
        Storage::fake('local');
        [$forum, $thread, $post, $author] = $this->createPublicForum(withThread: true, withPost: true);

        $attachment = PostAttachment::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'uploader_id' => $author->id,
            'original_filename' => 'public.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'filesize_bytes' => 100,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/uploads/2026/07/public.png',
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'bytes');

        $response = $this->get(route('post.attachments.show', $attachment));

        $response->assertOk();
        $this->assertSame('bytes', $response->streamedContent());
    }

    /**
     * @return array{0: Forum, 1: ?Thread, 2: ?Post, 3: ?User}
     */
    private function createPublicForum(bool $withThread = false, bool $withPost = false, array $forumOverrides = []): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create(array_merge([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ], $forumOverrides));

        if (! $withThread) {
            return [$forum, null, null, null];
        }

        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Test Thread '.Str::random(6),
            'slug' => 'test-thread-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
        ]);

        if (! $withPost) {
            return [$forum, $thread, null, $author];
        }

        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Original post body',
        ]);

        return [$forum, $thread, $post, $author];
    }
}
