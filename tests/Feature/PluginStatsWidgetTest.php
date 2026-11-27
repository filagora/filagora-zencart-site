<?php

namespace Tests\Feature;

use App\Filament\Widgets\PluginStats;
use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PluginStatsWidgetTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_widget_counts_every_plugin_and_release_waiting_for_review(): void
    {
        $moderator = $this->moderator();

        $type = PluginType::query()->create(['name' => 'Payments', 'slug' => 'payments', 'is_active' => true]);

        // More than the library's queue lists at once, so the count cannot come from that list.
        for ($i = 1; $i <= 21; $i++) {
            $this->plugin($type, "waiting-{$i}", PluginStatus::MODERATED);
        }
        $active = $this->plugin($type, 'published', PluginStatus::ACTIVE);

        foreach ([PluginStatus::MODERATED, PluginStatus::MODERATED, PluginStatus::ACTIVE] as $i => $status) {
            PluginRelease::query()->create([
                'plugin_id' => $active->id,
                'uploaded_by_id' => $moderator->id,
                'version' => "1.0.{$i}",
                'internal_version' => $i + 1,
                'zip_path' => "plugins/published-{$i}.zip",
                'zip_filename' => "published-{$i}.zip",
                'status' => $status->value,
                'encapsulated' => 'unknown',
            ]);
        }

        $this->actingAs($moderator);

        Livewire::withoutLazyLoading()
            ->test(PluginStats::class)
            ->assertOk()
            ->assertSee('23')
            ->assertSee('21 plugins and 2 releases pending review')
            ->assertSee(route('plugins.index'));
    }

    public function test_widget_words_a_single_plugin_and_release_in_the_singular(): void
    {
        $moderator = $this->moderator();
        $type = PluginType::query()->create(['name' => 'Payments', 'slug' => 'payments', 'is_active' => true]);
        $plugin = $this->plugin($type, 'waiting', PluginStatus::MODERATED);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $moderator->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/waiting.zip',
            'zip_filename' => 'waiting.zip',
            'status' => PluginStatus::MODERATED->value,
            'encapsulated' => 'unknown',
        ]);

        $this->actingAs($moderator);

        Livewire::withoutLazyLoading()
            ->test(PluginStats::class)
            ->assertSee('1 plugin and 1 release pending review');
    }

    public function test_the_review_queue_counts_everything_waiting_and_says_when_it_lists_only_some(): void
    {
        $type = PluginType::query()->create(['name' => 'Payments', 'slug' => 'payments', 'is_active' => true]);

        for ($i = 1; $i <= 21; $i++) {
            $this->plugin($type, "waiting-{$i}", PluginStatus::MODERATED);
        }

        $this->actingAs($this->moderator())
            ->get(route('plugins.index'))
            ->assertOk()
            ->assertSee('21 plugins · 0 releases to review')
            ->assertSee('Only the latest 20 of each are listed.');
    }

    public function test_widget_is_hidden_from_someone_who_cannot_moderate_plugins(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(PluginStats::canView());
    }

    private function moderator(): User
    {
        $moderator = User::factory()->create();
        UserEntitlementGrant::query()->create([
            'user_id' => $moderator->id,
            'entitlement_key' => 'operator.plugin.moderate',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        return $moderator;
    }

    private function plugin(PluginType $type, string $slug, PluginStatus $status): Plugin
    {
        return Plugin::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'plugin_type_id' => $type->id,
            'status' => $status->value,
        ]);
    }
}
