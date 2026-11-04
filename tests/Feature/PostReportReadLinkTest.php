<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Filament\Resources\PostReports\Pages\ListPostReports;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\PostReport;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostReportReadLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_on_a_post_report_opens_the_post_on_its_page_in_a_new_tab(): void
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);

        foreach (['operator.dashboard.access', 'operator.forum.moderate'] as $key) {
            UserEntitlementGrant::query()->create(['user_id' => $staff->id, 'entitlement_key' => $key, 'grant_type' => 'manual', 'starts_at' => now()->subMinute()]);
        }

        $author = User::factory()->create();
        $group = ForumGroup::query()->create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
        $forum = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $thread = Thread::query()->create(['forum_id' => $forum->id, 'author_id' => $author->id, 'title' => 'A thread', 'slug' => 'a-thread']);
        $post = Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'Reported text']);
        $report = PostReport::query()->create(['post_id' => $post->id, 'thread_id' => $thread->id, 'reporter_id' => $author->id, 'reason' => 'spam']);

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($staff);

        Livewire::test(ListPostReports::class)
            ->assertTableActionHasUrl('previewPost', route('threads.show', $thread).'#post-'.$post->getKey(), $report)
            ->assertTableActionShouldOpenUrlInNewTab('previewPost', $report);
    }
}
