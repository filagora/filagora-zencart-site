<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Messages\Models\IgnoredUser;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\Messages\Models\PrivateConversationParticipant;
use Filagora\Messages\Models\PrivateMessage;
use Filagora\Messages\Models\PrivateMessageFolder;
use Filagora\Messages\Notifications\MessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PrivateMessagingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_a_new_private_message_lands_in_the_recipients_bell_and_not_the_senders(): void
    {
        $sender = User::factory()->create(['username' => 'pm_sender2']);
        $recipient = User::factory()->create(['username' => 'pm_recipient2']);

        $this->actingAs($sender)->post(route('messages.store'), [
            'subject' => 'Ping',
            'to' => 'pm_recipient2',
            'body' => 'You have a message',
        ])->assertRedirect(route('messages.show', PrivateConversation::query()->firstOrFail()));

        $this->assertDatabaseCount('private_messages', 1);

        $row = $recipient->notifications()->firstOrFail();
        $this->assertSame('New message from pm_sender2', $row->data['title']);
        $this->assertSame(route('messages.show', PrivateConversation::query()->firstOrFail()), $row->data['url']);
        $this->assertSame(0, $sender->notifications()->count());
    }

    public function test_a_new_private_message_is_not_mailed_as_on_the_old_site(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['username' => 'pm_sender3']);
        $recipient = User::factory()->create(['username' => 'pm_recipient3']);

        $this->actingAs($sender)->post(route('messages.store'), ['subject' => 'Ping', 'to' => 'pm_recipient3', 'body' => 'You have a message'])->assertRedirect();

        Notification::assertNotSentTo($recipient, MessageReceivedNotification::class);
        Notification::assertNotSentTo($sender, MessageReceivedNotification::class);
    }

    public function test_header_avatar_shows_a_badge_for_unread_private_messages(): void
    {
        $sender = User::factory()->create(['username' => 'badge_sender', 'email_verified_at' => now()]);
        $recipient = User::factory()->create(['username' => 'badge_recipient', 'email_verified_at' => now()]);

        // Nothing unread yet — plain avatar, no badge.
        $this->actingAs($recipient)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('aria-label="Open user menu"', false)
            ->assertDontSee('unread messages', false);

        $this->actingAs($sender)->post(route('messages.store'), [
            'subject' => 'Ping',
            'to' => 'badge_recipient',
            'body' => 'A private message body',
        ])->assertRedirect();

        // Recipient now sees the count badge on the header avatar.
        $recipientView = $this->actingAs($recipient)->get(route('messages.index'));
        $recipientView->assertOk();
        $recipientView->assertSee('aria-label="Open user menu, 1 unread messages"', false);
        $recipientView->assertSee('rounded-full border-2 border-[color:var(--color-site-panel)] bg-[#933c1f]', false);

        // The sender's own avatar stays clean — their Inbox copy is read.
        $this->actingAs($sender)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('aria-label="Open user menu"', false)
            ->assertDontSee('unread messages', false);
    }

    public function test_authenticated_user_can_send_private_message_and_create_participants(): void
    {
        $sender = User::factory()->create(['username' => 'sender_user']);
        $recipient = User::factory()->create(['username' => 'recipient_user']);
        $secondRecipient = User::factory()->create(['username' => 'second_user']);

        $response = $this->actingAs($sender)->post(route('messages.store'), [
            'subject' => 'Hello',
            'to' => 'recipient_user, second_user',
            'body' => 'First private message',
        ]);

        $conversation = PrivateConversation::query()->first();
        $this->assertNotNull($conversation);
        $response->assertRedirect(route('messages.show', $conversation));
        $response->assertSessionHas('message_status', __('private_messages.flash.message_sent'));

        $this->assertDatabaseHas('private_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'First private message',
            'content_format' => 'markdown',
        ]);

        $this->assertDatabaseHas('private_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'folder_id' => PrivateMessageFolder::query()
                ->where('user_id', $sender->id)
                ->where('slug', 'inbox')
                ->value('id'),
        ]);
        $this->assertDatabaseHas('private_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $recipient->id,
            'folder_id' => PrivateMessageFolder::query()
                ->where('user_id', $recipient->id)
                ->where('slug', 'inbox')
                ->value('id'),
        ]);
        $this->assertDatabaseHas('private_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $secondRecipient->id,
            'folder_id' => PrivateMessageFolder::query()
                ->where('user_id', $secondRecipient->id)
                ->where('slug', 'inbox')
                ->value('id'),
        ]);
    }

    public function test_replies_stay_in_each_participants_current_folder(): void
    {
        [$conversation, $sender, $recipient] = $this->seedConversation();

        $this->actingAs($recipient)->post(route('messages.reply', $conversation), [
            'body' => 'Reply body',
        ])->assertRedirect(route('messages.show', $conversation))
            ->assertSessionHas('message_status', __('private_messages.flash.reply_sent'));

        $this->assertDatabaseHas('private_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $recipient->id,
            'body' => 'Reply body',
            'content_format' => 'markdown',
        ]);

        $senderParticipant = PrivateConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $sender->id)
            ->first();

        $senderParticipant->loadMissing('folder');
        $this->assertSame('inbox', $senderParticipant?->folder?->slug);
        $this->assertFalse((bool) $senderParticipant?->is_read);

        $recipientParticipant = PrivateConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $recipient->id)
            ->first();
        $recipientParticipant?->loadMissing('folder');
        $this->assertSame('inbox', $recipientParticipant?->folder?->slug);
        $this->assertTrue((bool) $recipientParticipant?->is_read);
    }

    public function test_reply_does_not_move_a_conversation_out_of_a_custom_folder(): void
    {
        [$conversation, $sender, $recipient] = $this->seedConversation();
        $customFolder = PrivateMessageFolder::query()->create([
            'user_id' => $sender->id,
            'name' => 'Project Alpha',
            'slug' => 'project-alpha',
            'is_system' => false,
        ]);
        PrivateConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $sender->id)
            ->update(['folder_id' => $customFolder->id]);

        $this->actingAs($recipient)->post(route('messages.reply', $conversation), [
            'body' => 'Follow-up reply',
        ])->assertRedirect(route('messages.show', $conversation));

        $this->assertDatabaseHas('private_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'folder_id' => $customFolder->id,
            'is_read' => false,
        ]);
    }

    public function test_user_can_move_conversation_to_archive_folder(): void
    {
        [$conversation, $sender] = $this->seedConversation();

        $archiveFolderId = PrivateMessageFolder::query()
            ->where('user_id', $sender->id)
            ->where('slug', 'archive')
            ->value('id');

        $this->actingAs($sender)->post(route('messages.move', $conversation), [
            'folder_id' => $archiveFolderId,
        ])->assertRedirect(route('messages.index', ['folder' => 'archive']))
            ->assertSessionHas('message_status', __('private_messages.flash.conversation_moved'));

        $this->assertDatabaseHas('private_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'folder_id' => $archiveFolderId,
        ]);
    }

    public function test_user_can_create_and_delete_custom_folder(): void
    {
        $user = User::factory()->create(['username' => 'folder_user']);
        $this->actingAs($user)->get(route('messages.index'))->assertOk();

        $this->actingAs($user)->post(route('messages.folders.store'), [
            'name' => 'Project Alpha',
        ])->assertRedirect(route('messages.index', ['folder' => 'project-alpha']))
            ->assertSessionHas('message_status', __('private_messages.flash.folder_created'));

        $folder = PrivateMessageFolder::query()
            ->where('user_id', $user->id)
            ->where('slug', 'project-alpha')
            ->first();
        $this->assertNotNull($folder);

        $this->actingAs($user)->post(route('messages.folders.delete', $folder))
            ->assertRedirect(route('messages.index', ['folder' => 'inbox']))
            ->assertSessionHas('message_status', __('private_messages.flash.folder_deleted'));

        $this->assertDatabaseMissing('private_message_folders', [
            'id' => $folder->id,
        ]);
    }

    public function test_sent_is_not_provisioned_or_shown_as_a_system_folder(): void
    {
        $user = User::factory()->create(['username' => 'folder_defaults_user']);

        $response = $this->actingAs($user)->get(route('messages.index'));

        $response->assertOk()->assertDontSee('>Sent</a>', false);
        $this->assertDatabaseMissing('private_message_folders', [
            'user_id' => $user->id,
            'slug' => 'sent',
        ]);
    }

    public function test_reply_pulls_a_trashed_conversation_out_of_trash_for_every_participant(): void
    {
        [$conversation, $sender, $recipient] = $this->seedConversation();

        foreach ([$sender, $recipient] as $user) {
            $this->actingAs($user)->post(route('messages.bulk'), [
                'action' => 'trash',
                'conversation_ids' => [$conversation->id],
            ]);
        }

        $this->actingAs($recipient)->post(route('messages.reply', $conversation), [
            'body' => 'Back from the dead',
        ])->assertRedirect(route('messages.show', $conversation));

        foreach ([$sender, $recipient] as $user) {
            $participant = PrivateConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->assertNull($participant->trashed_at);
            $this->assertNull($participant->trashed_from_folder_id);
            $this->assertSame('inbox', $participant->folder->slug);
        }
    }

    public function test_messages_index_uses_flat_listing_shell(): void
    {
        $user = User::factory()->create(['username' => 'viewer_user']);
        $this->seedSystemFolders($user);

        $response = $this->actingAs($user)->get(route('messages.index'));

        $response->assertOk();
        $response->assertSee('<main id="main-content" tabindex="-1" class="bg-white">', false);
        $response->assertSee('class="mt-[18px] grid gap-3"', false);
        $response->assertSee('class="ui-page-title"', false);
        $response->assertSee('Manage conversations, organize folders, and keep important replies easy to find.');
        $response->assertDontSee('rounded-[14px] border border-[#cabca8] bg-[#ececed] p-4', false);
        $response->assertDontSee('>Current<', false);
    }

    public function test_compose_and_show_pages_use_shared_typography_shell(): void
    {
        [$conversation, $sender] = $this->seedConversation();

        $this->actingAs($sender)
            ->get(route('messages.compose'))
            ->assertOk()
            ->assertSee('class="ui-page-title"', false)
            ->assertSee('Start a new conversation with one or more members.');

        $this->actingAs($sender)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('class="ui-page-title"', false)
            ->assertSee('Review the conversation and reply from the same thread.');
    }

    public function test_guest_is_redirected_to_login_modal_for_messages(): void
    {
        $this->get(route('messages.index'))
            ->assertRedirect('/?auth=login&intended=%2Fmessages');
    }

    public function test_unverified_user_is_redirected_to_verify_flow_for_messages_pages(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('messages.index'))
            ->assertRedirect(route('verification.notice', ['intended' => '/messages']));

        $this->actingAs($user)
            ->get(route('messages.compose'))
            ->assertRedirect(route('verification.notice', ['intended' => '/messages/compose']));
    }

    public function test_message_user_search_returns_matches_for_authenticated_user(): void
    {
        $user = User::factory()->create(['username' => 'searcher']);
        User::factory()->create(['username' => 'johnsmith', 'name' => 'John Smith', 'email' => 'john@example.com']);
        User::factory()->create(['username' => 'johndoe', 'name' => 'John Doe', 'email' => 'doe@example.com']);

        $response = $this->actingAs($user)->get(route('messages.users.search', ['q' => 'john']));

        $response->assertOk();
        $response->assertJsonPath('data.0.username', 'johndoe');
        $response->assertJsonPath('data.1.username', 'johnsmith');
        $response->assertJsonMissingPath('data.0.email');
        $response->assertJsonMissingPath('data.1.email');
    }

    public function test_message_user_search_excludes_authenticated_user_and_requires_auth(): void
    {
        $self = User::factory()->create(['username' => 'johnself', 'name' => 'John Self', 'email' => 'self@example.com']);

        $guestResponse = $this->get(route('messages.users.search', ['q' => 'john']));
        $guestResponse->assertStatus(401);

        $authResponse = $this->actingAs($self)->get(route('messages.users.search', ['q' => 'john']));
        $authResponse->assertOk();

        $usernames = collect($authResponse->json('data'))->pluck('username')->all();
        $this->assertNotContains('johnself', $usernames);
    }

    public function test_message_user_search_does_not_match_by_email_fragment(): void
    {
        $searcher = User::factory()->create(['username' => 'searcher']);
        User::factory()->create([
            'username' => 'recipient_alpha',
            'name' => 'Recipient Alpha',
            'email' => 'alpha-hidden@example.test',
        ]);

        $response = $this->actingAs($searcher)->get(route('messages.users.search', ['q' => 'alpha-hidden']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_message_user_search_excludes_users_with_ignore_relationships(): void
    {
        $searcher = User::factory()->create(['username' => 'searcher']);
        $visible = User::factory()->create(['username' => 'visible_john', 'name' => 'Visible John']);
        $ignored = User::factory()->create(['username' => 'ignored_john', 'name' => 'Ignored John']);
        $ignoring = User::factory()->create(['username' => 'ignoring_john', 'name' => 'Ignoring John']);

        IgnoredUser::query()->create([
            'user_id' => $searcher->id,
            'ignored_user_id' => $ignored->id,
        ]);
        IgnoredUser::query()->create([
            'user_id' => $ignoring->id,
            'ignored_user_id' => $searcher->id,
        ]);

        $response = $this->actingAs($searcher)->get(route('messages.users.search', ['q' => 'john']));

        $response->assertOk();
        $usernames = collect($response->json('data'))->pluck('username')->all();
        $this->assertContains($visible->username, $usernames);
        $this->assertNotContains($ignored->username, $usernames);
        $this->assertNotContains($ignoring->username, $usernames);
    }

    public function test_message_user_search_requires_minimum_query_length(): void
    {
        $searcher = User::factory()->create(['username' => 'searcher']);
        User::factory()->create(['username' => 'ab_match', 'name' => 'AB Match']);

        $shortResponse = $this->actingAs($searcher)->get(route('messages.users.search', ['q' => 'a']));
        $shortResponse->assertOk();
        $shortResponse->assertJsonCount(0, 'data');

        $validResponse = $this->actingAs($searcher)->get(route('messages.users.search', ['q' => 'ab']));
        $validResponse->assertOk();
        $validResponse->assertJsonPath('data.0.username', 'ab_match');
    }

    public function test_message_user_search_falls_back_to_sql_when_scout_unavailable(): void
    {
        $searcher = User::factory()->create(['username' => 'searcher']);
        User::factory()->create(['username' => 'fallback_john', 'name' => 'Fallback John']);

        config()->set('scout.driver', 'meilisearch');
        config()->set('scout.meilisearch.host', 'http://127.0.0.1:9');
        config()->set('scout.meilisearch.key', null);

        $response = $this->actingAs($searcher)->get(route('messages.users.search', ['q' => 'john']));

        $response->assertOk();
        $response->assertJsonPath('data.0.username', 'fallback_john');
    }

    public function test_message_user_search_is_rate_limited(): void
    {
        $searcher = User::factory()->create(['username' => 'searcher']);
        User::factory()->create(['username' => 'johnsmith', 'name' => 'John Smith']);

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($searcher)
                ->get(route('messages.users.search', ['q' => 'john']))
                ->assertOk();
        }

        $this->actingAs($searcher)
            ->get(route('messages.users.search', ['q' => 'john']))
            ->assertStatus(429);
    }

    public function test_messages_compose_script_renders_recipient_lookup_with_dom_text_nodes(): void
    {
        $script = file_get_contents(resource_path('js/modules/messages-compose.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('lookup.replaceChildren(...rows.map((row) => buildRecipientOption(row)));', $script);
        $this->assertStringContainsString('strong.textContent = username;', $script);
        $this->assertStringContainsString("small.textContent = name !== '' ? name : username;", $script);
        $this->assertStringNotContainsString('lookup.innerHTML = rows.map((row) => {', $script);
    }

    public function test_non_participant_cannot_view_or_reply_conversation(): void
    {
        [$conversation] = $this->seedConversation();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('messages.reply', $conversation), [
                'body' => 'Unauthorized reply',
            ])
            ->assertForbidden();
    }

    public function test_conversation_renders_markdown_messages_safely(): void
    {
        [$conversation, $sender] = $this->seedConversation();
        $conversation->messages()->firstOrFail()->update([
            'body' => '**Markdown message** <img src=x onerror=alert(1)>',
            'content_format' => 'markdown',
        ]);
        PrivateMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => '**Second message**',
            'content_format' => 'markdown',
        ]);

        $response = $this->actingAs($sender)->get(route('messages.show', $conversation));

        $response->assertOk();
        $response->assertSee('<strong>Markdown message</strong>', false);
        $response->assertSee('<strong>Second message</strong>', false);
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_conversation_message_body_gets_the_paragraph_spacing_class(): void
    {
        [$conversation, $sender] = $this->seedConversation();
        $conversation->messages()->firstOrFail()->update([
            'body' => "First paragraph.\n\nSecond paragraph.",
        ]);

        $response = $this->actingAs($sender)->get(route('messages.show', $conversation));

        $response->assertOk();
        // Regression test for the paragraph breaks disappearing from posted
        // content: without the "forum-rendered-content" class, Tailwind
        // Preflight zeroes <p> margins and consecutive paragraphs render
        // flush against each other with no visible gap.
        $response->assertSee('class="forum-rendered-content', false);
    }

    public function test_message_store_rejects_recipient_who_disabled_private_messages(): void
    {
        $sender = User::factory()->create(['username' => 'sender_user']);
        User::factory()->create([
            'username' => 'recipient_user',
            'allow_private_messages' => false,
        ]);

        $response = $this->actingAs($sender)
            ->from(route('messages.compose'))
            ->post(route('messages.store'), [
                'subject' => 'Hello',
                'to' => 'recipient_user',
                'body' => 'Body text',
            ]);

        $response->assertRedirect(route('messages.compose'));
        $response->assertSessionHasErrors(['to']);
        $this->assertSame(
            __('private_messages.errors.blocked_recipients', ['users' => 'recipient_user']),
            session('errors')->get('to')[0]
        );
        $this->assertDatabaseCount('private_conversations', 0);
    }

    public function test_message_store_rejects_recipient_when_ignore_relationship_exists(): void
    {
        $sender = User::factory()->create(['username' => 'sender_user']);
        $recipient = User::factory()->create(['username' => 'recipient_user']);

        IgnoredUser::query()->create([
            'user_id' => $recipient->id,
            'ignored_user_id' => $sender->id,
        ]);

        $response = $this->actingAs($sender)
            ->from(route('messages.compose'))
            ->post(route('messages.store'), [
                'subject' => 'Hello',
                'to' => 'recipient_user',
                'body' => 'Body text',
            ]);

        $response->assertRedirect(route('messages.compose'));
        $response->assertSessionHasErrors(['to']);
        $this->assertSame(
            __('private_messages.errors.blocked_recipients', ['users' => 'recipient_user']),
            session('errors')->get('to')[0]
        );
        $this->assertDatabaseCount('private_conversations', 0);
    }

    public function test_move_with_invalid_folder_returns_422(): void
    {
        [$conversation, $sender] = $this->seedConversation();

        $this->actingAs($sender)
            ->post(route('messages.move', $conversation), [
                'folder_id' => 999999,
            ])
            ->assertStatus(422);
    }

    public function test_deleting_system_folder_returns_422(): void
    {
        $user = User::factory()->create();
        $this->seedSystemFolders($user);
        $inbox = PrivateMessageFolder::query()
            ->where('user_id', $user->id)
            ->where('slug', 'inbox')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('messages.folders.delete', $inbox))
            ->assertStatus(422);
    }

    public function test_store_requires_at_least_one_recipient(): void
    {
        $sender = User::factory()->create(['username' => 'sender_user']);

        $response = $this->actingAs($sender)
            ->from(route('messages.compose'))
            ->post(route('messages.store'), [
                'subject' => 'Missing recipients',
                'to' => '',
                'body' => 'Body text',
            ]);

        $response->assertRedirect(route('messages.compose'));
        $response->assertSessionHasErrors(['to']);
    }

    public function test_bulk_move_relocates_multiple_conversations(): void
    {
        [$user, $conversations] = $this->seedInboxConversations(3);
        $archiveId = $this->folderId($user, 'archive');

        $this->actingAs($user)
            ->post(route('messages.bulk'), [
                'action' => 'move',
                'folder_id' => $archiveId,
                'conversation_ids' => $conversations->pluck('id')->all(),
                'return_folder' => 'inbox',
            ])
            ->assertRedirect(route('messages.index', ['folder' => 'inbox']))
            ->assertSessionHas('message_status', trans_choice('private_messages.flash.conversations_moved', 3, ['count' => 3]));

        foreach ($conversations as $conversation) {
            $this->assertDatabaseHas('private_conversation_participants', [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'folder_id' => $archiveId,
            ]);
        }
    }

    public function test_bulk_trash_moves_conversations_to_trash_folder(): void
    {
        [$user, $conversations] = $this->seedInboxConversations(2);
        $inboxId = $this->folderId($user, 'inbox');
        $trashId = $this->folderId($user, 'trash');

        $this->actingAs($user)
            ->post(route('messages.bulk'), [
                'action' => 'trash',
                'conversation_ids' => $conversations->pluck('id')->all(),
                'return_folder' => 'inbox',
            ])
            ->assertRedirect(route('messages.index', ['folder' => 'inbox']));

        foreach ($conversations as $conversation) {
            $participant = PrivateConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->assertSame($trashId, (int) $participant->folder_id);
            $this->assertSame($inboxId, (int) $participant->trashed_from_folder_id);
            $this->assertNotNull($participant->trashed_at);
        }

        $this->actingAs($user)
            ->get(route('messages.index', ['folder' => 'inbox']))
            ->assertOk()
            ->assertDontSee($conversations->first()->subject);
    }

    public function test_inbox_orders_conversations_by_latest_message_not_participant_updated_at(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->seedSystemFolders($user);
        $inboxId = (int) PrivateMessageFolder::query()->where('user_id', $user->id)->where('slug', 'inbox')->value('id');

        $make = function (string $subject, $messageAt, $participantUpdatedAt) use ($user, $other, $inboxId): void {
            $conversation = PrivateConversation::query()->create(['created_by_id' => $other->id, 'subject' => $subject]);
            PrivateMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $other->id,
                'body' => 'Body',
                'created_at' => $messageAt,
                'updated_at' => $messageAt,
            ]);
            PrivateConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'folder_id' => $inboxId,
                'is_read' => true,
                'created_at' => $participantUpdatedAt,
                'updated_at' => $participantUpdatedAt,
            ]);
        };

        $make('Older message, touched recently', now()->subDays(10), now());
        $make('Newer message, untouched', now()->subDay(), now()->subDays(20));

        $this->actingAs($user)
            ->get(route('messages.index'))
            ->assertSeeInOrder(['Newer message, untouched', 'Older message, touched recently']);
    }

    public function test_inbox_paginates_conversations_twenty_per_page(): void
    {
        [$user] = $this->seedInboxConversations(25);

        $page1 = $this->actingAs($user)->get(route('messages.index'));
        $page1->assertOk();
        $paginator = $page1->viewData('participants');
        $this->assertSame(20, $paginator->perPage());
        $this->assertSame(25, $paginator->total());
        $this->assertCount(20, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());
        $page1->assertSee('page=2', false);

        $page2 = $this->actingAs($user)->get(route('messages.index', ['page' => 2]));
        $page2->assertOk();
        $this->assertCount(5, $page2->viewData('participants')->items());
    }

    public function test_trash_view_states_how_long_conversations_are_kept(): void
    {
        config(['filagora-messages.trash.retention_days' => 21]);
        [$user] = $this->seedInboxConversations(1);

        $this->actingAs($user)
            ->get(route('messages.index', ['folder' => 'trash']))
            ->assertOk()
            ->assertSee('Conversations in Trash are permanently deleted after 21 days.');
    }

    public function test_restore_returns_conversation_to_its_origin_folder(): void
    {
        [$user, $conversations] = $this->seedInboxConversations(1);
        $conversation = $conversations->first();
        $archiveId = $this->folderId($user, 'archive');

        $this->actingAs($user)->post(route('messages.bulk'), [
            'action' => 'move',
            'folder_id' => $archiveId,
            'conversation_ids' => [$conversation->id],
        ]);
        $this->actingAs($user)->post(route('messages.bulk'), [
            'action' => 'trash',
            'conversation_ids' => [$conversation->id],
        ]);

        $this->actingAs($user)
            ->post(route('messages.bulk'), [
                'action' => 'restore',
                'conversation_ids' => [$conversation->id],
                'return_folder' => 'trash',
            ])
            ->assertRedirect(route('messages.index', ['folder' => 'trash']))
            ->assertSessionHas('message_status', trans_choice('private_messages.flash.conversations_restored', 1, ['count' => 1]));

        $participant = PrivateConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame($archiveId, (int) $participant->folder_id);
        $this->assertNull($participant->trashed_at);
        $this->assertNull($participant->trashed_from_folder_id);
    }

    public function test_delete_permanently_removes_conversation_from_every_folder(): void
    {
        [$user, $conversations] = $this->seedInboxConversations(1);
        $conversation = $conversations->first();

        $this->actingAs($user)->post(route('messages.bulk'), [
            'action' => 'trash',
            'conversation_ids' => [$conversation->id],
        ]);

        $this->actingAs($user)
            ->post(route('messages.bulk'), [
                'action' => 'delete',
                'conversation_ids' => [$conversation->id],
                'return_folder' => 'trash',
            ])
            ->assertRedirect(route('messages.index', ['folder' => 'trash']));

        $participant = PrivateConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->assertNotNull($participant->deleted_at);

        foreach (['inbox', 'archive', 'trash'] as $slug) {
            $this->actingAs($user)
                ->get(route('messages.index', ['folder' => $slug]))
                ->assertOk()
                ->assertDontSee($conversation->subject);
        }

        $this->actingAs($user)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();
    }

    public function test_empty_trash_deletes_all_trashed_conversations(): void
    {
        [$user, $conversations] = $this->seedInboxConversations(3);

        $this->actingAs($user)->post(route('messages.bulk'), [
            'action' => 'trash',
            'conversation_ids' => $conversations->pluck('id')->all(),
        ]);

        $this->actingAs($user)
            ->post(route('messages.trash.empty'))
            ->assertRedirect(route('messages.index', ['folder' => 'trash']))
            ->assertSessionHas('message_status', trans_choice('private_messages.flash.trash_emptied', 3, ['count' => 3]));

        $live = PrivateConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->count();
        $this->assertSame(0, $live);
    }

    public function test_bulk_mark_read_and_unread_toggles_participant_state(): void
    {
        [$user, $conversations] = $this->seedInboxConversations(2);
        $ids = $conversations->pluck('id')->all();

        $this->actingAs($user)->post(route('messages.bulk'), [
            'action' => 'mark_read',
            'conversation_ids' => $ids,
        ]);
        $this->assertSame(0, PrivateConversationParticipant::query()
            ->where('user_id', $user->id)->where('is_read', false)->count());

        $this->actingAs($user)->post(route('messages.bulk'), [
            'action' => 'mark_unread',
            'conversation_ids' => $ids,
        ]);
        $this->assertSame(2, PrivateConversationParticipant::query()
            ->where('user_id', $user->id)->where('is_read', false)->count());
    }

    public function test_bulk_action_cannot_touch_conversations_the_user_is_not_part_of(): void
    {
        [$user] = $this->seedInboxConversations(1);
        [$conversation, $otherOwner] = $this->seedConversation();

        $otherParticipant = PrivateConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $otherOwner->id)
            ->firstOrFail();
        $originalFolderId = (int) $otherParticipant->folder_id;

        $this->actingAs($user)
            ->post(route('messages.bulk'), [
                'action' => 'trash',
                'conversation_ids' => [$conversation->id],
            ])
            ->assertRedirect(route('messages.index', ['folder' => 'inbox']))
            ->assertSessionHas('message_status', trans_choice('private_messages.flash.conversations_trashed', 0, ['count' => 0]));

        $otherParticipant->refresh();
        $this->assertSame($originalFolderId, (int) $otherParticipant->folder_id);
        $this->assertNull($otherParticipant->trashed_at);
    }

    public function test_bulk_action_requires_at_least_one_conversation(): void
    {
        [$user] = $this->seedInboxConversations(1);

        $this->actingAs($user)
            ->from(route('messages.index'))
            ->post(route('messages.bulk'), [
                'action' => 'trash',
                'conversation_ids' => [],
            ])
            ->assertRedirect(route('messages.index'))
            ->assertSessionHasErrors('conversation_ids');
    }

    /**
     * @return array{0:User,1:Collection<int,PrivateConversation>}
     */
    private function seedInboxConversations(int $count): array
    {
        $user = User::factory()->create();
        $this->seedSystemFolders($user);
        $inboxId = $this->folderId($user, 'inbox');

        $conversations = collect(range(1, $count))->map(function (int $index) use ($user, $inboxId): PrivateConversation {
            $conversation = PrivateConversation::query()->create([
                'created_by_id' => $user->id,
                'subject' => "Inbox conversation {$index} ".Str::random(6),
            ]);

            PrivateMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => "Body {$index}",
                'content_format' => 'markdown',
            ]);

            PrivateConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'folder_id' => $inboxId,
                'is_read' => false,
            ]);

            return $conversation;
        });

        return [$user, $conversations];
    }

    private function folderId(User $user, string $slug): int
    {
        return (int) PrivateMessageFolder::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->value('id');
    }

    /**
     * @return array{0:PrivateConversation,1:User,2:User}
     */
    private function seedConversation(): array
    {
        $sender = User::factory()->create(['username' => 'sender_user']);
        $recipient = User::factory()->create(['username' => 'recipient_user']);

        $conversation = PrivateConversation::query()->create([
            'created_by_id' => $sender->id,
            'subject' => 'Seeded',
        ]);

        PrivateMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Initial body',
        ]);

        $this->seedSystemFolders($sender);
        $this->seedSystemFolders($recipient);

        $senderInboxFolderId = PrivateMessageFolder::query()
            ->where('user_id', $sender->id)
            ->where('slug', 'inbox')
            ->value('id');
        $recipientInboxFolderId = PrivateMessageFolder::query()
            ->where('user_id', $recipient->id)
            ->where('slug', 'inbox')
            ->value('id');

        PrivateConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'folder_id' => $senderInboxFolderId,
            'is_read' => true,
            'last_read_at' => now(),
        ]);

        PrivateConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $recipient->id,
            'folder_id' => $recipientInboxFolderId,
            'is_read' => false,
        ]);

        return [$conversation, $sender, $recipient];
    }

    private function seedSystemFolders(User $user): void
    {
        foreach ([
            ['slug' => 'inbox', 'name' => 'Inbox'],
            ['slug' => 'archive', 'name' => 'Archive'],
            ['slug' => 'trash', 'name' => 'Trash'],
        ] as $folder) {
            PrivateMessageFolder::query()->firstOrCreate(
                ['user_id' => $user->id, 'slug' => $folder['slug']],
                ['name' => $folder['name'], 'is_system' => true]
            );
        }
    }
}
