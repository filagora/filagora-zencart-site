<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsNavigation;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Models\CmsPreviewShare;
use Filagora\Cms\Preview\PreviewShares;
use Filagora\Cms\Services\NavigationDrafts;
use Filagora\Cms\Services\PageDrafts;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class CmsPreviewShareTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->staff = $this->userWith(['operator.dashboard.access', 'operator.cms.manage']);
    }

    public function test_a_visitor_with_the_link_sees_the_pinned_variant_even_after_staff_switch_away(): void
    {
        $page = $this->pageWithTwoVariants();
        $share = $this->share(pages: [$page->id => 'Layout B']);

        $this->get($share->url())
            ->assertRedirect(route('cms.pages.show', 'faq'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/pages/faq')
            ->assertOk()
            ->assertSee('Copy B')
            ->assertDontSee('Copy A')
            ->assertSee('Shared preview of unpublished changes')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');

        // Staff switch the working draft to the other variant; the link stays pinned to B.
        app(PageDrafts::class)->switchVariant($page, 'Main', $this->staff);
        $this->assertSame('Main', app(PageDrafts::class)->activeVariantName($page));

        $this->get('/pages/faq')->assertSee('Copy B')->assertDontSee('Copy A');
        $this->assertSame(1, $share->fresh()->open_count);
    }

    public function test_a_shared_menu_variant_shows_in_the_header_for_the_visitor_only(): void
    {
        $menu = CmsNavigation::query()->updateOrCreate(['name' => 'header'], ['items' => $this->items('Live Link')]);
        $service = app(NavigationDrafts::class);
        $service->saveDraft($menu, $this->items('Menu A'), $this->staff);
        $service->createVariant($menu, 'Menu B', false, $this->staff);
        $service->saveDraft($menu, $this->items('Menu B'), $this->staff);
        $share = $this->share(navigations: ['header' => 'Main']);

        $this->get('/about')->assertDontSee('Menu A')->assertDontSee('Menu B');

        $this->get($share->url())->assertRedirect('/');
        $this->get('/about')->assertSee('Menu A')->assertDontSee('Menu B')->assertDontSee('Live Link');

        $this->flushSession();
        $this->get('/about')->assertSee('Live Link')->assertDontSee('Menu A');
    }

    public function test_unknown_expired_and_revoked_links_do_not_work(): void
    {
        $page = $this->pageWithTwoVariants();

        $this->get(route('cms.preview.share.open', 'nope'))->assertNotFound();

        $expired = $this->share(pages: [$page->id => 'Layout B']);
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->get($expired->url())->assertNotFound();

        $revoked = $this->share(pages: [$page->id => 'Layout B']);
        $revoked->update(['revoked_at' => now()]);
        $this->get($revoked->url())->assertNotFound();
    }

    public function test_revoking_a_link_ends_the_preview_for_visitors_already_inside(): void
    {
        $page = $this->pageWithTwoVariants();
        $share = $this->share(pages: [$page->id => 'Layout B']);

        $this->get($share->url());
        $this->get('/pages/faq')->assertSee('Copy B');

        app(PreviewShares::class)->revoke($share);

        $this->get('/pages/faq')->assertSee('Live copy')->assertDontSee('Copy B')->assertDontSee('Shared preview');
    }

    public function test_exiting_the_preview_returns_the_visitor_to_the_live_site(): void
    {
        $page = $this->pageWithTwoVariants();
        $share = $this->share(pages: [$page->id => 'Layout B']);

        $this->get($share->url());
        $this->post(route('cms.preview.share.exit'))->assertRedirect('/');

        $this->get('/pages/faq')->assertSee('Live copy')->assertDontSee('Copy B');
    }

    public function test_a_link_to_a_missing_variant_cannot_be_created_and_a_deleted_one_falls_back_to_live(): void
    {
        $page = $this->pageWithTwoVariants();
        $shares = app(PreviewShares::class);

        try {
            $shares->create($this->staff, [], [$page->id => 'Nope'], 7);
            $this->fail('A missing variant should be refused.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        try {
            $shares->create($this->staff, [], [], 7);
            $this->fail('An empty link should be refused.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $share = $this->share(pages: [$page->id => 'Layout B']);
        app(PageDrafts::class)->switchVariant($page, 'Main', $this->staff);
        app(PageDrafts::class)->deleteVariant($page, 'Layout B');

        $this->get($share->url());
        $this->get('/pages/faq')->assertSee('Live copy');
    }

    private function pageWithTwoVariants(): CmsPage
    {
        $page = CmsPage::query()->create([
            'slug' => 'faq',
            'title' => 'FAQ',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $page->blocks()->create(['type' => 'raw_html', 'sort_order' => 10, 'data' => ['html' => '<p>Live copy</p>']]);

        $service = app(PageDrafts::class);
        $service->ensureDraft($page, $this->staff);
        $page->draftBlocks()->firstOrFail()->update(['data' => ['html' => '<p>Copy A</p>']]);
        $service->createVariant($page, 'Layout B', false, $this->staff);
        $page->draftBlocks()->firstOrFail()->update(['data' => ['html' => '<p>Copy B</p>']]);

        return $page;
    }

    /**
     * @param  array<string, string>  $navigations
     * @param  array<int, string>  $pages
     */
    private function share(array $navigations = [], array $pages = []): CmsPreviewShare
    {
        return app(PreviewShares::class)->create($this->staff, $navigations, $pages, 7);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(string $label): array
    {
        return [['label' => $label, 'type' => 'url', 'url' => '/forums', 'target' => '_self']];
    }

    /**
     * @param  list<string>  $entitlements
     */
    private function userWith(array $entitlements): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach ($entitlements as $key) {
            UserEntitlementGrant::query()->create([
                'user_id' => $user->id,
                'entitlement_key' => $key,
                'grant_type' => 'manual',
                'starts_at' => now()->subMinute(),
            ]);
        }

        return $user;
    }
}
