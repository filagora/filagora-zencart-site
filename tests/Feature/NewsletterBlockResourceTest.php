<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use App\Filament\Resources\NewsletterBlocks\Pages\CreateNewsletterBlock;
use App\Filament\Resources\NewsletterBlocks\Pages\EditNewsletterBlock;
use App\Filament\Resources\NewsletterBlocks\Schemas\NewsletterBlockForm;
use App\Models\Newsletter;
use App\Models\NewsletterBlock;
use App\Models\NewsletterLinkClick;
use App\Models\User;
use App\NewsletterStatus;
use App\Services\NewsletterContentRenderer;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ReflectionClass;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterBlockResourceTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_newsletter_block_resource_requires_newsletter_entitlement(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get(NewsletterBlockResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_staff_can_create_a_reusable_text_block(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(CreateNewsletterBlock::class)
            ->fillForm([
                'name' => 'Spring Sale Banner',
                'type' => 'text',
                'content.heading' => 'Spring sale is live',
                'content.body_html' => '<p>20% off everything.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $block = NewsletterBlock::query()->where('name', 'Spring Sale Banner')->firstOrFail();

        $this->assertSame('spring-sale-banner', $block->key);
        $this->assertSame('text', $block->type->value);
        $this->assertSame('Spring sale is live', $block->content['heading']);
        $this->assertTrue($block->is_active);
        $this->assertFalse($block->is_system);
    }

    public function test_staff_can_edit_a_reusable_block(): void
    {
        $user = $this->newsletterManager();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $block = NewsletterBlock::query()->create([
            'key' => 'promo-banner',
            'name' => 'Promo banner',
            'type' => 'text',
            'content' => ['heading' => 'Original copy'],
        ]);

        Livewire::test(EditNewsletterBlock::class, ['record' => $block->getRouteKey()])
            ->fillForm([
                'content.heading' => 'Updated copy',
                'content.body_html' => '<p>Updated body</p>',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $block->refresh();

        $this->assertSame('Updated copy', $block->content['heading']);
        $this->assertFalse($block->is_active);
        $this->assertTrue($block->updater->is($user));
    }

    public function test_system_blocks_cannot_be_deleted(): void
    {
        $user = $this->newsletterManager();

        $header = NewsletterBlock::query()->where('key', 'shared_header')->firstOrFail();

        $this->assertTrue($header->is_system);
        $this->assertFalse(Gate::forUser($user)->allows('delete', $header));
    }

    public function test_non_system_blocks_can_be_deleted(): void
    {
        $user = $this->newsletterManager();

        $block = NewsletterBlock::query()->create([
            'key' => 'retired-banner',
            'name' => 'Retired banner',
            'type' => 'text',
            'content' => ['heading' => 'Retired'],
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('delete', $block));
    }

    public function test_block_view_page_shows_aggregate_click_performance(): void
    {
        $block = NewsletterBlock::query()->create([
            'key' => 'performance-banner',
            'name' => 'Performance banner',
            'type' => 'button',
            'content' => ['button_label' => 'Shop now', 'button_url' => 'https://example.test/shop'],
        ]);

        $renderer = app(NewsletterContentRenderer::class);
        $newsletter = Newsletter::query()->create([
            'title' => 'Performance campaign',
            'subject' => 'Performance campaign',
            'body_html' => '',
            'content_blocks' => $renderer->preparedBlocks([
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ]),
            'status' => NewsletterStatus::Draft,
        ]);

        NewsletterLinkClick::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_block_id' => $block->id,
            'block_key' => $newsletter->content_blocks[1]['block_key'],
            'label' => 'Shop now',
            'destination_url' => 'https://example.test/shop',
            'clicked_at' => now(),
        ]);

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterBlockResource::getUrl('view', ['record' => $block]))
            ->assertOk()
            ->assertSee('Click Performance')
            ->assertSee('Total Clicks');
    }

    public function test_text_block_rich_editor_supports_inserting_images_from_the_media_library(): void
    {
        $reflection = new ReflectionClass(NewsletterBlockForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('InsertMediaLibraryImagePlugin::make()', $source);
        $this->assertStringContainsString("'insertMediaLibraryImage'", $source);
    }

    public function test_text_block_rich_editor_supports_inserting_tracked_links(): void
    {
        $reflection = new ReflectionClass(NewsletterBlockForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('InsertTrackedLinkPlugin::make()', $source);
        $this->assertStringContainsString("'insertTrackedLink'", $source);
    }

    private function newsletterManager(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.newsletter.manage');

        return $user;
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
