<?php

namespace Tests\Feature;

use App\BugReportStatus;
use App\Filament\Resources\BugReports\BugReportResource;
use App\Models\BugReport;
use App\Models\User;
use App\Policies\BugReportPolicy;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class BugReportPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_staff_can_access_all_bug_report_abilities(): void
    {
        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $bugReport = $this->createBugReport($staff);
        $policy = app(BugReportPolicy::class);

        $this->assertTrue($policy->viewAny($staff));
        $this->assertTrue($policy->view($staff, $bugReport));
        $this->assertTrue($policy->create($staff));
        $this->assertTrue($policy->update($staff, $bugReport));
        $this->assertTrue($policy->delete($staff, $bugReport));
        $this->assertTrue($policy->deleteAny($staff));
    }

    public function test_unprivileged_user_cannot_access_bug_report_abilities(): void
    {
        $user = User::factory()->create();
        $bugReport = $this->createBugReport($user);
        $policy = app(BugReportPolicy::class);

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $bugReport));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $bugReport));
        $this->assertFalse($policy->delete($user, $bugReport));
        $this->assertFalse($policy->deleteAny($user));
    }

    public function test_unprivileged_user_is_denied_by_the_dashboard_bug_report_resource_itself(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $bugReport = $this->createBugReport($user);

        $this->assertFalse(BugReportResource::canViewAny());
        $this->assertFalse(BugReportResource::canEdit($bugReport));
    }

    public function test_staff_is_allowed_by_the_dashboard_bug_report_resource_itself(): void
    {
        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $this->actingAs($staff);
        $bugReport = $this->createBugReport($staff);

        $this->assertTrue(BugReportResource::canViewAny());
        $this->assertTrue(BugReportResource::canEdit($bugReport));
        $this->assertFalse(BugReportResource::canCreate());
        $this->assertTrue(BugReportResource::canDelete($bugReport));
        $this->assertTrue(BugReportResource::canDeleteAny());
    }

    private function createBugReport(User $reporter): BugReport
    {
        $group = ForumGroup::query()->create([
            'name' => 'Policy Bug Report Group',
            'slug' => 'policy-bug-report-group-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Policy Bug Report Forum',
            'slug' => 'policy-bug-report-forum-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $reporter->id,
            'title' => 'Policy Bug Report Thread',
            'slug' => 'policy-bug-report-thread-'.Str::lower(Str::random(8)),
        ]);

        return BugReport::query()->create([
            'thread_id' => $thread->id,
            'reported_by_id' => $reporter->id,
            'status' => BugReportStatus::REPORTED,
        ]);
    }
}
