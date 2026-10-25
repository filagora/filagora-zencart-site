<?php

namespace Tests\Feature;

use App\Models\PrivateConversation;
use App\Models\PrivateConversationParticipant;
use App\Models\PrivateMessage;
use App\Models\PrivateMessageFolder;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\PostAttachment;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class LegacyAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_public_forum_attachment_can_be_downloaded_by_guest(): void
    {
        Storage::fake('local');
        [$attachment] = $this->seedAttachment(ForumVisibility::PUBLIC);

        Storage::disk('local')->put((string) $attachment->storage_path, 'attachment-content');

        $response = $this->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('attachment-content', $response->streamedContent());
    }

    public function test_attachment_url_with_styleid_returns_gone(): void
    {
        Storage::fake('local');
        [$attachment] = $this->seedAttachment(ForumVisibility::PUBLIC);

        Storage::disk('local')->put((string) $attachment->storage_path, 'attachment-content');

        $this->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id.'&styleid=4')
            ->assertGone();
    }

    public function test_inline_displayable_attachment_can_be_rendered_inline_when_requested(): void
    {
        Storage::fake('local');
        [$attachment] = $this->seedAttachment(ForumVisibility::PUBLIC, [
            'original_filename' => 'legacy-image.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'storage_path' => 'attachments/vbulletin/91001-92001.jpg',
        ]);

        Storage::disk('local')->put((string) $attachment->storage_path, 'image-bytes');

        $response = $this->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id.'&disposition=inline');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('image-bytes', $response->streamedContent());
    }

    public function test_non_displayable_attachment_still_downloads_when_inline_is_requested(): void
    {
        Storage::fake('local');
        [$attachment] = $this->seedAttachment(ForumVisibility::PUBLIC);

        Storage::disk('local')->put((string) $attachment->storage_path, 'zip-content');

        $response = $this->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id.'&disposition=inline');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('zip-content', $response->streamedContent());
    }

    public function test_staff_only_forum_attachment_is_forbidden_for_guest(): void
    {
        Storage::fake('local');
        [$attachment] = $this->seedAttachment(ForumVisibility::STAFF_ONLY);
        Storage::disk('local')->put((string) $attachment->storage_path, 'secret');

        $this->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertForbidden();
    }

    public function test_deleted_post_attachment_is_hidden_from_regular_user_but_visible_to_moderator(): void
    {
        Storage::fake('local');
        [$attachment, $post] = $this->seedAttachment(ForumVisibility::PUBLIC);
        Storage::disk('local')->put((string) $attachment->storage_path, 'deleted-post-file');

        $post->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        $regularUser = User::factory()->create();
        $this->actingAs($regularUser)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertNotFound();

        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');
        $response = $this->actingAs($moderator)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);
        $response->assertOk();
        $this->assertSame('deleted-post-file', $response->streamedContent());
    }

    public function test_pending_post_attachment_is_hidden_from_other_users_but_visible_to_post_author(): void
    {
        Storage::fake('local');
        [$attachment, $post] = $this->seedAttachment(ForumVisibility::PUBLIC);
        Storage::disk('local')->put((string) $attachment->storage_path, 'pending-post-file');

        $post->update([
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertNotFound();

        $authorResponse = $this->actingAs($post->author)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);
        $authorResponse->assertOk();
        $this->assertSame('pending-post-file', $authorResponse->streamedContent());
    }

    public function test_pending_thread_attachment_is_hidden_from_other_users_but_visible_to_thread_author(): void
    {
        Storage::fake('local');
        [$attachment, $post] = $this->seedAttachment(ForumVisibility::PUBLIC);
        Storage::disk('local')->put((string) $attachment->storage_path, 'pending-thread-file');

        $post->thread->update([
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertForbidden();

        $authorResponse = $this->actingAs($post->thread->author)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);
        $authorResponse->assertOk();
        $this->assertSame('pending-thread-file', $authorResponse->streamedContent());
    }

    public function test_private_message_attachment_requires_conversation_participation(): void
    {
        $this->markTestSkipped('Needs private messages: slice S6 (members and messages).');

        Storage::fake('local');
        [$attachment, $sender, $recipient] = $this->seedPrivateMessageAttachment();
        Storage::disk('local')->put((string) $attachment->storage_path, 'pm-attachment-content');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertForbidden();

        $senderResponse = $this->actingAs($sender)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);
        $senderResponse->assertOk();
        $this->assertSame('pm-attachment-content', $senderResponse->streamedContent());

        $recipientResponse = $this->actingAs($recipient)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);
        $recipientResponse->assertOk();
        $this->assertSame('pm-attachment-content', $recipientResponse->streamedContent());
    }

    public function test_staff_author_only_attachment_is_forbidden_to_non_owner_non_staff(): void
    {
        Storage::fake('local');

        $group = ForumGroup::query()->create([
            'name' => 'Security',
            'slug' => 'security',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Reports of Security Problems',
            'slug' => 'reports-of-security-problems',
            'visibility' => ForumVisibility::STAFF_AUTHOR_ONLY,
            'is_active' => true,
        ]);

        $owner = User::factory()->create();
        $otherAuthor = User::factory()->create();

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Other Author Thread',
            'slug' => 'other-author-thread',
        ]);

        $ownerThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $owner->id,
            'title' => 'Owner Thread',
            'slug' => 'owner-thread-for-access',
        ]);

        Post::query()->create([
            'thread_id' => $ownerThread->id,
            'author_id' => $owner->id,
            'body' => 'Owner starter',
        ]);

        $thread = Thread::query()->where('author_id', $otherAuthor->id)->firstOrFail();
        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $otherAuthor->id,
            'body' => 'Other author post',
        ]);

        $attachment = PostAttachment::query()->create([
            'legacy_attachment_id' => 91999,
            'legacy_filedata_id' => 92999,
            'legacy_post_id' => 90999,
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'uploader_id' => $otherAuthor->id,
            'original_filename' => 'security.zip',
            'extension' => 'zip',
            'mime_type' => 'application/zip',
            'filesize_bytes' => 12,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/91999-92999.zip',
        ]);

        Storage::disk('local')->put((string) $attachment->storage_path, 'secret-security-file');

        $this->actingAs($owner)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertForbidden();
    }

    public function test_paid_or_author_attachment_index_is_forbidden_to_non_member_non_owner(): void
    {
        Storage::fake('local');

        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Help Wanted',
            'slug' => 'commercial-help-wanted',
            'visibility' => ForumVisibility::PAID_OR_AUTHOR,
            'required_entitlement_scope' => 'forums.commercial_help_wanted.view',
            'is_active' => true,
        ]);

        $owner = User::factory()->create();
        $otherAuthor = User::factory()->create();

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $owner->id,
            'title' => 'Owner Thread',
            'slug' => 'owner-thread-paid-or-author',
        ]);

        $otherThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Other Author Thread',
            'slug' => 'other-author-thread-paid-or-author',
        ]);

        $post = Post::query()->create([
            'legacy_post_id' => 91950,
            'thread_id' => $otherThread->id,
            'author_id' => $otherAuthor->id,
            'body' => 'Other author post with attachment',
        ]);

        PostAttachment::query()->create([
            'legacy_attachment_id' => 91951,
            'legacy_filedata_id' => 92951,
            'legacy_post_id' => 91950,
            'post_id' => $post->id,
            'thread_id' => $otherThread->id,
            'uploader_id' => $otherAuthor->id,
            'original_filename' => 'commercial.zip',
            'extension' => 'zip',
            'mime_type' => 'application/zip',
            'filesize_bytes' => 12,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/91951-92951.zip',
        ]);

        $this->actingAs($owner)
            ->get(route('legacy.attachments.index', $otherThread))
            ->assertForbidden();
    }

    public function test_paid_or_author_attachment_download_is_forbidden_to_non_member_non_owner(): void
    {
        Storage::fake('local');

        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial-download',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Downloads',
            'slug' => 'commercial-downloads',
            'visibility' => ForumVisibility::PAID_OR_AUTHOR,
            'required_entitlement_scope' => 'forums.commercial_downloads.view',
            'is_active' => true,
        ]);

        $owner = User::factory()->create();
        $otherAuthor = User::factory()->create();

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $owner->id,
            'title' => 'Owner Download Thread',
            'slug' => 'owner-download-thread',
        ]);

        $otherThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Other Download Thread',
            'slug' => 'other-download-thread',
        ]);

        $post = Post::query()->create([
            'legacy_post_id' => 91960,
            'thread_id' => $otherThread->id,
            'author_id' => $otherAuthor->id,
            'body' => 'Other author downloadable post',
        ]);

        $attachment = PostAttachment::query()->create([
            'legacy_attachment_id' => 91961,
            'legacy_filedata_id' => 92961,
            'legacy_post_id' => 91960,
            'post_id' => $post->id,
            'thread_id' => $otherThread->id,
            'uploader_id' => $otherAuthor->id,
            'original_filename' => 'commercial-download.zip',
            'extension' => 'zip',
            'mime_type' => 'application/zip',
            'filesize_bytes' => 18,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/91961-92961.zip',
        ]);

        Storage::disk('local')->put((string) $attachment->storage_path, 'restricted-commercial-file');

        $this->actingAs($owner)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id)
            ->assertForbidden();
    }

    public function test_entitlement_only_attachment_can_be_downloaded_by_directly_entitled_user(): void
    {
        Storage::fake('local');
        [$attachment] = $this->seedAttachment(ForumVisibility::ENTITLEMENT_ONLY, [
            'required_entitlement_scope' => 'forums.new_website_feedback.view',
        ]);

        $user = User::factory()->create();

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'forums.new_website_feedback.view',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
            'starts_at' => now()->subMinute(),
        ]);

        Storage::disk('local')->put((string) $attachment->storage_path, 'entitled-content');

        $response = $this->actingAs($user)
            ->get('/attachment.php?attachmentid='.$attachment->legacy_attachment_id);

        $response->assertOk();
        $this->assertSame('entitled-content', $response->streamedContent());
    }

    /**
     * @return array{0:PostAttachment,1:Post}
     */
    private function seedAttachment(ForumVisibility $visibility, array $overrides = []): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'Legacy Group',
            'slug' => 'legacy-group-'.$visibility->value,
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Legacy Forum '.$visibility->value,
            'slug' => 'legacy-forum-'.$visibility->value,
            'visibility' => $visibility,
            'required_entitlement_scope' => $overrides['required_entitlement_scope'] ?? null,
            'is_active' => true,
        ]);

        $author = User::factory()->create();

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Legacy Attachment Thread',
            'slug' => 'legacy-attachment-thread-'.$visibility->value,
        ]);

        $post = Post::query()->create([
            'legacy_post_id' => 90001,
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Has attachment',
        ]);

        $attachment = PostAttachment::query()->create(array_merge([
            'legacy_attachment_id' => 91001,
            'legacy_filedata_id' => 92001,
            'legacy_post_id' => 90001,
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'uploader_id' => $author->id,
            'original_filename' => 'legacy.zip',
            'extension' => 'zip',
            'mime_type' => 'application/zip',
            'filesize_bytes' => 17,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/91001-92001.zip',
        ], $overrides));

        return [$attachment, $post];
    }

    /**
     * @return array{0:PostAttachment,1:User,2:User}
     */
    private function seedPrivateMessageAttachment(): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $conversation = PrivateConversation::query()->create([
            'legacy_root_pmid' => 81230,
            'created_by_id' => $sender->id,
            'subject' => 'PM Attachment Subject',
        ]);

        $message = PrivateMessage::query()->create([
            'legacy_pmtext_id' => 81231,
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'PM with attachment',
        ]);

        $senderSent = PrivateMessageFolder::query()->create([
            'user_id' => $sender->id,
            'name' => 'Sent',
            'slug' => 'sent',
            'is_system' => true,
        ]);
        $recipientInbox = PrivateMessageFolder::query()->create([
            'user_id' => $recipient->id,
            'name' => 'Inbox',
            'slug' => 'inbox',
            'is_system' => true,
        ]);

        PrivateConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'folder_id' => $senderSent->id,
            'is_read' => true,
        ]);
        PrivateConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $recipient->id,
            'folder_id' => $recipientInbox->id,
            'is_read' => false,
        ]);

        $attachment = PostAttachment::query()->create([
            'legacy_attachment_id' => 91234,
            'legacy_filedata_id' => 91235,
            'legacy_pmtext_id' => 81231,
            'private_message_id' => $message->id,
            'private_conversation_id' => $conversation->id,
            'uploader_id' => $sender->id,
            'original_filename' => 'pm-attachment.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'filesize_bytes' => 22,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/vbulletin/91234-91235.jpg',
        ]);

        return [$attachment, $sender, $recipient];
    }
}
