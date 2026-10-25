<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\AnnouncementAudience;
use Filagora\Forums\Enums\AnnouncementStatus;
use Filagora\Forums\Models\SiteNotification;
use Filagora\Forums\Models\UserSiteNotificationDismissal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SiteHeaderNotificationBannerRenderingTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_everyone_notification_renders_for_guest(): void
    {
        $this->notification(['title' => 'Everyone banner', 'body' => 'Everyone banner body', 'audience' => AnnouncementAudience::EVERYONE]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Everyone banner body');
    }

    public function test_title_is_not_rendered_on_the_frontend(): void
    {
        $this->notification(['title' => 'Internal admin label only', 'body' => 'Visible banner body']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Visible banner body')
            ->assertDontSee('Internal admin label only');
    }

    public function test_guests_only_notification_does_not_render_for_authenticated_user(): void
    {
        $this->notification(['title' => 'Guests only banner', 'body' => 'Guests only banner body', 'audience' => AnnouncementAudience::GUESTS_ONLY]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Guests only banner body');
    }

    public function test_authenticated_only_notification_does_not_render_for_guest(): void
    {
        $this->notification(['title' => 'Members only banner', 'body' => 'Members only banner body', 'audience' => AnnouncementAudience::AUTHENTICATED_ONLY]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Members only banner body');
    }

    public function test_dismissed_notification_renders_hidden_for_that_user(): void
    {
        $notification = $this->notification(['title' => 'Already dismissed banner', 'body' => 'Already dismissed banner body']);
        $user = User::factory()->create();

        UserSiteNotificationDismissal::query()->create([
            'user_id' => $user->id,
            'site_notification_id' => $notification->id,
            'dismissed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Already dismissed banner body');
        $response->assertSee('data-site-notification-id="'.$notification->id.'"', false);
        $response->assertSee('hidden', false);
    }

    public function test_markdown_in_body_renders_as_html(): void
    {
        $this->notification(['body' => 'Please **read this** carefully.']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<strong>read this</strong>', false);
        $response->assertDontSee('**read this**');
    }

    public function test_notification_markdown_escapes_raw_html_and_rejects_unsafe_links(): void
    {
        $this->notification(['body' => '<script>alert(1)</script> [unsafe](javascript:alert(2))']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>', false);
        $response->assertDontSee('href="javascript:', false);
    }

    public function test_dismissible_notification_renders_dismiss_button(): void
    {
        $this->notification(['body' => 'Dismissible banner body', 'is_dismissible' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-notification-dismiss', false);
    }

    public function test_non_dismissible_notification_does_not_render_dismiss_button(): void
    {
        $this->notification(['body' => 'Non-dismissible banner body', 'is_dismissible' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Non-dismissible banner body')
            ->assertDontSee('data-site-notification-dismiss', false);
    }

    public function test_persistent_notification_renders_persistent_flag_as_one(): void
    {
        $this->notification(['body' => 'Persistent banner body', 'is_persistent' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-notification-persistent="1"', false);
    }

    public function test_non_persistent_notification_renders_persistent_flag_as_zero(): void
    {
        $this->notification(['body' => 'One-time banner body', 'is_persistent' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-notification-persistent="0"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function notification(array $overrides = []): SiteNotification
    {
        return SiteNotification::query()->create(array_merge([
            'title' => 'Test notification',
            'body' => 'Test body',
            'status' => AnnouncementStatus::PUBLISHED,
            'audience' => AnnouncementAudience::EVERYONE,
        ], $overrides));
    }
}
