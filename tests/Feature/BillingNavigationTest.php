<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class BillingNavigationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_profile_page_links_to_billing_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Memberships & Billing', false)
            ->assertSee(route('profile.show', ['tab' => 'billing']), false);
    }

    public function test_paid_forum_pages_link_to_billing_page(): void
    {
        $group = ForumGroup::query()->create([
            'name' => 'Commercial',
            'slug' => 'commercial-billing-nav',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Commercial Help Wanted',
            'slug' => 'commercial-help-wanted-billing-nav',
            'visibility' => ForumVisibility::PAID_ONLY,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create([
            'membership_expires_at' => now()->addDay(),
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Billing Navigation Thread',
            'slug' => 'billing-navigation-thread',
        ]);

        $this->actingAs($author)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Manage memberships')
            ->assertSee(route('billing.index'), false);

        $this->actingAs($author)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Manage memberships')
            ->assertSee(route('billing.index'), false);
    }
}
