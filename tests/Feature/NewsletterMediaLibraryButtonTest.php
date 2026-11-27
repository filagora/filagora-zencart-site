<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Cms\Models\CmsMediaAsset;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\CreateNewsletterBlock;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterMediaLibraryButtonTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_the_media_library_button_opens_and_inserts_the_chosen_image(): void
    {
        $asset = $this->asset(['alt_text' => 'Release banner']);

        $this->insert($asset)
            ->assertHasNoFormErrors()
            ->assertDispatched(
                'run-rich-editor-commands',
                fn (string $event, array $params): bool => $params['commands'][0]['arguments'][0]['attrs']['src'] === $asset->publicUrl()
                    && $params['commands'][0]['arguments'][0]['attrs']['alt'] === 'Release banner',
            );
    }

    public function test_an_image_without_alt_text_is_described_by_its_filename(): void
    {
        $this->insert($this->asset(['alt_text' => null]))
            ->assertDispatched(
                'run-rich-editor-commands',
                fn (string $event, array $params): bool => $params['commands'][0]['arguments'][0]['attrs']['alt'] === 'banner.png',
            );
    }

    public function test_an_image_whose_file_is_gone_is_reported_and_the_dialog_stays_open(): void
    {
        $asset = $this->asset();
        Storage::disk('public')->delete('cms/banner.png');

        $this->insert($asset)
            ->assertNotified('That image could not be inserted')
            ->assertActionMounted($this->button())
            ->assertNotDispatched('run-rich-editor-commands');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(array $attributes = []): CmsMediaAsset
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms/banner.png', 'image');

        return CmsMediaAsset::query()->create([
            'disk' => 'public',
            'path' => 'cms/banner.png',
            'filename' => 'banner.png',
            'mime_type' => 'image/png',
            ...$attributes,
        ]);
    }

    private function button(): TestAction
    {
        return TestAction::make('insertMediaLibraryImage')->schemaComponent('content.body_html');
    }

    private function insert(CmsMediaAsset $asset): Testable
    {
        $editor = User::factory()->create(['email_verified_at' => now()]);
        foreach (['operator.dashboard.access', 'operator.newsletter.manage'] as $key) {
            UserEntitlementGrant::query()->create([
                'user_id' => $editor->id,
                'entitlement_key' => $key,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }

        $this->actingAs($editor);
        Filament::setCurrentPanel('dashboard');

        return Livewire::test(CreateNewsletterBlock::class)
            ->fillForm(['type' => 'text'])
            ->mountAction($this->button(), ['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]])
            ->assertActionMounted($this->button())
            ->fillForm(['asset_id' => $asset->id])
            ->callMountedAction();
    }
}
