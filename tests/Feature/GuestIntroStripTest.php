<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class GuestIntroStripTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private const STRIP_TEXT = 'If this is your first visit, check our';

    /**
     * @return array<string, string>
     */
    private function guestBrowsingUrls(): array
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $forum = $this->createForum($group, 'Announcements');
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => User::factory()->create()->id,
            'title' => 'Release 2.0',
            'slug' => 'release-2-0',
        ]);
        $type = PluginType::query()->create(['name' => 'Plugin', 'slug' => 'plugin', 'is_active' => true]);
        $plugin = Plugin::query()->create([
            'name' => 'Strip Plugin',
            'slug' => 'strip-plugin',
            'plugin_type_id' => $type->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        return [
            'forum' => route('forums.show', $forum),
            'thread' => route('threads.show', $thread),
            'forum search' => route('forums.search', ['q' => 'release']),
            'thread search' => route('threads.search', ['thread' => $thread, 'q' => 'release']),
            'today' => route('forums.today'),
            'plugins index' => route('plugins.index'),
            'plugin show' => route('plugins.show', $plugin),
            'plugin search' => route('plugins.search', ['q' => 'strip']),
        ];
    }

    public function test_guest_sees_intro_strip_on_forum_and_plugin_pages(): void
    {
        foreach ($this->guestBrowsingUrls() as $label => $url) {
            $this->get($url)->assertOk()->assertSee(self::STRIP_TEXT, false, "Strip missing on {$label}");
        }
    }

    public function test_authenticated_user_does_not_see_intro_strip(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach ($this->guestBrowsingUrls() as $label => $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertDontSee(self::STRIP_TEXT, false, "Strip shown to member on {$label}");
        }
    }

    public function test_guest_sees_subscribe_button_that_opens_login_on_forum_page(): void
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $forum = $this->createForum($group, 'Announcements');

        $html = $this->get(route('forums.show', $forum))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-auth-modal-url="\/login">Subscribe<\/button>/', $html);
    }

    public function test_guest_cannot_post_a_forum_subscription(): void
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $forum = $this->createForum($group, 'Announcements');

        $this->post(route('forums.subscribe', $forum))->assertRedirect();
        $this->assertDatabaseCount('forum_subscriptions', 0);
    }

    public function test_verified_member_keeps_real_subscribe_form(): void
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $forum = $this->createForum($group, 'Announcements');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee(route('forums.subscribe', $forum), false);
    }

    private function createForum(ForumGroup $group, string $name): Forum
    {
        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => $name,
            'slug' => 'announcements',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }
}
