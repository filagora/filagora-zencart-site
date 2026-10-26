<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ForumEntitlementPostingPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_user_without_entitlement_cannot_see_the_new_thread_form(): void
    {
        $forum = $this->entitlementGatedForum();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('threads.create.form', $forum))
            ->assertForbidden();
    }

    public function test_user_without_entitlement_cannot_start_a_thread(): void
    {
        $forum = $this->entitlementGatedForum();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('threads.create', $forum), [
                'title' => 'A new thread',
                'body' => 'Opening post body',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('threads', ['forum_id' => $forum->id]);
    }

    public function test_user_with_entitlement_can_start_a_thread(): void
    {
        $forum = $this->entitlementGatedForum();
        $user = User::factory()->create();
        $this->grantEntitlement($user, 'forums.entitlement_gated.post');

        $this->actingAs($user)
            ->get(route('threads.create.form', $forum))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('threads.create', $forum), [
                'title' => 'A new thread',
                'body' => 'Opening post body',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('threads', [
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'A new thread',
        ]);
    }

    public function test_user_with_entitlement_can_reply_to_another_users_thread(): void
    {
        $forum = $this->entitlementGatedForum();
        $author = User::factory()->create();
        $this->grantEntitlement($author, 'forums.entitlement_gated.post');
        $thread = $this->thread($forum, $author);

        $replier = User::factory()->create();
        $this->grantEntitlement($replier, 'forums.entitlement_gated.post');

        $this->actingAs($replier)
            ->post(route('threads.reply', $thread), ['body' => 'A reply'])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'thread_id' => $thread->id,
            'author_id' => $replier->id,
            'body' => 'A reply',
        ]);
    }

    public function test_thread_author_without_entitlement_cannot_reply_to_their_own_thread(): void
    {
        $forum = $this->entitlementGatedForum();
        $author = User::factory()->create();
        $this->grantEntitlement($author, 'forums.entitlement_gated.post');
        $thread = $this->thread($forum, $author);

        // Entitlements can lapse (e.g. membership expiry) after the thread was
        // started. Unlike reply_entitlement_scope's own-thread exemption on
        // other posting policies, ENTITLEMENT_ONLY means the entitlement is
        // required to post at all, with no exemption for the thread's author.
        $author->entitlementGrants()->delete();

        $this->actingAs($author)
            ->post(route('threads.reply', $thread), ['body' => 'A reply'])
            ->assertRedirect(route('threads.show', $thread).'#reply')
            ->assertSessionHasErrors(['body' => __('forum.errors.reply_not_permitted')], null, 'reply');

        $this->assertDatabaseMissing('posts', [
            'thread_id' => $thread->id,
            'body' => 'A reply',
        ]);
    }

    private function entitlementGatedForum(): Forum
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Entitlement Gated',
            'slug' => 'entitlement-gated-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::REGISTERED,
            'posting_policy' => ForumPostingPolicy::ENTITLEMENT_ONLY,
            'reply_entitlement_scope' => 'forums.entitlement_gated.post',
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);
    }

    private function thread(Forum $forum, User $author): Thread
    {
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Existing thread',
            'slug' => 'existing-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
            'created_at' => now(),
            'last_post_at' => now(),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        return $thread;
    }
}
