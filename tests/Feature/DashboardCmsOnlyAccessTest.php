<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserOperatorAccessAssignment;
use Croustibat\FilamentJobsMonitor\Authorization;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class DashboardCmsOnlyAccessTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_dashboard_access_with_cms_management_and_frontend_moderation_exposes_only_cms_screens(): void
    {
        $this->actingAs($this->userWith('operator.dashboard.access', 'operator.cms.manage', 'operator.forum.moderate_frontend'));
        $panel = Filament::getPanel('dashboard');
        Filament::setCurrentPanel($panel);

        $resources = collect($panel->getResources())
            ->filter(fn (string $resource): bool => $resource::canAccess())
            ->map(fn (string $resource): string => class_basename($resource))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['CmsBlockResource', 'CmsMediaAssetResource', 'CmsNavigationResource', 'CmsPageResource', 'CmsPreviewShareResource', 'CmsTagResource'],
            array_values(array_intersect($resources, ['CmsBlockResource', 'CmsMediaAssetResource', 'CmsNavigationResource', 'CmsPageResource', 'CmsPreviewShareResource', 'CmsTagResource'])),
            'the CMS screens open',
        );

        $this->assertSame([], array_values(array_diff($resources, [
            'CmsBlockResource', 'CmsMediaAssetResource', 'CmsNavigationResource', 'CmsPageResource', 'CmsPreviewShareResource', 'CmsTagResource',
        ])), 'nothing but the CMS screens opens');

        $widgets = collect($panel->getWidgets())
            ->filter(fn (string $widget): bool => $widget::canView())
            ->map(fn (string $widget): string => class_basename($widget))
            ->all();

        $this->assertSame(['FrontendLinksWidget'], array_values($widgets));
    }

    public function test_the_queue_monitor_needs_its_entitlement(): void
    {
        $panel = Filament::getPanel('dashboard');
        Filament::setCurrentPanel($panel);

        $this->actingAs($this->userWith('operator.dashboard.access'));
        $this->assertFalse(Authorization::allows(Authorization::VIEW_ANY));

        $this->actingAs($this->userWith('operator.dashboard.access', 'operator.queue_monitors.view'));
        $this->assertTrue(Authorization::allows(Authorization::VIEW_ANY));
    }

    public function test_frontend_only_moderation_is_assignable_by_standard_staff(): void
    {
        $actor = $this->userWith('operator.users.update');

        $this->assertArrayHasKey('operator.forum.moderate_frontend', UserOperatorAccessAssignment::optionsFor($actor));
    }

    private function userWith(string ...$entitlements): User
    {
        $user = User::factory()->create();

        foreach ($entitlements as $entitlement) {
            UserEntitlementGrant::query()->create([
                'user_id' => $user->id,
                'entitlement_key' => $entitlement,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }

        return $user;
    }
}
