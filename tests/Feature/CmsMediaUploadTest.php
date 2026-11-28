<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Cms\Filament\Resources\CmsMediaAssets\Pages\CreateCmsMediaAsset;
use Filagora\Cms\Filament\Resources\CmsMediaAssets\Pages\ListCmsMediaAssets;
use Filagora\Cms\Models\CmsMediaAsset;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CmsMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_takes_only_listed_file_types_and_never_replaces_a_file(): void
    {
        $editor = User::factory()->create(['email_verified_at' => now()]);

        foreach (['operator.dashboard.access', 'operator.cms.manage'] as $key) {
            UserEntitlementGrant::query()->create(['user_id' => $editor->id, 'entitlement_key' => $key, 'grant_type' => 'manual']);
        }

        Storage::fake('public');
        $seeded = CmsMediaAsset::query()->pluck('id')->all();

        $this->actingAs($editor)->get('/dashboard/cms-media-assets/create')->assertOk();

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        $this->actingAs($editor);

        foreach ([['tool.php', 'text/x-php'], ['page.html', 'text/html'], ['logo.svg', 'image/svg+xml']] as [$name, $type]) {
            Livewire::test(CreateCmsMediaAsset::class)->fillForm(['path' => UploadedFile::fake()->create($name, 1, $type)])->call('create')->assertHasFormErrors(['path']);
            Livewire::test(ListCmsMediaAssets::class)->callAction('bulkUpload', ['paths' => [UploadedFile::fake()->create($name, 1, $type)]])->assertHasActionErrors(['paths']);
        }

        Livewire::test(CreateCmsMediaAsset::class)->fillForm(['path' => UploadedFile::fake()->image('Home Hero.JPG')])->call('create')->assertHasNoFormErrors();
        Livewire::test(ListCmsMediaAssets::class)->callAction('bulkUpload', ['paths' => [UploadedFile::fake()->image('Home Hero.jpg')]])->assertHasNoActionErrors();

        $paths = CmsMediaAsset::query()->whereNotIn('id', $seeded)->orderBy('id')->pluck('path')->all();
        $this->assertSame(['cms-media/Home-Hero.jpg', 'cms-media/Home-Hero-2.jpg'], $paths);
    }
}
