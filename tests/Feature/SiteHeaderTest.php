<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SiteHeaderTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_header_row_stays_single_line_on_narrow_screens_so_the_fixed_menu_panels_clear_it(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('max-sm:flex-nowrap', false)
            ->assertSee('id="mobile-nav-panel" class="ui-shell-panel fixed right-4 top-[88px]', false);
    }

    public function test_home_page_title_is_short_and_not_the_long_tagline(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Zen Cart Support', false)
            ->assertDontSee('Putting the dream of your own business', false);
    }

    public function test_frontend_only_moderators_get_the_show_moderation_controls_toggle(): void
    {
        $user = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'operator.forum.moderate_frontend',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Show Moderation Controls: On');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Show Moderation Controls');
    }
}
