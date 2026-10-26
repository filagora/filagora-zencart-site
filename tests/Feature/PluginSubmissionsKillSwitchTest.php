<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginSubmissionsKillSwitchTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_submission_routes_return_503_when_switched_off(): void
    {
        config(['plugins.submissions_enabled' => false]);
        $user = User::factory()->create();
        $plugin = $this->activePlugin();

        $this->actingAs($user)->get(route('plugins.submit'))->assertStatus(503);
        $this->actingAs($user)->post(route('plugins.store'))->assertStatus(503);
        $this->actingAs($user)->get(route('plugins.release.new', $plugin))->assertStatus(503);
        $this->actingAs($user)->post(route('plugins.release.store', $plugin))->assertStatus(503);
    }

    public function test_submission_routes_are_not_blocked_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame(503, $this->actingAs($user)->get(route('plugins.submit'))->getStatusCode());
    }

    public function test_browsing_stays_available_when_switched_off(): void
    {
        config(['plugins.submissions_enabled' => false]);

        $this->get(route('plugins.index'))->assertOk();
    }

    private function activePlugin(): Plugin
    {
        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);

        return Plugin::query()->create(['name' => 'Kill Switch Plugin', 'slug' => 'kill-switch-plugin', 'plugin_type_id' => $type->id, 'status' => PluginStatus::ACTIVE]);
    }
}
