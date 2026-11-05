<?php

namespace Tests\Feature;

use App\Http\SubsystemRoutes;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SubsystemMaintenanceModeTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.subsystem_maintenance.allow_during_tests', true);
        config()->set('app.frontend_maintenance.allowed_ips', []);
        config()->set('app.subsystem_maintenance.forums.enabled', false);
        config()->set('app.subsystem_maintenance.plugins.enabled', false);
    }

    public function test_forum_routes_return_a_scoped_maintenance_response(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);
        config()->set('app.subsystem_maintenance.forums.message', 'The forum database is being upgraded.');
        config()->set('app.subsystem_maintenance.forums.retry_after', 600);

        $response = $this->get('/forums');

        $response->assertStatus(503);
        $response->assertSee('The forums are temporarily unavailable.');
        $response->assertSee('The forum database is being upgraded.');
        $response->assertHeader('Retry-After', '600');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function test_plugin_routes_return_a_scoped_maintenance_response(): void
    {
        config()->set('app.subsystem_maintenance.plugins.enabled', true);

        $this->get('/plugins')
            ->assertStatus(503)
            ->assertSee('The plugin library is temporarily unavailable.');
    }

    public function test_subsystems_can_be_disabled_independently(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);

        $this->get('/plugins')->assertOk();
        $this->get('/')->assertOk();

        config()->set('app.subsystem_maintenance.forums.enabled', false);
        config()->set('app.subsystem_maintenance.plugins.enabled', true);

        $this->get('/forums')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_search_stays_available_and_reports_omitted_subsystems(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);
        config()->set('app.subsystem_maintenance.plugins.enabled', true);

        $this->get('/search?q=maintenance')
            ->assertOk()
            ->assertSee('Results from forums and plugins are temporarily unavailable');
    }

    public function test_plugin_api_returns_json_maintenance_response(): void
    {
        config()->set('app.subsystem_maintenance.plugins.enabled', true);
        config()->set('app.subsystem_maintenance.plugins.retry_after', 120);

        $response = $this->getJson('/api/v1/plugins')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '120')
            ->assertExactJson([
                'message' => __('maintenance.subsystems.plugins'),
                'maintenance' => true,
                'subsystem' => 'plugins',
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_maintenance_middleware_runs_before_route_model_binding(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);
        config()->set('app.subsystem_maintenance.plugins.enabled', true);

        $this->get('/threads/999999')->assertStatus(503);
        $this->get('/plugins/not-a-real-plugin')->assertStatus(503);
    }

    public function test_legacy_routes_are_scoped_to_their_subsystem(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);
        config()->set('app.subsystem_maintenance.plugins.enabled', true);

        $this->get('/showthread.php?t=123')->assertStatus(503);
        $this->get('/downloads.php?id=123')->assertStatus(503);
    }

    public function test_forum_mutations_and_cross_subsystem_plugin_submission_are_blocked(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);

        $this->post('/forums/999999/threads')->assertStatus(503);
        $this->get('/plugins/submit')->assertStatus(503);
    }

    public function test_global_frontend_allowed_ip_can_bypass_subsystem_maintenance(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);
        config()->set('app.frontend_maintenance.allowed_ips', ['203.0.113.10']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/forums');

        $response->assertOk();
    }

    public function test_staff_user_can_bypass_subsystem_maintenance(): void
    {
        config()->set('app.subsystem_maintenance.forums.enabled', true);
        $staff = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $staff->id,
            'entitlement_key' => 'operator.dashboard.access',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($staff)->get('/forums')->assertOk();
    }

    public function test_every_listed_address_is_a_real_route_and_carries_the_switch(): void
    {
        foreach (SubsystemRoutes::URIS as $subsystem => $uris) {
            foreach ($uris as $uri) {
                $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => $route->uri() === $uri);

                $this->assertNotEmpty($routes, "No route at {$uri}");

                foreach ($routes as $route) {
                    $this->assertTrue(
                        collect($route->middleware())->contains(fn ($middleware): bool => str_ends_with((string) $middleware, 'EnsureSubsystemIsAvailable:'.$subsystem)),
                        "{$uri} does not answer to the {$subsystem} switch",
                    );
                }
            }
        }
    }

    public function test_the_version_check_answers_to_the_plugin_switch(): void
    {
        config()->set('app.subsystem_maintenance.plugins.enabled', true);

        $this->get('/plugincheck/123')->assertStatus(503);
    }

    public function test_subsystem_maintenance_is_ignored_in_tests_unless_explicitly_enabled(): void
    {
        config()->set('app.subsystem_maintenance.allow_during_tests', false);
        config()->set('app.subsystem_maintenance.forums.enabled', true);

        $this->get('/forums')->assertOk();
    }
}
