<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAuthModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_hamburger_mobile_navigation_trigger(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Toggle site navigation"', false)
            ->assertSee('Open navigation menu', false)
            ->assertDontSee('>Menu<', false);
    }

    public function test_home_page_renders_the_native_login_and_register_panels_and_no_iframe(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="home-auth-modal-native-login"', false)
            ->assertSee('id="home-auth-modal-native-register"', false)
            ->assertDontSee('home-auth-modal-frame', false);
    }

    public function test_home_page_renders_the_reusable_form_submit_lock_overlay(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-form-submit-lock-overlay', false)
            ->assertSee('data-form-submit-lock-message', false);
    }

    public function test_dashboard_register_page_no_longer_renders_the_auth_modal_bridge_script(): void
    {
        $this->get('/dashboard/register')
            ->assertOk()
            ->assertDontSee('filament-auth-modal-state', false)
            ->assertDontSee('__filamentAuthModalBridgeInit', false);
    }

    public function test_site_header_script_contains_no_iframe_bridge_machinery(): void
    {
        $script = file_get_contents(resource_path('js/modules/site-header.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString('authFrame', $script);
        $this->assertStringNotContainsString('handleAuthFrameMessage', $script);
        $this->assertStringNotContainsString('navigateAwayFromModal', $script);
        $this->assertStringNotContainsString('filament-auth-modal-state', $script);
    }
}
