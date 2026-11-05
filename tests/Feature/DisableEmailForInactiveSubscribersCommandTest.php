<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\Services\Preferences;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DisableEmailForInactiveSubscribersCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function emailableUser(array $attributes): User
    {
        $user = User::factory()->create(['receives_email' => true, ...$attributes]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->getKey(),
            'entitlement_key' => (string) config('filagora-zencart.notifications.email_entitlement'),
            'grant_type' => 'registration',
            'source_type' => 'registration',
            'starts_at' => now()->subYears(2),
            'reason' => 'Test default.',
        ]);

        return $user;
    }

    private function subscribeToAThread(User $user): void
    {
        $group = ForumGroup::query()->create(['name' => 'G', 'slug' => 'g-'.Str::lower(Str::random(6)), 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'F',
            'slug' => 'f-'.Str::lower(Str::random(6)),
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'T',
            'slug' => 't-'.Str::lower(Str::random(6)),
            'reply_count' => 0,
        ]);

        ThreadSubscription::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id]);
    }

    public function test_lists_and_revokes_the_grant_for_subscribers_inactive_past_the_cutoff(): void
    {
        $inactive = $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);
        $this->subscribeToAThread($inactive);

        $preferences = app(Preferences::class);
        $this->assertFalse($preferences->isSuppressed($inactive));

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->expectsOutputToContain($inactive->username)
            ->assertSuccessful();

        $this->assertTrue($preferences->isSuppressed($inactive->fresh()));
        $this->assertNotNull(
            UserEntitlementGrant::query()
                ->where('user_id', $inactive->id)
                ->where('entitlement_key', config('filagora-zencart.notifications.email_entitlement'))
                ->value('revoked_at')
        );
    }

    public function test_a_subscriber_who_has_never_been_seen_since_registering_long_ago_counts_as_inactive(): void
    {
        // last_seen_at null alone must not be enough - a user created seconds
        // ago with no visit yet would also be null. It's the combination
        // with an old created_at that makes this genuinely inactive.
        $neverSeen = $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);
        $this->subscribeToAThread($neverSeen);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->assertSuccessful();

        $preferences = app(Preferences::class);
        $this->assertTrue($preferences->isSuppressed($neverSeen->fresh()));
    }

    public function test_a_freshly_registered_subscriber_with_no_visit_yet_is_not_treated_as_inactive(): void
    {
        $justRegistered = $this->emailableUser(['last_seen_at' => null]);
        $this->subscribeToAThread($justRegistered);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->expectsOutputToContain('No thread subscribers')
            ->assertSuccessful();

        $preferences = app(Preferences::class);
        $this->assertFalse($preferences->isSuppressed($justRegistered->fresh()));
    }

    public function test_leaves_recently_active_subscribers_alone(): void
    {
        $active = $this->emailableUser(['last_seen_at' => now()->subDays(5)]);
        $this->subscribeToAThread($active);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->assertSuccessful();

        $preferences = app(Preferences::class);
        $this->assertFalse($preferences->isSuppressed($active->fresh()));
    }

    public function test_ignores_inactive_users_with_no_thread_subscriptions(): void
    {
        $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->expectsOutputToContain('No thread subscribers')
            ->assertSuccessful();
    }

    public function test_dry_run_lists_without_revoking_the_grant(): void
    {
        $inactive = $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);
        $this->subscribeToAThread($inactive);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90, '--dry-run' => true])
            ->expectsOutputToContain($inactive->username)
            ->expectsOutputToContain('Would revoke')
            ->assertSuccessful();

        $preferences = app(Preferences::class);
        $this->assertFalse($preferences->isSuppressed($inactive->fresh()));
    }

    public function test_export_writes_the_full_list_as_csv_and_still_revokes_the_grant(): void
    {
        $inactive = $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);
        $this->subscribeToAThread($inactive);

        $path = tempnam(sys_get_temp_dir(), 'inactive-subscribers-').'.csv';

        try {
            $this->artisan('notifications:disable-email-inactive-subscribers', [
                '--days' => 90,
                '--export' => $path,
            ])->assertSuccessful();

            $this->assertFileExists($path);
            $rows = array_map('str_getcsv', file($path));
            $this->assertSame(['id', 'username', 'email', 'last_active', 'thread_subscriptions'], $rows[0]);
            $this->assertSame((string) $inactive->id, $rows[1][0]);
            $this->assertSame($inactive->username, $rows[1][1]);

            $preferences = app(Preferences::class);
            $this->assertTrue($preferences->isSuppressed($inactive->fresh()));
        } finally {
            @unlink($path);
        }
    }

    public function test_does_not_touch_individual_category_preferences(): void
    {
        // The command only revokes the blanket entitlement; per-category
        // email/push/bell preference rows are a separate mechanism it never
        // writes to.
        $inactive = $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);
        $this->subscribeToAThread($inactive);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseCount('user_notification_preferences', 0);
        $this->assertSame(0, NotificationPreference::query()->where('user_id', $inactive->id)->count());
    }

    public function test_running_twice_does_not_error_or_double_revoke(): void
    {
        $inactive = $this->emailableUser(['last_seen_at' => null, 'created_at' => now()->subDays(120)]);
        $this->subscribeToAThread($inactive);

        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])->assertSuccessful();
        $firstRevokedAt = UserEntitlementGrant::query()
            ->where('user_id', $inactive->id)
            ->where('entitlement_key', config('filagora-zencart.notifications.email_entitlement'))
            ->value('revoked_at');

        // Once revoked, the grant no longer matches whereNull('revoked_at'),
        // so a second run should leave it untouched rather than erroring or
        // re-stamping a new revocation time.
        $this->artisan('notifications:disable-email-inactive-subscribers', ['--days' => 90])
            ->expectsOutputToContain('No thread subscribers')
            ->assertSuccessful();

        $secondRevokedAt = UserEntitlementGrant::query()
            ->where('user_id', $inactive->id)
            ->where('entitlement_key', config('filagora-zencart.notifications.email_entitlement'))
            ->value('revoked_at');

        $this->assertSame($firstRevokedAt->toDateTimeString(), $secondRevokedAt->toDateTimeString());
    }
}
