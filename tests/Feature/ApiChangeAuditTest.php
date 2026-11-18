<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * A change made through the plugin API is written to the api-audit log with the id of the key it came
 * with, so an abused or leaked key can be traced. The plugins package fires the event; the site keeps
 * the record.
 */
class ApiChangeAuditTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private function livePlugin(): Plugin
    {
        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);
        $plugin = Plugin::query()->create(['name' => 'Admin Tools', 'slug' => 'admin-tools', 'plugin_type_id' => $type->id, 'status' => PluginStatus::ACTIVE->value]);
        PluginRelease::query()->create(['plugin_id' => $plugin->id, 'version' => '1.2.0', 'internal_version' => 1, 'zip_path' => 'plugins/a.zip', 'zip_filename' => 'a.zip', 'encapsulated' => 'unknown', 'status' => PluginStatus::ACTIVE->value]);

        return $plugin;
    }

    private function auditLogExpecting(callable $matches): void
    {
        $channel = Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('info')->once()->with('api.change', Mockery::on($matches));

        Log::partialMock()->shouldReceive('channel')->once()->with('api-audit')->andReturn($channel);
    }

    public function test_a_change_made_with_a_key_is_logged_with_the_key_s_id(): void
    {
        $this->livePlugin();
        $user = User::factory()->create();
        $this->grantEntitlements($user, ['api.plugins.read', 'api.plugins.write']);
        $token = $user->createToken('plugin-api', ['plugins:read', 'plugins:write']);

        $this->auditLogExpecting(fn (array $context): bool => $context['change'] === 'plugin.followed'
            && $context['user_id'] === $user->id
            && $context['plugin'] === 'admin-tools'
            && $context['token_id'] === $token->accessToken->id
            && is_string($context['ip']));

        $this->withToken($token->plainTextToken)->postJson('/api/v1/plugins/admin-tools/subscription')->assertOk();
    }

    public function test_a_refused_change_is_not_logged(): void
    {
        $this->livePlugin();
        $user = User::factory()->create();
        $this->grantEntitlements($user, ['api.plugins.read', 'api.plugins.write']);
        $token = $user->createToken('plugin-api', ['plugins:read', 'plugins:write']);

        Log::partialMock()->shouldNotReceive('channel')->with('api-audit');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/plugins/no-such-plugin/subscription')->assertNotFound();
    }

    public function test_a_change_made_from_a_session_is_logged_without_a_key_id(): void
    {
        $this->livePlugin();
        $user = User::factory()->create();
        $this->grantEntitlements($user, ['api.plugins.read', 'api.plugins.write']);
        Sanctum::actingAs($user, ['plugins:read', 'plugins:write']);

        $this->auditLogExpecting(fn (array $context): bool => $context['change'] === 'plugin.followed' && $context['token_id'] === null);

        $this->postJson('/api/v1/plugins/admin-tools/subscription')->assertOk();
    }

    public function test_the_audit_log_is_a_daily_channel_of_its_own(): void
    {
        $channel = config('logging.channels.api-audit');

        $this->assertSame('daily', $channel['driver']);
        $this->assertStringEndsWith('api-audit.log', $channel['path']);
        $this->assertSame(90, (int) $channel['days']);
    }
}
