<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PostReportSubmittedNotification;
use Filagora\Core\Models\Setting;
use Filagora\Forums\Enums\PostReportStatus;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\PostReport;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PostReportTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_duplicate_open_report_by_same_user_is_blocked(): void
    {
        [, $post] = $this->createPublicThreadWithPost();
        $reporter = User::factory()->create();

        PostReport::query()->create([
            'post_id' => $post->id,
            'thread_id' => $post->thread_id,
            'reporter_id' => $reporter->id,
            'reason' => 'Initial report',
            'status' => PostReportStatus::OPEN->value,
        ]);
        Notification::fake();

        $response = $this->actingAs($reporter)->post(route('posts.report', $post), [
            'reason' => 'Second report',
        ]);

        $response->assertSessionHas('thread_status', __('forum.reports.already_reported'));
        $this->assertSame(1, PostReport::query()->count());
        Notification::assertSentOnDemandTimes(PostReportSubmittedNotification::class, 0);
    }

    public function test_guest_report_redirects_to_login_modal_with_thread_intended_url(): void
    {
        Notification::fake();
        [$thread, $post] = $this->createPublicThreadWithPost();

        $this->post(route('posts.report', $post), [
            'reason' => 'Report reason',
        ])->assertRedirect(route('home', [
            'auth' => 'login',
            'intended' => route('threads.show', $thread, false),
        ]));
        Notification::assertSentOnDemandTimes(PostReportSubmittedNotification::class, 0);
    }

    public function test_unverified_user_cannot_report_posts(): void
    {
        Notification::fake();
        [, $post] = $this->createPublicThreadWithPost();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('posts.report', $post), [
                'reason' => 'Report reason',
            ])
            ->assertRedirect(route('verification.notice', [
                'intended' => '/posts/'.$post->id.'/report',
            ]));
        Notification::assertSentOnDemandTimes(PostReportSubmittedNotification::class, 0);
    }

    public function test_reporting_does_not_send_staff_email_if_forum_support_email_setting_is_missing_or_invalid(): void
    {
        Notification::fake();
        [, $post] = $this->createPublicThreadWithPost();
        $reporter = User::factory()->create();

        Setting::query()->where('key', 'forum_support_email')->delete();
        $this->actingAs($reporter)->post(route('posts.report', $post), [
            'reason' => 'Missing setting case',
        ]);
        Notification::assertSentOnDemandTimes(PostReportSubmittedNotification::class, 0);

        Setting::query()->updateOrCreate(
            ['key' => 'forum_support_email'],
            [
                'label' => 'Forum Support Email',
                'value' => 'not-an-email',
                'value_type' => 'string',
                'is_public' => false,
            ]
        );
        $anotherReporter = User::factory()->create();
        $this->actingAs($anotherReporter)->post(route('posts.report', $post), [
            'reason' => 'Invalid setting case',
        ]);
        Notification::assertSentOnDemandTimes(PostReportSubmittedNotification::class, 0);
    }

    public function test_thread_page_shows_report_button_for_verified_non_author_and_hides_for_author(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $author = $post->author;
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('data-report-open', false);

        $this->actingAs($author)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('data-report-open', false);
    }

    public function test_open_report_shows_a_review_strip_to_the_reporter_and_staff_but_not_other_members(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $reporter = User::factory()->create();
        $otherMember = User::factory()->create();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        PostReport::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'reporter_id' => $reporter->id,
            'reason' => 'Spam',
            'status' => PostReportStatus::OPEN->value,
        ]);

        // The reporter sees the strip.
        $this->actingAs($reporter)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('This post has been reported');

        // Staff see the strip too, even though they didn't file the report.
        $this->actingAs($moderator)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('This post has been reported');

        // Another member (who did not report and isn't staff) doesn't see it,
        // and can still add their own report.
        $this->actingAs($otherMember)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('This post has been reported')
            ->assertSee('data-report-open', false);
    }

    public function test_staff_only_sees_the_review_strip_on_the_reported_post_not_every_post_in_the_thread(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $reporter = User::factory()->create();
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $secondPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $post->author_id,
            'body' => 'Second, unreported post.',
        ]);
        $thirdPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $post->author_id,
            'body' => 'Third, unreported post.',
        ]);

        PostReport::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'reporter_id' => $reporter->id,
            'reason' => 'Spam',
            'status' => PostReportStatus::OPEN->value,
        ]);

        $response = $this->actingAs($moderator)->get(route('threads.show', $thread));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'This post has been reported'));
    }

    public function test_open_report_review_strip_is_hidden_from_guests(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        PostReport::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'reporter_id' => User::factory()->create()->id,
            'reason' => 'Spam',
            'status' => PostReportStatus::OPEN->value,
        ]);

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('This post has been reported');
    }

    public function test_review_strip_is_gone_once_the_report_is_resolved(): void
    {
        [$thread, $post] = $this->createPublicThreadWithPost();
        $reporter = User::factory()->create();
        $report = PostReport::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'reporter_id' => $reporter->id,
            'reason' => 'Spam',
            'status' => PostReportStatus::OPEN->value,
        ]);
        $report->update(['status' => PostReportStatus::DISMISSED->value]);

        $this->actingAs($reporter)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('This post has been reported');
    }

    public function test_reporter_is_never_emailed_when_a_report_is_resolved(): void
    {
        Notification::fake();

        [$thread, $post] = $this->createPublicThreadWithPost();
        $reporter = User::factory()->create();

        $report = PostReport::query()->create([
            'post_id' => $post->id,
            'thread_id' => $thread->id,
            'reporter_id' => $reporter->id,
            'reason' => 'Spam and abusive content',
            'status' => PostReportStatus::OPEN->value,
        ]);

        $report->update([
            'status' => PostReportStatus::REVIEWED->value,
            'resolution_note' => 'We reviewed the report and took action.',
        ]);

        Notification::assertNothingSentTo($reporter);
    }

    /**
     * @return array{0: Thread, 1: Post}
     */
    private function createPublicThreadWithPost(): array
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Announcements',
            'slug' => 'announcements-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Report Test Thread',
            'slug' => 'report-test-thread-'.Str::lower(Str::random(6)),
        ]);
        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Original post body.',
        ]);

        $thread->forceFill([
            'last_post_author_id' => $author->id,
            'last_post_at' => $post->created_at,
        ])->save();

        return [$thread, $post];
    }
}
