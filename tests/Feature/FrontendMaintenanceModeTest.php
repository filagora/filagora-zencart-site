<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class FrontendMaintenanceModeTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    protected function setUp(): void
    {
        parent::setUp();

        TrustProxies::flushState();
        config()->set('app.frontend_maintenance.allow_during_tests', true);
        config()->set('app.frontend_maintenance.allowed_ips', []);

        Route::middleware('web')->get('/maintenance-test-json', fn () => response()->json(['ok' => true]));
        Route::middleware('web')->post('/livewire-test/update', fn () => response()->json(['ok' => true]));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_public_frontend_route_returns_maintenance_page_when_enabled(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.message', 'We are upgrading the support site.');
        config()->set('app.frontend_maintenance.retry_after', 900);

        $group = ForumGroup::query()->create([
            'name' => 'Maintenance Group',
            'slug' => 'maintenance-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Maintenance Forum',
            'slug' => 'maintenance-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('We are upgrading the support site.');
        $response->assertSee('Suggested retry window: 15 minutes.');
        $response->assertHeader('Retry-After', '900');
    }

    public function test_maintenance_page_shows_when_the_window_started_in_utc(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.retry_after', 3600);
        config()->set('app.frontend_maintenance.started_at', '2026-08-24T14:00:00+02:00');

        $response = $this->get('/');

        $response->assertStatus(503);
        // The offset above is UTC+2, so the displayed time must be converted.
        $response->assertSee('Maintenance began at 24 Aug 2026, 12:00 UTC.');
    }

    public function test_maintenance_page_omits_started_at_line_when_not_configured(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.started_at', null);

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertDontSee('Maintenance began at');
    }

    public function test_maintenance_page_omits_started_at_line_when_value_is_unparsable(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.started_at', 'not-a-real-timestamp');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertDontSee('Maintenance began at');
    }

    public function test_json_maintenance_response_includes_started_at_when_configured(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.started_at', '2026-08-24T14:00:00Z');

        $response = $this->getJson('/maintenance-test-json');

        $response->assertStatus(503);
        $response->assertJson(['started_at' => '2026-08-24T14:00:00+00:00']);
    }

    public function test_public_frontend_route_uses_translated_message_when_override_is_blank(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.message', null);
        config()->set('app.frontend_maintenance.message_key', 'maintenance.message');
        config()->set('app.frontend_maintenance.retry_after', 120);

        app('translator')->addLines([
            'maintenance.page_title' => ':app | Entretien',
            'maintenance.eyebrow' => 'Maintenance planifiée',
            'maintenance.title' => ':app est temporairement indisponible.',
            'maintenance.message' => 'Le site est temporairement indisponible pendant la maintenance.',
            'maintenance.retry_after' => 'Veuillez réessayer plus tard. Fenêtre suggérée : :duration.',
        ], 'fr');
        app()->setLocale('fr');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Maintenance planifiée');
        $response->assertSee('Le site est temporairement indisponible pendant la maintenance.');
        $response->assertSee('Veuillez réessayer plus tard. Fenêtre suggérée : 2 minutes.');
    }

    public function test_dashboard_login_path_bypasses_maintenance_mode(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);

        $response = $this->get('/dashboard/login');

        $this->assertNotSame(503, $response->getStatusCode());
    }

    public function test_livewire_request_from_dashboard_path_bypasses_maintenance_mode(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);

        $response = $this->withHeader('Referer', 'http://localhost/dashboard/login')
            ->postJson('/livewire-test/update');

        $response->assertOk();
        $response->assertExactJson(['ok' => true]);
    }

    public function test_staff_user_can_bypass_maintenance_mode(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);

        $moderator = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $moderator->id,
            'entitlement_key' => 'operator.dashboard.access',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $group = ForumGroup::query()->create([
            'name' => 'Visible Group',
            'slug' => 'visible-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Visible Forum',
            'slug' => 'visible-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($moderator)->get('/');

        $response->assertOk();
        $response->assertDontSee('We are upgrading the support site.');
    }

    public function test_allowed_ip_can_bypass_maintenance_mode(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.allowed_ips', ['203.0.113.10']);

        $group = ForumGroup::query()->create([
            'name' => 'Allowed IP Group',
            'slug' => 'allowed-ip-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Allowed IP Forum',
            'slug' => 'allowed-ip-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
        ])->get('/');

        $response->assertOk();
        $response->assertDontSee('We are upgrading the support site.');
    }

    public function test_allowed_ip_cidr_can_bypass_maintenance_mode(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.allowed_ips', ['198.51.100.0/24']);

        $group = ForumGroup::query()->create([
            'name' => 'Allowed CIDR Group',
            'slug' => 'allowed-cidr-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Allowed CIDR Forum',
            'slug' => 'allowed-cidr-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.42',
        ])->get('/');

        $response->assertOk();
        $response->assertDontSee('We are upgrading the support site.');
    }

    public function test_forwarded_client_ip_can_bypass_maintenance_mode_when_proxy_is_trusted(): void
    {
        TrustProxies::at('REMOTE_ADDR');

        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.allowed_ips', ['203.0.113.10']);

        $group = ForumGroup::query()->create([
            'name' => 'Forwarded IP Group',
            'slug' => 'forwarded-ip-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Forwarded IP Forum',
            'slug' => 'forwarded-ip-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        ])->get('/');

        $response->assertOk();
        $response->assertDontSee('We are upgrading the support site.');
    }

    public function test_forwarded_client_ip_can_bypass_maintenance_mode_when_proxy_is_configured(): void
    {
        config()->set('trustedproxy.proxies', '*');
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.allowed_ips', ['203.0.113.10']);

        $group = ForumGroup::query()->create([
            'name' => 'Configured Proxy Group',
            'slug' => 'configured-proxy-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Configured Proxy Forum',
            'slug' => 'configured-proxy-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.20',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        ])->get('/');

        $response->assertOk();
        $response->assertDontSee('We are upgrading the support site.');
    }

    public function test_json_request_receives_json_maintenance_response(): void
    {
        config()->set('app.frontend_maintenance.enabled', true);
        config()->set('app.frontend_maintenance.message', 'JSON maintenance response.');

        $response = $this->getJson('/maintenance-test-json');

        $response->assertStatus(503);
        $response->assertJson([
            'message' => 'JSON maintenance response.',
            'maintenance' => true,
        ]);
        $response->assertHeader('Retry-After', '3600');
    }
}
