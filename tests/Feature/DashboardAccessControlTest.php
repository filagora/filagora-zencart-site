<?php

namespace Tests\Feature;

use App\Filament\Widgets\EntitlementRequestQueueStats;
use App\Filament\Widgets\FrontendLinksWidget;
use App\Models\User;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Filament\Widgets\ModerationQueueStats as ForumModerationQueueStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class DashboardAccessControlTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_forum_user_is_redirected_from_queue_monitor_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/queue-monitors')
            ->assertRedirect(route('home'));
    }

    public function test_forum_user_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('home'));
    }

    public function test_staff_user_can_access_dashboard_route(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertTrue(in_array($response->status(), [200, 302], true));
        $this->assertNotSame(route('home'), $response->headers->get('Location'));
    }

    public function test_staff_dashboard_includes_queue_monitor_navigation_link(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.queue_monitors.view');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('filament.dashboard.resources.queue-monitors.index'), false);
    }

    public function test_frontend_links_widget_links_to_home_forum_and_plugins(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($user);
        Livewire::test(FrontendLinksWidget::class)
            ->assertSee('Home')
            ->assertSee('Forum')
            ->assertSee('Plugins')
            ->assertSee(route('home'), false)
            ->assertSee(route('forums.index'), false)
            ->assertSee(route('plugins.index'), false);
    }

    public function test_entitlement_request_widget_renders_for_staff_when_pending_requests_exist(): void
    {
        $staffUser = User::factory()->create(['email_verified_at' => now()]);
        $requester = User::factory()->create(['email_verified_at' => now()]);
        $this->grant($staffUser, 'operator.dashboard.access');
        $this->grant($staffUser, 'operator.entitlements.view');

        EntitlementRequest::query()->create([
            'user_id' => $requester->id,
            'entitlement_key' => 'downloads.support',
            'status' => EntitlementRequestStatus::PENDING->value,
            'request_reason' => 'Need support package access.',
            'requested_at' => now()->subHour(),
        ]);

        $this->actingAs($staffUser);

        $this->assertTrue(EntitlementRequestQueueStats::canView());

        Livewire::test(EntitlementRequestQueueStats::class)
            ->assertSee(__('dashboard.entitlement_requests.title'))
            ->assertSee(route('filament.dashboard.resources.entitlement-requests.index'), false);
    }

    public function test_entitlement_request_widget_is_hidden_when_no_pending_requests_exist(): void
    {
        $staffUser = User::factory()->create(['email_verified_at' => now()]);
        $requester = User::factory()->create(['email_verified_at' => now()]);
        $this->grant($staffUser, 'operator.dashboard.access');
        $this->grant($staffUser, 'operator.entitlements.view');

        EntitlementRequest::query()->create([
            'user_id' => $requester->id,
            'entitlement_key' => 'downloads.support',
            'status' => EntitlementRequestStatus::APPROVED->value,
            'request_reason' => 'Need support package access.',
            'requested_at' => now()->subDay(),
            'reviewed_at' => now()->subHour(),
            'reviewed_by_id' => $staffUser->id,
        ]);

        $this->actingAs($staffUser);

        $this->assertFalse(EntitlementRequestQueueStats::canView());
    }

    public function test_forum_moderation_queue_widget_renders_translated_copy_for_staff(): void
    {
        $staffUser = User::factory()->create(['email_verified_at' => now()]);
        $this->grant($staffUser, 'operator.dashboard.access');
        $this->grant($staffUser, 'operator.forum.moderate');

        $this->actingAs($staffUser);

        $this->assertTrue(ForumModerationQueueStats::canView());

        Livewire::test(ForumModerationQueueStats::class)
            ->assertSee(__('filagora-forums::messages.widget.waiting'))
            ->assertSee(__('filagora-forums::messages.widget.waiting_description', ['threads' => 0, 'posts' => 0]));
    }

    public function test_staff_user_can_access_queue_monitor_routes(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.queue_monitors.view');

        $indexResponse = $this->actingAs($user)->get('/dashboard/queue-monitors');

        $this->assertTrue(in_array($indexResponse->status(), [200, 302], true));
        $this->assertNotSame(route('home'), $indexResponse->headers->get('Location'));
    }

    public function test_unverified_staff_user_is_redirected_to_verification_prompt(): void
    {
        $user = User::factory()->unverified()->create();
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('filament.dashboard.auth.email-verification.prompt'));
    }

    public function test_unverified_staff_user_is_redirected_from_queue_monitor_routes_to_verification_prompt(): void
    {
        $user = User::factory()->unverified()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.queue_monitors.view');

        $this->actingAs($user)
            ->get('/dashboard/queue-monitors')
            ->assertRedirect(route('filament.dashboard.auth.email-verification.prompt'));
    }

    private function grant(User $user, string $entitlement): void
    {
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => $entitlement,
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
    }
}
