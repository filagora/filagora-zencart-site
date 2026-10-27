<?php

namespace Tests\Feature;

use App\BugReportStatus;
use App\Models\BugReport;
use App\Models\User;
use App\Support\ResolvedBugThreadVisibility;
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

class ForumListResolvedBugThreadFilterTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_toggle_hides_only_threads_whose_bug_reports_are_all_terminal(): void
    {
        $forum = $this->createForum();

        $noBug = $this->createThread($forum, 'Thread without any bug report');

        $allResolved = $this->createThread($forum, 'Thread with every bug report resolved');
        $this->bugReport($allResolved, BugReportStatus::COMPLETED);
        $this->bugReport($allResolved, BugReportStatus::WONT_FIX);

        $mixed = $this->createThread($forum, 'Thread with one bug report still open');
        $this->bugReport($mixed, BugReportStatus::COMPLETED);
        $this->bugReport($mixed, BugReportStatus::REPORTED);

        $stillOpen = $this->createThread($forum, 'Thread with an open bug report');
        $this->bugReport($stillOpen, BugReportStatus::CONFIRMED);

        $moderator = $this->createModerator();

        // Default: nothing is hidden.
        $this->actingAs($moderator)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee($noBug->title)
            ->assertSee($allResolved->title)
            ->assertSee($mixed->title)
            ->assertSee($stillOpen->title);

        // Toggle on.
        $this->actingAs($moderator)
            ->post(route('profile.hide-resolved-bug-threads.update'), [
                'hidden' => '1',
                'redirect_to' => '/forums/'.$forum->id,
            ])
            ->assertRedirect('/forums/'.$forum->id);

        $this->actingAs($moderator)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee($noBug->title)
            ->assertSee($mixed->title)
            ->assertSee($stillOpen->title)
            ->assertDontSee($allResolved->title);

        // Toggle back off.
        $this->actingAs($moderator)
            ->post(route('profile.hide-resolved-bug-threads.update'), ['hidden' => '0'])
            ->assertRedirect();

        $this->actingAs($moderator)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee($allResolved->title);
    }

    public function test_toggle_is_moderator_only(): void
    {
        $forum = $this->createForum();
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertDontSee('Hide threads with all bug reports resolved');

        $this->actingAs($member)
            ->post(route('profile.hide-resolved-bug-threads.update'), ['hidden' => '1'])
            ->assertForbidden();

        $this->assertFalse(
            app(ResolvedBugThreadVisibility::class)->hiddenFor(request(), $member->fresh())
        );
    }

    public function test_moderator_sees_the_toggle_and_its_state(): void
    {
        $forum = $this->createForum();
        $moderator = $this->createModerator();

        $this->actingAs($moderator)
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('Hide threads with all bug reports resolved');

        $this->actingAs($moderator)
            ->withSession([ResolvedBugThreadVisibility::SESSION_KEY => true])
            ->get(route('forums.show', $forum))
            ->assertOk()
            ->assertSee('name="hidden" value="0"', false);
    }

    private function createForum(): Forum
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-resolved-bug-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Resolved Bug Filter Forum',
            'slug' => 'resolved-bug-filter-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
    }

    private function createThread(Forum $forum, string $title): Thread
    {
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        return $thread;
    }

    private function bugReport(Thread $thread, BugReportStatus $status): BugReport
    {
        return BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => User::factory()->create()->id,
            'status' => $status,
            'completed_at' => $status->isTerminal() ? now() : null,
        ]);
    }

    private function createModerator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
