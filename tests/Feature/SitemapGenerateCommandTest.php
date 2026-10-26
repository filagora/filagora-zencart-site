<?php

namespace Tests\Feature;

use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SitemapGenerateCommandTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_it_lists_public_pages_plugins_and_forums_and_leaves_out_everything_else(): void
    {
        Storage::fake('public');

        $type = PluginType::query()->create(['name' => 'Layout', 'slug' => 'layout', 'is_active' => true]);
        $hiddenType = PluginType::query()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $live = Plugin::query()->create(['name' => 'Live', 'slug' => 'live-plugin', 'plugin_type_id' => $type->id, 'status' => PluginStatus::ACTIVE->value]);
        PluginRelease::query()->create(['plugin_id' => $live->id, 'version' => '1.0.0', 'internal_version' => 1, 'zip_path' => 'plugins/a.zip', 'zip_filename' => 'a.zip', 'encapsulated' => 'unknown', 'status' => PluginStatus::ACTIVE->value]);
        Plugin::query()->create(['name' => 'Pending', 'slug' => 'pending-plugin', 'plugin_type_id' => $type->id, 'status' => PluginStatus::MODERATED->value]);
        Plugin::query()->create(['name' => 'Typed out', 'slug' => 'typed-out', 'plugin_type_id' => $hiddenType->id, 'status' => PluginStatus::ACTIVE->value]);

        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general', 'is_active' => true, 'sort_order' => 1]);
        $public = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'Open', 'slug' => 'open', 'is_active' => true, 'visibility' => ForumVisibility::PUBLIC->value, 'sort_order' => 1]);
        $members = Forum::query()->create(['forum_group_id' => $group->id, 'name' => 'Members', 'slug' => 'members', 'is_active' => true, 'visibility' => ForumVisibility::REGISTERED->value, 'sort_order' => 2]);

        $this->artisan('sitemap:generate')->expectsOutput('Generating sitemap...')->assertExitCode(0);

        $xml = Storage::disk('public')->get('sitemap.xml');

        $this->assertStringContainsString('<loc>'.route('home').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('plugins.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('plugins.type', 'layout').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('plugins.show', $live).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('forums.show', $public).'</loc>', $xml);
        $this->assertStringNotContainsString('pending-plugin', $xml);
        $this->assertStringNotContainsString('typed-out', $xml);
        $this->assertStringNotContainsString('plugins/type/hidden', $xml);
        $this->assertStringNotContainsString(route('forums.show', $members), $xml);
        $this->assertSame(substr_count($xml, '<loc>'.route('home').'</loc>'), 1);
    }

    public function test_it_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn ($scheduled) => str_contains((string) $scheduled->command, 'sitemap:generate')
        );

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
