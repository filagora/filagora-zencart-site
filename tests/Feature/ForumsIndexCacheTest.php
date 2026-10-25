<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ForumsIndexCache;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ForumsIndexCacheTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_forums_warm_cache_command_populates_guest_cache_entry(): void
    {
        $this->markTestSkipped('The forums:warm-cache command arrives with the scheduler and ops slice: S9.');

        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);
        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread-cache',
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Hello world',
        ]);

        $guestKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_GUEST);
        $registeredKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_REGISTERED);
        $memberKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_MEMBER);
        $staffKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_STAFF);
        $this->assertNull(Cache::get($guestKey));

        $exit = Artisan::call('forums:warm-cache', ['--flush' => true]);
        $this->assertSame(0, $exit);

        $cached = Cache::get($guestKey);
        $this->assertIsArray($cached);
        $this->assertSame(
            ForumsIndexCache::signatureForForumIds([(int) $forum->id]),
            $cached['signature'] ?? null
        );
        $this->assertIsArray($cached['forumMeta'] ?? null);
        $this->assertIsArray($cached['totals'] ?? null);
        $this->assertIsArray(Cache::get($registeredKey));
        $this->assertIsArray(Cache::get($memberKey));
        $this->assertIsArray(Cache::get($staffKey));
    }

    public function test_forums_index_cache_is_flushed_after_post_mutation(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);
        $forum = $this->createForum($group, 'Public Forum', ForumVisibility::PUBLIC);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Welcome Thread',
            'slug' => 'welcome-thread-cache-2',
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        $guestKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_GUEST);

        $this->get('/forums')->assertOk();
        $this->assertIsArray(Cache::get($guestKey));

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Second post should flush index cache',
        ]);

        $this->assertNull(Cache::get($guestKey));
    }

    public function test_warmed_registered_cache_hides_paid_or_author_thread_metadata(): void
    {
        $this->markTestSkipped('The forums:warm-cache command arrives with the scheduler and ops slice: S9.');

        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial-cache',
            'is_active' => true,
        ]);
        $forum = $this->createForum($group, 'Commercial Help Wanted', ForumVisibility::PAID_OR_AUTHOR);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Private customer request',
            'slug' => 'private-customer-request-cache',
        ]);
        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Private request details',
        ]);
        $forum->forceFill([
            'thread_count' => 1,
            'post_count' => 1,
            'last_post_id' => $post->id,
            'last_post_thread_id' => $thread->id,
            'last_post_author_id' => $author->id,
            'last_post_at' => now(),
        ])->save();

        $this->artisan('forums:warm-cache', ['--flush' => true])->assertSuccessful();

        $registeredMeta = Cache::get(ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_REGISTERED))['forumMeta'][$forum->id];
        $this->assertSame(0, $registeredMeta['threadCount']);
        $this->assertSame(0, $registeredMeta['postCount']);
        $this->assertNull($registeredMeta['lastPost']);

        $memberMeta = Cache::get(ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_MEMBER))['forumMeta'][$forum->id];
        $this->assertSame(1, $memberMeta['threadCount']);
        $this->assertSame('Private customer request', $memberMeta['lastPost']['title']);
    }

    public function test_entitlement_only_forums_do_not_use_shared_registered_cache_scope(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Restricted',
            'slug' => 'restricted-cache',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'New Website Feedback',
            'slug' => 'new-website-feedback-cache',
            'visibility' => ForumVisibility::ENTITLEMENT_ONLY,
            'required_entitlement_scope' => 'forums.new_website_feedback.view',
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'forums.new_website_feedback.view',
            'grant_type' => 'manual',
            'granted_by_id' => $user->id,
            'starts_at' => now()->subMinute(),
        ]);

        $registeredKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_REGISTERED);
        $memberKey = ForumsIndexCache::keyForScope(ForumsIndexCache::SCOPE_MEMBER);

        $this->assertNull(Cache::get($registeredKey));
        $this->assertNull(Cache::get($memberKey));

        $this->actingAs($user)
            ->get('/forums')
            ->assertOk()
            ->assertSee($forum->name);

        $this->assertNull(Cache::get($registeredKey));
        $this->assertNull(Cache::get($memberKey));
    }

    private function createForum(ForumGroup $group, string $name, ForumVisibility $visibility): Forum
    {
        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => $visibility,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }
}
