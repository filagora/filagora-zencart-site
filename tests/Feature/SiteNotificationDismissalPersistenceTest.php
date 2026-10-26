<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\AnnouncementStatus as SiteNotificationStatus;
use Filagora\Forums\Models\SiteNotification;
use Filagora\Forums\Models\UserSiteNotificationDismissal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SiteNotificationDismissalPersistenceTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_authenticated_user_can_dismiss_a_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification();

        $response = $this->actingAs($user)->postJson('/profile/site-notification-dismissals', [
            'notification_id' => $notification->id,
        ]);

        $response->assertOk()->assertJson(['dismissed' => true]);

        $this->assertDatabaseHas('user_site_notification_dismissals', [
            'user_id' => $user->id,
            'site_notification_id' => $notification->id,
        ]);
    }

    public function test_dismissing_the_same_notification_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification();

        $this->actingAs($user)->postJson('/profile/site-notification-dismissals', [
            'notification_id' => $notification->id,
        ])->assertOk();

        $this->actingAs($user)->postJson('/profile/site-notification-dismissals', [
            'notification_id' => $notification->id,
        ])->assertOk();

        $this->assertSame(1, UserSiteNotificationDismissal::query()
            ->where('user_id', $user->id)
            ->where('site_notification_id', $notification->id)
            ->count());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $notification = $this->notification();

        $this->postJson('/profile/site-notification-dismissals', [
            'notification_id' => $notification->id,
        ])->assertStatus(401);
    }

    public function test_non_dismissible_notification_cannot_be_dismissed(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification(['is_dismissible' => false]);

        $response = $this->actingAs($user)->postJson('/profile/site-notification-dismissals', [
            'notification_id' => $notification->id,
        ]);

        $response->assertOk()->assertJson(['dismissed' => false]);

        $this->assertDatabaseMissing('user_site_notification_dismissals', [
            'user_id' => $user->id,
            'site_notification_id' => $notification->id,
        ]);
    }

    public function test_authenticated_user_viewing_a_non_persistent_notification_marks_it_recorded(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification(['is_persistent' => false]);

        $response = $this->actingAs($user)->postJson('/profile/site-notification-views', [
            'notification_id' => $notification->id,
        ]);

        $response->assertOk()->assertJson(['recorded' => true]);

        $this->assertDatabaseHas('user_site_notification_dismissals', [
            'user_id' => $user->id,
            'site_notification_id' => $notification->id,
        ]);
    }

    public function test_persistent_notification_cannot_be_recorded_as_first_view(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification(['is_persistent' => true]);

        $response = $this->actingAs($user)->postJson('/profile/site-notification-views', [
            'notification_id' => $notification->id,
        ]);

        $response->assertOk()->assertJson(['recorded' => false]);

        $this->assertDatabaseMissing('user_site_notification_dismissals', [
            'user_id' => $user->id,
            'site_notification_id' => $notification->id,
        ]);
    }

    public function test_unauthenticated_view_request_is_rejected(): void
    {
        $notification = $this->notification(['is_persistent' => false]);

        $this->postJson('/profile/site-notification-views', [
            'notification_id' => $notification->id,
        ])->assertStatus(401);
    }

    public function test_a_non_dismissible_non_persistent_notification_can_still_be_recorded_as_viewed(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification(['is_persistent' => false, 'is_dismissible' => false]);

        $response = $this->actingAs($user)->postJson('/profile/site-notification-views', [
            'notification_id' => $notification->id,
        ]);

        $response->assertOk()->assertJson(['recorded' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function notification(array $overrides = []): SiteNotification
    {
        return SiteNotification::query()->create(array_merge([
            'title' => 'Dismissible notification',
            'body' => 'Body',
            'status' => SiteNotificationStatus::PUBLISHED,
        ], $overrides));
    }
}
