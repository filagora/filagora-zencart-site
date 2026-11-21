<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EntitlementRequestMailNotification;
use App\Notifications\PluginMailNotification;
use App\Notifications\PluginReportMailNotification;
use App\Notifications\PrivacyMailNotification;
use App\Notifications\SecurityMailNotification;
use App\Notifications\SiteCategories;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Notifications\Contracts\EmailSuppression;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\Services\Preferences;
use Filagora\Plugins\Enums\ModerationAction;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Events\PluginModerated;
use Filagora\Plugins\Events\PluginReportReviewed;
use Filagora\Plugins\Events\ReleaseRejected;
use Filagora\Plugins\Models\PluginReport;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * A member chooses email, the bell, both or neither for the news about their own plugin
 * submissions, plugin reports and access requests; privacy request mail is required and always
 * goes. Mail for staff and the shared support address is not a member's choice.
 */
class NotificationChoicesTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private function preferences(): Preferences
    {
        return app(Preferences::class);
    }

    private function turnOff(User $user, string $category, NotificationChannel $channel): void
    {
        $this->preferences()->set($user, $category, $channel, false);
        $this->preferences()->flush();
    }

    private function suppressMailTo(User $user): void
    {
        $this->app->instance(EmailSuppression::class, new class([$user->id]) implements EmailSuppression
        {
            /** @param list<int> $ids */
            public function __construct(private array $ids) {}

            public function isSuppressed(Authenticatable $user): bool
            {
                return in_array($user->getAuthIdentifier(), $this->ids, true);
            }
        });
        $this->app->forgetInstance(Preferences::class);
    }

    /** @return list<string> */
    private function authorKinds(): array
    {
        return ['submitted', 'submitted_live', 'release_approved', 'release_rejected', 'plugin_approved', 'plugin_rejected'];
    }

    public function test_a_new_member_is_offered_the_three_site_categories_on_email_and_the_bell_and_both_are_on(): void
    {
        $user = User::factory()->create();

        foreach ([SiteCategories::PLUGIN_SUBMISSIONS, SiteCategories::PLUGIN_REPORTS, SiteCategories::ENTITLEMENT_REQUESTS] as $id) {
            $this->assertTrue($this->preferences()->allows($user, $id, NotificationChannel::EMAIL), $id);
            $this->assertTrue($this->preferences()->allows($user, $id, NotificationChannel::IN_APP), $id);
        }
    }

    public function test_an_author_who_turns_off_email_for_their_submissions_gets_no_plugin_mail(): void
    {
        $author = User::factory()->create();

        foreach ($this->authorKinds() as $kind) {
            $this->assertSame(['mail'], (new PluginMailNotification($kind, 'approved', [], 'plugin-approved', []))->via($author), $kind);
        }

        $this->turnOff($author, SiteCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL);

        foreach ($this->authorKinds() as $kind) {
            $this->assertSame([], (new PluginMailNotification($kind, 'approved', [], 'plugin-approved', []))->via($author), $kind);
        }
    }

    public function test_the_support_copy_of_a_submission_is_not_a_members_choice(): void
    {
        $mail = new PluginMailNotification('submitted_support', 'submitted_support', [], 'plugin-submitted-support', []);

        $this->assertSame(['mail'], $mail->via(new AnonymousNotifiable));
    }

    public function test_a_followers_plugin_update_follows_the_plugin_updates_choice_not_the_submissions_one(): void
    {
        $follower = User::factory()->create();
        $this->turnOff($follower, SiteCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL);

        $update = new PluginMailNotification('release_updated', 'updated', [], 'plugin-updated', [], withUnsubscribe: true);

        $this->assertSame(['mail'], $update->via($follower));

        $this->turnOff($follower, PluginMailNotification::UPDATES_CATEGORY, NotificationChannel::EMAIL);

        $this->assertSame([], $update->via($follower));
    }

    public function test_a_reporter_who_turns_off_email_gets_no_report_review_mail_but_staff_still_get_the_report(): void
    {
        $reporter = User::factory()->create();
        $reviewed = new PluginReportMailNotification(new PluginReport, PluginReportMailNotification::STATUS_UPDATED);
        $submitted = new PluginReportMailNotification(new PluginReport, PluginReportMailNotification::SUBMITTED);

        $this->assertSame(['mail'], $reviewed->via($reporter));

        $this->turnOff($reporter, SiteCategories::PLUGIN_REPORTS, NotificationChannel::EMAIL);

        $this->assertSame([], $reviewed->via($reporter));
        $this->assertSame(['mail'], $submitted->via(new AnonymousNotifiable));
    }

    public function test_a_member_who_turns_off_email_for_access_requests_gets_no_receipt_or_status_mail(): void
    {
        $member = User::factory()->create();
        $this->turnOff($member, SiteCategories::ENTITLEMENT_REQUESTS, NotificationChannel::EMAIL);
        $request = new EntitlementRequest;

        foreach ([EntitlementRequestMailNotification::SUBMITTED_USER, EntitlementRequestMailNotification::STATUS_UPDATED] as $kind) {
            $this->assertSame([], (new EntitlementRequestMailNotification($kind, $request))->via($member), $kind);
        }

        $this->assertSame(['mail'], (new EntitlementRequestMailNotification(EntitlementRequestMailNotification::SUBMITTED_STAFF, $request))->via(new AnonymousNotifiable));
    }

    public function test_an_authors_security_mail_is_their_choice_and_mail_for_staff_is_not(): void
    {
        $author = User::factory()->create();
        $this->turnOff($author, SiteCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL);
        [, $release] = $this->pluginWithRelease();

        $forAuthor = new SecurityMailNotification('scan_complete', $release);
        $forStaff = new SecurityMailNotification('finding_flagged', $release, forStaff: true);

        $this->assertSame([], $forAuthor->via($author));
        $this->assertSame(['mail'], $forStaff->via($author));
        $this->assertSame(['mail'], $forAuthor->via(new AnonymousNotifiable));
    }

    public function test_privacy_request_mail_is_required_so_it_reaches_a_suppressed_address_and_cannot_be_switched_off(): void
    {
        $member = User::factory()->create();
        $this->suppressMailTo($member);
        $privacy = new PrivacyMailNotification(PrivacyMailNotification::STATUS_UPDATED, new PrivacyRequest);

        $this->assertSame(['mail'], $privacy->via($member));
        $this->assertSame(['mail'], (new PrivacyMailNotification(PrivacyMailNotification::SUBMITTED_STAFF, new PrivacyRequest))->via(new AnonymousNotifiable));

        $this->expectException(InvalidPreference::class);
        $this->preferences()->set($member, SiteCategories::PRIVACY_REQUESTS, NotificationChannel::EMAIL, false);
    }

    public function test_a_suppressed_address_gets_no_optional_plugin_mail_but_still_gets_privacy_mail(): void
    {
        $member = User::factory()->create();
        $this->suppressMailTo($member);

        $this->assertSame([], (new PluginMailNotification('release_approved', 'release_approved', [], 'plugin-release-approved', []))->via($member));
        $this->assertSame(['mail'], (new PrivacyMailNotification(PrivacyMailNotification::SUBMITTED_USER, new PrivacyRequest))->via($member));
    }

    public function test_an_approved_or_rejected_plugin_rings_the_owners_bell_unless_they_turned_it_off(): void
    {
        [$plugin, , $owner] = $this->pluginWithRelease();
        $moderator = User::factory()->create();

        event(new PluginModerated($plugin, ModerationAction::APPROVE, $moderator, ''));
        event(new PluginModerated($plugin, ModerationAction::REJECT_PERMANENTLY, $moderator, 'Not a fit.'));

        $rows = $owner->notifications()->get();
        $this->assertCount(2, $rows);
        $this->assertSame(SiteCategories::PLUGIN_SUBMISSIONS, $rows->first()->data['category']);

        $this->turnOff($owner, SiteCategories::PLUGIN_SUBMISSIONS, NotificationChannel::IN_APP);
        event(new PluginModerated($plugin, ModerationAction::APPROVE, $moderator, ''));

        $this->assertCount(2, $owner->notifications()->get());
    }

    public function test_a_rejected_release_uses_the_submissions_choice_for_the_bell(): void
    {
        [$plugin, $release, $owner] = $this->pluginWithRelease();

        event(new ReleaseRejected($plugin, $release, 'Please add a readme.'));
        $this->assertSame(SiteCategories::PLUGIN_SUBMISSIONS, $owner->notifications()->sole()->data['category']);

        $this->turnOff($owner, SiteCategories::PLUGIN_SUBMISSIONS, NotificationChannel::IN_APP);
        event(new ReleaseRejected($plugin, $release, 'Again.'));

        $this->assertCount(1, $owner->notifications()->get());
    }

    public function test_a_reviewed_report_rings_the_reporters_bell_unless_they_turned_it_off(): void
    {
        [$plugin, $release] = $this->pluginWithRelease();
        $reporter = User::factory()->create();
        $report = PluginReport::query()->forceCreate([
            'plugin_id' => $plugin->id,
            'plugin_release_id' => $release->id,
            'reporter_id' => $reporter->id,
            'reason' => 'It phones home.',
            'status' => 'reviewed',
            'resolution_note' => 'Removed the call.',
        ]);

        event(new PluginReportReviewed($report));
        $this->assertSame(SiteCategories::PLUGIN_REPORTS, $reporter->notifications()->sole()->data['category']);

        $this->turnOff($reporter, SiteCategories::PLUGIN_REPORTS, NotificationChannel::IN_APP);
        event(new PluginReportReviewed($report));

        $this->assertCount(1, $reporter->notifications()->get());
    }

    public function test_an_access_request_status_change_rings_the_members_bell_unless_they_turned_it_off(): void
    {
        $member = User::factory()->create();
        $request = EntitlementRequest::query()->forceCreate(['user_id' => $member->id, 'entitlement_key' => 'forums.paid', 'status' => EntitlementRequestStatus::PENDING->value, 'request_reason' => 'Please.', 'requested_at' => now()]);

        $request->update(['status' => EntitlementRequestStatus::APPROVED->value]);
        $this->assertSame(SiteCategories::ENTITLEMENT_REQUESTS, $member->notifications()->sole()->data['category']);

        $this->turnOff($member, SiteCategories::ENTITLEMENT_REQUESTS, NotificationChannel::IN_APP);
        $request->update(['status' => EntitlementRequestStatus::REJECTED->value]);

        $this->assertCount(1, $member->notifications()->get());
    }

    public function test_the_profile_groups_the_choices_and_shows_privacy_as_always_on(): void
    {
        $member = $this->makeAcknowledgedUser();

        $this->actingAs($member)->get('/profile?tab=settings')
            ->assertOk()
            ->assertSeeInOrder(['Forums', 'Plugins', 'Messages'])
            ->assertSee('Your plugin submissions: receipts, approvals and rejections')
            ->assertSee('Updates to your access requests')
            ->assertSee('Updates to your privacy requests')
            ->assertSee('Always on')
            ->assertDontSee('name="channel[privacy.request_updates][email]"', false);
    }

    public function test_saving_the_profile_never_touches_a_required_category(): void
    {
        $member = $this->makeAcknowledgedUser();

        $this->actingAs($member)->post(route('profile.notifications.update'), [])->assertRedirect('/profile?tab=settings');
        $this->preferences()->flush();

        $this->assertTrue($this->preferences()->allows($member, SiteCategories::PRIVACY_REQUESTS, NotificationChannel::EMAIL));
        $this->assertFalse($this->preferences()->allows($member, SiteCategories::PLUGIN_SUBMISSIONS, NotificationChannel::EMAIL));
    }

    private function makeAcknowledgedUser(): User
    {
        $user = User::factory()->create();
        $policy = PrivacyPolicyVersion::query()->where('is_active', true)->orderByDesc('published_at')->orderByDesc('id')->first();

        if ($policy instanceof PrivacyPolicyVersion) {
            UserPrivacyPolicyAcceptance::query()->updateOrCreate(
                ['user_id' => $user->id, 'privacy_policy_version_id' => $policy->id],
                ['accepted_at' => now(), 'ip_hash' => hash('sha256', '127.0.0.1'), 'user_agent_hash' => hash('sha256', 'phpunit')],
            );
        }

        return $user;
    }

    /**
     * @return array{0: Plugin, 1: PluginRelease, 2: User}
     */
    private function pluginWithRelease(): array
    {
        $owner = User::factory()->create();
        $type = PluginType::query()->create(['name' => 'Admin Tools', 'slug' => 'admin-tools', 'is_active' => true, 'position' => 30]);
        $plugin = Plugin::query()->create(['name' => 'Admin Audit Trail', 'slug' => 'admin-audit-trail', 'plugin_type_id' => $type->id, 'owner_id' => $owner->id, 'status' => PluginStatus::ACTIVE->value]);
        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/missing.zip',
            'zip_filename' => 'missing.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);

        return [$plugin, $release, $owner];
    }
}
