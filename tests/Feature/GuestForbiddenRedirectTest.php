<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestForbiddenRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_hitting_a_permission_gated_page_is_sent_to_login(): void
    {
        $thread = $this->createStaffOnlyThread();

        $this->get(route('threads.show', $thread), ['Accept' => 'text/html,application/xhtml+xml'])
            ->assertRedirect('/?auth=login&intended=%2Fthreads%2F'.$thread->id);
    }

    public function test_guest_requests_that_are_not_page_navigations_still_get_403(): void
    {
        $thread = $this->createStaffOnlyThread();

        $this->get(route('threads.show', $thread), ['Accept' => 'image/avif,image/webp,*/*;q=0.8'])->assertForbidden();
        $this->getJson(route('threads.show', $thread))->assertForbidden();
        $this->get(route('threads.show', $thread), ['Accept' => 'text/html', 'Authorization' => 'Bearer x'])->assertForbidden();
    }

    public function test_signed_in_user_without_permission_still_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $this->createStaffOnlyThread()))
            ->assertForbidden();
    }

    private function createStaffOnlyThread(): Thread
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'visibility' => ForumVisibility::STAFF_ONLY,
            'is_active' => true,
        ]);

        return Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => User::factory()->create()->id,
            'title' => 'Thread',
            'slug' => 'thread',
        ]);
    }
}
