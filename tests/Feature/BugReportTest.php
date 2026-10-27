<?php

namespace Tests\Feature;

use App\BugReportStatus;
use App\Models\BugReport;
use App\Models\User;
use App\Notifications\BugReportUpdatedNotification;
use Carbon\Carbon;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\StandardCategories;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class BugReportTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_report_a_thread_as_a_bug(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Checkout fails with a fatal error.',
        ]);

        $response->assertRedirect(route('threads.show', $thread));
        $response->assertSessionHas('thread_status', __('forum.flash.bug_report_created'));

        $this->assertDatabaseHas('bug_reports', [
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED->value,
            'description' => 'Checkout fails with a fatal error.',
        ]);
    }

    public function test_double_submitting_bug_report_does_not_create_duplicate(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        Carbon::setTestNow(now());

        $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Checkout fails with a fatal error.',
        ])->assertRedirect(route('threads.show', $thread));

        // Simulate a double-click/back-button resubmit a few seconds later.
        $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Checkout fails with a fatal error.',
        ])->assertRedirect(route('threads.show', $thread));

        $this->assertSame(1, BugReport::query()->where('thread_id', $thread->id)->count());

        // Outside the dedupe window, another report is allowed through.
        Carbon::setTestNow(now()->addSeconds(20));
        $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Checkout fails with a fatal error.',
        ]);
        $this->assertSame(2, BugReport::query()->where('thread_id', $thread->id)->count());

        Carbon::setTestNow();
    }

    public function test_non_staff_cannot_report_a_thread_as_a_bug(): void
    {
        $thread = $this->createPublicThread();
        $member = User::factory()->create();

        $response = $this->actingAs($member)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Attempted report',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('bug_reports', ['thread_id' => $thread->id]);
    }

    public function test_report_as_bug_button_is_staff_only(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $member = User::factory()->create();

        $this->actingAs($moderator)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Report as Bug');

        $this->actingAs($member)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Report as Bug');
    }

    public function test_staff_can_progress_bug_report_status_from_reported_to_confirmed_to_completed(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        $confirmResponse = $this->actingAs($moderator)->post(route('bug-reports.update', $bugReport), [
            'status' => 'confirmed',
        ]);
        $confirmResponse->assertRedirect(route('threads.show', $thread));
        $bugReport->refresh();
        $this->assertSame(BugReportStatus::CONFIRMED, $bugReport->status);
        $this->assertNotNull($bugReport->confirmed_at);
        $this->assertNull($bugReport->completed_at);

        $completeResponse = $this->actingAs($moderator)->post(route('bug-reports.update', $bugReport), [
            'status' => 'completed',
            'resolution_note' => 'Fixed in release 2.1.',
        ]);
        $completeResponse->assertRedirect(route('threads.show', $thread));
        $bugReport->refresh();
        $this->assertSame(BugReportStatus::COMPLETED, $bugReport->status);
        $this->assertNotNull($bugReport->completed_at);
        $this->assertSame('Fixed in release 2.1.', $bugReport->resolution_note);
    }

    public function test_thread_subscribers_are_emailed_when_a_bug_report_changes(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $subscriber = $this->createEmailableUser();
        $bystander = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $moderator->id]);
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        Notification::fake();

        $this->actingAs($moderator)->post(route('bug-reports.update', $bugReport), [
            'status' => 'confirmed',
            'resolution_note' => 'Reproduced on 2.0.',
        ])->assertRedirect(route('threads.show', $thread));

        Notification::assertSentTo($subscriber, BugReportUpdatedNotification::class, function (BugReportUpdatedNotification $notification) use ($subscriber): bool {
            $mail = (string) $notification->toMail($subscriber)->render();

            return in_array('mail', $notification->via($subscriber), true)
                && str_contains($mail, 'Status changed from Reported to Confirmed.')
                && str_contains($mail, 'Reproduced on 2.0.');
        });
        Notification::assertNotSentTo($moderator, BugReportUpdatedNotification::class);
        Notification::assertNotSentTo($bystander, BugReportUpdatedNotification::class);
    }

    public function test_assignment_changes_made_outside_the_thread_page_also_notify_subscribers(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $subscriber = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        Notification::fake();

        $bugReport->update(['assigned_to_id' => $moderator->id]);

        Notification::assertSentTo($subscriber, BugReportUpdatedNotification::class);
    }

    public function test_saving_a_bug_report_without_changes_does_not_notify_subscribers(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $subscriber = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        Notification::fake();

        $bugReport->update(['status' => BugReportStatus::REPORTED]);

        Notification::assertNothingSent();
    }

    public function test_reporting_a_bug_subscribes_the_reporter_and_notifies_existing_subscribers(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $subscriber = User::factory()->create();
        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $subscriber->id]);

        Notification::fake();

        $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Checkout fails.',
        ])->assertRedirect(route('threads.show', $thread));

        $this->assertDatabaseHas('thread_subscriptions', ['thread_id' => $thread->id, 'user_id' => $moderator->id]);
        Notification::assertSentTo($subscriber, BugReportUpdatedNotification::class);
        Notification::assertNotSentTo($moderator, BugReportUpdatedNotification::class);
    }

    public function test_digest_mode_subscribers_do_not_get_instant_bug_report_email(): void
    {
        $thread = $this->createPublicThread();
        $subscriber = $this->createEmailableUser();
        app(Preferences::class)->setMode($subscriber, StandardCategories::THREAD_UPDATES_DIGEST_FREQUENCY, 'daily');
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $this->createModerator()->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        $this->assertNotContains('mail', (new BugReportUpdatedNotification($bugReport, ['x']))->via($subscriber));
    }

    public function test_staff_can_assign_bug_report_to_another_staff_member(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $assignee = $this->createModerator();
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        $response = $this->actingAs($moderator)->post(route('bug-reports.update', $bugReport), [
            'status' => 'reported',
            'assigned_to_id' => $assignee->id,
        ]);

        $response->assertRedirect(route('threads.show', $thread));
        $response->assertSessionHas('thread_status', __('forum.flash.bug_report_updated'));
        $this->assertSame($assignee->id, $bugReport->fresh()->assigned_to_id);
    }

    public function test_non_staff_cannot_update_a_bug_report(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $member = User::factory()->create();
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        $response = $this->actingAs($member)->post(route('bug-reports.update', $bugReport), [
            'status' => 'confirmed',
        ]);

        $response->assertForbidden();
        $this->assertSame(BugReportStatus::REPORTED, $bugReport->fresh()->status);
    }

    public function test_a_new_bug_report_is_allowed_once_the_previous_one_is_completed(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'First bug.',
        ]);
        $firstReport = BugReport::query()->where('thread_id', $thread->id)->firstOrFail();
        $this->actingAs($moderator)->post(route('bug-reports.update', $firstReport), [
            'status' => 'completed',
        ]);

        $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Second, unrelated bug.',
        ]);

        $this->assertSame(2, BugReport::query()->where('thread_id', $thread->id)->count());
    }

    public function test_can_report_a_thread_as_a_bug_while_an_active_report_already_exists(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::CONFIRMED,
            'description' => 'First, still-active bug.',
        ]);

        $response = $this->actingAs($moderator)->post(route('threads.bug-reports.store', $thread), [
            'description' => 'Second, distinct bug on the same thread.',
        ]);

        $response->assertRedirect(route('threads.show', $thread));
        $response->assertSessionHas('thread_status', __('forum.flash.bug_report_created'));
        $this->assertSame(2, BugReport::query()->where('thread_id', $thread->id)->count());
        $this->assertDatabaseHas('bug_reports', [
            'thread_id' => $thread->id,
            'status' => BugReportStatus::CONFIRMED->value,
            'description' => 'First, still-active bug.',
        ]);
        $this->assertDatabaseHas('bug_reports', [
            'thread_id' => $thread->id,
            'status' => BugReportStatus::REPORTED->value,
            'description' => 'Second, distinct bug on the same thread.',
        ]);
    }

    public function test_report_as_bug_button_stays_visible_while_an_active_report_exists(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();

        $response = $this->actingAs($moderator)->get(route('threads.show', $thread));
        $response->assertSee('Report as Bug');

        BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        $this->actingAs($moderator)
            ->get(route('threads.show', $thread))
            ->assertSee('Report as Bug');
    }

    public function test_bug_report_status_and_description_are_visible_to_regular_users(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $member = User::factory()->create();

        BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::CONFIRMED,
            'description' => 'Distinctive bug description text.',
        ]);

        $this->actingAs($member)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Distinctive bug description text.')
            ->assertSee(BugReportStatus::CONFIRMED->label());

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Distinctive bug description text.');
    }

    public function test_thread_list_shows_a_bug_indicator_with_the_latest_status_in_its_hover_text(): void
    {
        $thread = $this->createPublicThread();
        $thread->update(['moderation_status' => ContentModerationStatus::APPROVED]);
        $moderator = $this->createModerator();

        $this->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertDontSee('data-thread-bug-report-indicator', false);

        BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
        ]);

        $this->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('data-thread-bug-report-indicator', false)
            ->assertSee('Bug status: '.BugReportStatus::REPORTED->label());
    }

    public function test_bug_report_reporter_assignee_and_management_form_are_staff_only(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $member = User::factory()->create();

        BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
            'description' => 'Distinctive bug description text.',
        ]);

        $this->actingAs($moderator)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Reported by')
            ->assertSee('Assign to');

        $this->actingAs($member)
            ->get(route('threads.show', $thread))
            ->assertOk()
            ->assertDontSee('Reported by')
            ->assertDontSee('Assign to');
    }

    public function test_dashboard_bug_report_list_and_edit_pages_render_for_staff(): void
    {
        $thread = $this->createPublicThread();
        $moderator = $this->createModerator();
        $bugReport = BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $moderator->id,
            'status' => BugReportStatus::REPORTED,
            'description' => 'Dashboard render check.',
        ]);

        $this->actingAs($moderator)->get('/dashboard/bug-reports')->assertOk();
        $this->actingAs($moderator)->get("/dashboard/bug-reports/{$bugReport->id}/edit")->assertOk();
    }

    private function createPublicThread(): Thread
    {
        $group = ForumGroup::query()->create([
            'name' => 'General',
            'slug' => 'general-bugreport-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-bugreport-'.Str::lower(Str::random(8)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);

        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Bug Report Test Thread',
            'slug' => 'bug-report-test-thread-'.Str::lower(Str::random(8)),
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'First post',
        ]);

        return $thread;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEmailableUser(array $attributes = []): User
    {
        $user = User::factory()->create(['receives_email' => true, ...$attributes]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->getKey(),
            'entitlement_key' => (string) config('filagora-zencart.notifications.email_entitlement'),
            'grant_type' => 'registration',
            'source_type' => 'registration',
            'starts_at' => now(),
            'reason' => 'Test default.',
        ]);

        return $user;
    }

    private function createModerator(): User
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        return $moderator;
    }
}
