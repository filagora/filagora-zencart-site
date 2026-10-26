<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SearchIconToggleTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_persistent_search_strip_renders_for_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('border-t border-[var(--color-site-line)] md:block', false)
            ->assertDontSee('data-search-toggle-trigger', false)
            ->assertDontSee('data-search-toggle-panel', false);
    }

    public function test_users_saved_preference_applies(): void
    {
        $user = User::factory()->create(['search_icon_enabled' => true]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('data-search-toggle-trigger', false)
            ->assertSee('id="desktop-search-panel"', false)
            ->assertDontSee('border-t border-[var(--color-site-line)] md:block', false);
    }

    public function test_profile_settings_shows_the_control(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile?tab=settings')
            ->assertOk()
            ->assertSee('Header Search Icon');
    }

    public function test_profile_settings_updates_the_preference(): void
    {
        $user = User::factory()->create(['search_icon_enabled' => false]);

        $this->actingAs($user)
            ->post(route('profile.settings.update'), [
                'thread_emails_enabled' => '1',
                'thread_subscription_email_frequency' => 'instant',
                'plugin_release_updates' => '1',
                'allow_private_messages' => '1',
                'search_icon_enabled' => '1',
                'show_archived_plugin_releases' => '0',
                'prefers_rich_editor' => '1',
                'time_format' => '12h',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'settings']));

        $this->assertTrue($user->refresh()->search_icon_enabled);
    }

    public function test_profile_settings_requires_the_search_icon_field(): void
    {
        $user = User::factory()->create(['search_icon_enabled' => false]);

        $this->actingAs($user)
            ->post(route('profile.settings.update'), [
                'thread_emails_enabled' => '1',
                'thread_subscription_email_frequency' => 'instant',
                'plugin_release_updates' => '1',
                'allow_private_messages' => '1',
                'time_format' => '12h',
            ])
            ->assertSessionHasErrors('search_icon_enabled', null, 'settings');

        $this->assertFalse($user->refresh()->search_icon_enabled);
    }

    public function test_query_param_overrides_the_toggle_for_a_guest_session(): void
    {
        $this->get('/?search_icon_toggle=1')
            ->assertOk()
            ->assertSee('data-search-toggle-trigger', false)
            ->assertDontSee('border-t border-[var(--color-site-line)] md:block', false);

        // The override persists in the session on the next request, even
        // without the query param present again.
        $this->get('/')
            ->assertOk()
            ->assertSee('data-search-toggle-trigger', false);

        // Explicitly turning it back off also sticks.
        $this->get('/?search_icon_toggle=0')
            ->assertOk()
            ->assertDontSee('data-search-toggle-trigger', false)
            ->assertSee('border-t border-[var(--color-site-line)] md:block', false);
    }

    public function test_query_param_override_takes_precedence_over_the_users_saved_preference(): void
    {
        $user = User::factory()->create(['search_icon_enabled' => true]);

        $this->actingAs($user)
            ->get('/?search_icon_toggle=0')
            ->assertOk()
            ->assertDontSee('data-search-toggle-trigger', false)
            ->assertSee('border-t border-[var(--color-site-line)] md:block', false);
    }
}
