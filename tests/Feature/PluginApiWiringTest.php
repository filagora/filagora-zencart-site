<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * The plugins package's JSON API, as the site wires it: signed in by an account API key, with the
 * key's ability and its owner's entitlement both required. The endpoints themselves are tested in
 * the package.
 */
class PluginApiWiringTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private function livePlugin(): Plugin
    {
        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);
        $plugin = Plugin::query()->create(['name' => 'Admin Tools', 'slug' => 'admin-tools', 'plugin_type_id' => $type->id, 'status' => PluginStatus::ACTIVE->value]);
        PluginRelease::query()->create(['plugin_id' => $plugin->id, 'version' => '1.2.0', 'internal_version' => 1, 'zip_path' => 'plugins/a.zip', 'zip_filename' => 'a.zip', 'encapsulated' => 'unknown', 'status' => PluginStatus::ACTIVE->value]);

        return $plugin;
    }

    public function test_a_key_with_the_ability_and_the_entitlement_lists_plugins(): void
    {
        $this->livePlugin();
        $user = User::factory()->create();
        $this->grantEntitlement($user, 'api.plugins.read');
        $token = $user->createToken('plugin-api', ['plugins:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/plugins')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'admin-tools']);
    }

    public function test_a_key_without_the_ability_is_refused(): void
    {
        $this->livePlugin();
        $user = User::factory()->create();
        $this->grantEntitlement($user, 'api.plugins.read');
        $token = $user->createToken('plugin-api', ['user:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/plugins')->assertForbidden();
    }

    public function test_a_key_whose_owner_lacks_the_entitlement_is_refused(): void
    {
        $this->livePlugin();
        $user = User::factory()->create();
        $token = $user->createToken('plugin-api', ['plugins:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/plugins')->assertForbidden();
    }

    public function test_an_unknown_key_is_unauthenticated(): void
    {
        $this->withToken('999|not-a-real-key')->getJson('/api/v1/plugins')->assertUnauthorized();
    }

    public function test_releases_that_are_held_for_a_scan_are_not_listed(): void
    {
        $plugin = $this->livePlugin();
        $plugin->releases()->first()->forceFill(['status' => PluginStatus::QUARANTINED, 'quarantined_at' => now()])->save();
        $user = User::factory()->create();
        $this->grantEntitlement($user, 'api.plugins.read');
        $token = $user->createToken('plugin-api', ['plugins:read'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/plugins/admin-tools/releases');

        $this->assertNotContains('1.2.0', collect($response->json('data') ?? [])->pluck('version')->all());
    }
}
