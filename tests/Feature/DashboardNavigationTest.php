<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\Concerns\SeedsSitePages;
use Tests\Support\CanonicalOperatorEntitlements;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_every_menu_entry_has_a_registered_group_a_unique_sort_and_a_translated_label(): void
    {
        $panel = Filament::getPanel('dashboard');
        $registry = app(NavigationGroupRegistry::class);
        $labels = array_map(fn (string $key): string => $registry->label($key), $registry->keys());
        $seenSorts = [];

        foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
            if ($class === Dashboard::class || ! $this->registersNavigation($class)) {
                continue;
            }

            $group = $class::getNavigationGroup();
            $this->assertIsString($group, "{$class} must name a group from the navigation group registry.");
            $this->assertContains($group, $labels, "{$class} uses a group that is not registered ({$group}).");

            $sortKey = $group.':'.$class::getNavigationSort();
            $this->assertNotNull($class::getNavigationSort(), "{$class} needs an explicit navigation sort.");
            $this->assertArrayNotHasKey($sortKey, $seenSorts, sprintf('%s shares its sort with %s.', $class, $seenSorts[$sortKey] ?? ''));
            $seenSorts[$sortKey] = $class;

            $this->assertStringNotContainsString('navigation_items.', $class::getNavigationLabel(), "{$class} has an untranslated navigation label.");
        }
    }

    public function test_super_admin_sees_every_group_in_order(): void
    {
        $admin = User::factory()->create();
        $this->makeForumModerator($admin);
        $this->grantEntitlements($admin, CanonicalOperatorEntitlements::allEntitlements());

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeTextInOrder(array_map(
                static fn (string $key): string => app(NavigationGroupRegistry::class)->label($key),
                array_slice(app(NavigationGroupRegistry::class)->keys(), 0, 3),
            ));
    }

    public function test_forum_moderator_only_sees_permitted_entries(): void
    {
        $moderator = User::factory()->create();
        $this->makeForumModerator($moderator);
        $this->grantCanonicalOperatorEntitlements($moderator, 'forum_moderator');

        $this->actingAs($moderator)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText(__('filagora-forums::messages.queue.navigation_label'))
            ->assertDontSeeText(__('dashboard.navigation_items.system_settings'))
            ->assertDontSeeText(__('dashboard.navigation_items.fail2ban'))
            ->assertDontSeeText(__('dashboard.navigation_items.email_log'));
    }

    /**
     * @param  class-string  $class
     */
    private function registersNavigation(string $class): bool
    {
        $reflection = new ReflectionClass($class);

        return ! $reflection->hasProperty('shouldRegisterNavigation')
            || $reflection->getStaticPropertyValue('shouldRegisterNavigation') !== false;
    }
}
