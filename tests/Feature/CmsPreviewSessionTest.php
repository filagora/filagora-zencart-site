<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsNavigation;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Preview\PreviewSession;
use Filagora\Cms\Services\NavigationDrafts;
use Filagora\Cms\Services\PageDrafts;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class CmsPreviewSessionTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->staff = $this->userWith(['operator.dashboard.access', 'operator.cms.manage']);
        $this->actingAs($this->staff);
    }

    public function test_previewing_a_page_opens_it_at_its_public_url_and_serves_its_draft(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy');

        $this->get(route('cms.pages.preview.start', $page))
            ->assertRedirect(route('cms.pages.show', 'faq'))
            ->assertSessionHas(PreviewSession::SESSION_KEY, ['navigations' => [], 'pages' => [$page->id => true]]);

        $this->withSession($this->previewSession(pages: [$page->id]))
            ->get('/pages/faq')
            ->assertOk()
            ->assertSee('Draft copy')
            ->assertDontSee('Live copy')
            ->assertSee('Previewing unpublished changes');

        auth()->logout();
        $this->get('/pages/faq')->assertSee('Live copy')->assertDontSee('Draft copy');
    }

    public function test_a_draft_menu_and_the_draft_pages_it_links_to_preview_together(): void
    {
        $linked = $this->pageWithDraft('linked', 'Live linked copy', 'Draft linked copy');
        $untouched = $this->pageWithDraft('other', 'Live other copy', 'Draft other copy');
        $menu = $this->headerMenu('Old Entry', '/pages/linked');
        app(NavigationDrafts::class)->saveDraft($menu, [
            ['label' => 'New Section Entry', 'type' => 'url', 'url' => '/pages/linked', 'target' => '_self'],
            ['label' => 'Other Entry', 'type' => 'url', 'url' => '/pages/other', 'target' => '_self'],
        ], $this->staff);

        // Only the menu and the "linked" page are in the preview.
        $session = $this->previewSession(menus: ['header'], pages: [$linked->id]);

        $this->withSession($session)->get('/pages/linked')
            ->assertOk()
            ->assertSee('Draft linked copy')
            ->assertSee('New Section Entry')
            ->assertSee('Other Entry');

        // A page that is not in the preview shows its live version, inside the draft menu.
        $this->withSession($session)->get('/pages/other')
            ->assertOk()
            ->assertSee('Live other copy')
            ->assertDontSee('Draft other copy')
            ->assertSee('New Section Entry');

        // Add it, and the same URL now shows its draft.
        $this->withSession($this->previewSession(menus: ['header'], pages: [$linked->id, $untouched->id]))
            ->get('/pages/other')
            ->assertSee('Draft other copy');
    }

    public function test_a_page_that_is_not_published_yet_is_visible_only_to_the_staff_previewing_it(): void
    {
        $page = $this->pageWithDraft('coming-soon', 'Not live yet', 'Not live yet', status: CmsPageStatus::DRAFT);

        auth()->logout();
        $this->get('/pages/coming-soon')->assertNotFound();

        $this->actingAs($this->staff)->withSession($this->previewSession(pages: [$page->id]))
            ->get('/pages/coming-soon')
            ->assertOk()
            ->assertSee('Not live yet');

        $this->actingAs($this->userWith([]))->withSession($this->previewSession(pages: [$page->id]))
            ->get('/pages/coming-soon')
            ->assertNotFound();
    }

    public function test_the_home_page_and_custom_route_pages_are_served_from_their_drafts(): void
    {
        $home = CmsPage::query()->updateOrCreate(['slug' => 'home'], [
            'custom_route' => '/',
            'title' => 'Home',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $about = CmsPage::query()->updateOrCreate(['slug' => 'about'], [
            'custom_route' => '/about',
            'title' => 'About',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        // These pages use fixed templates, so the visible difference is the title.
        foreach ([$home, $about] as $page) {
            app(PageDrafts::class)->saveDraftFields($page, ['seo_title' => 'Draft '.$page->slug.' title']);
            $page->forceFill(['seo_title' => 'Live '.$page->slug.' title'])->save();
        }

        $session = $this->previewSession(pages: [$home->id, $about->id]);

        $this->withSession($session)->get('/')->assertOk()->assertSee('<title>Draft home title</title>', false);
        $this->withSession($session)->get('/about')->assertOk()->assertSee('<title>Draft about title</title>', false);

        auth()->logout();
        $this->get('/')->assertOk()->assertSee('<title>Live home title</title>', false);
        $this->get('/about')->assertOk()->assertSee('<title>Live about title</title>', false);
    }

    public function test_a_draft_that_changes_the_slug_is_previewed_at_the_new_address(): void
    {
        $page = $this->pageWithDraft('old-slug', 'Live copy', 'Draft copy');
        app(PageDrafts::class)->saveDraftFields($page, ['slug' => 'new-slug']);

        $this->get(route('cms.pages.preview.start', $page))->assertRedirect(route('cms.pages.show', 'new-slug'));

        $this->withSession($this->previewSession(pages: [$page->id]))
            ->get('/pages/new-slug')
            ->assertOk()
            ->assertSee('Draft copy');
    }

    public function test_one_item_can_be_removed_and_the_whole_preview_can_be_exited(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy', title: 'Frequently Asked');
        $menu = $this->headerMenu('Old Entry', '/pages/faq');
        app(NavigationDrafts::class)->ensureDraft($menu, $this->staff);

        $this->withSession($this->previewSession(menus: ['header'], pages: [$page->id]))
            ->get('/pages/faq')
            ->assertSee('header menu')
            ->assertSee('Frequently Asked')
            ->assertSee('Exit preview');

        $this->withSession($this->previewSession(menus: ['header'], pages: [$page->id]))
            ->post(route('cms.preview.remove'), ['type' => 'page', 'key' => (string) $page->id, 'return' => '/pages/faq'])
            ->assertRedirect('/pages/faq')
            ->assertSessionHas(PreviewSession::SESSION_KEY, ['navigations' => ['header' => true], 'pages' => []]);

        $this->withSession($this->previewSession(menus: ['header'], pages: [$page->id]))
            ->post(route('cms.preview.stop'), ['return' => '//evil.example'])
            ->assertRedirect('/')
            ->assertSessionMissing(PreviewSession::SESSION_KEY);
    }

    public function test_the_preview_session_grants_nothing_without_the_right_entitlement(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy');
        $member = $this->userWith([]);

        $this->actingAs($member)->get(route('cms.pages.preview.start', $page))->assertForbidden();

        $this->actingAs($member)->withSession($this->previewSession(pages: [$page->id]))
            ->get('/pages/faq')
            ->assertSee('Live copy')
            ->assertDontSee('Draft copy')
            ->assertDontSee('Previewing unpublished');

        $previewer = $this->userWith(['operator.cms.preview']);
        $this->actingAs($previewer)->get(route('cms.pages.preview.start', $page))->assertRedirect(route('cms.pages.show', 'faq'));
    }

    public function test_responses_while_previewing_are_never_cacheable_or_indexable(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy');

        $previewing = $this->withSession($this->previewSession(pages: [$page->id]))->get('/pages/faq')->assertOk();
        $this->assertStringContainsString('no-store', (string) $previewing->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $previewing->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $previewing->headers->get('X-Robots-Tag'));

        // The same staff member with nothing in their preview gets ordinary headers.
        $this->flushSession();
        $ordinary = $this->get('/pages/faq')->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $ordinary->headers->get('Cache-Control'));
        $this->assertNull($ordinary->headers->get('X-Robots-Tag'));

        // A session flag without the entitlement does not change anyone's headers.
        $this->actingAs($this->userWith([]))->withSession($this->previewSession(pages: [$page->id]))
            ->get('/pages/faq')
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_a_page_without_a_draft_cannot_be_added_to_the_preview(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'no-draft',
            'title' => 'No Draft',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('cms.pages.preview.start', $page))->assertNotFound();
    }

    /**
     * @param  list<string>  $menus
     * @param  list<int>  $pages
     * @return array<string, array<string, array<int|string, true>>>
     */
    private function previewSession(array $menus = [], array $pages = []): array
    {
        return [PreviewSession::SESSION_KEY => [
            'navigations' => array_fill_keys($menus, true),
            'pages' => array_fill_keys($pages, true),
        ]];
    }

    private function pageWithDraft(string $slug, string $liveCopy, string $draftCopy, CmsPageStatus $status = CmsPageStatus::PUBLISHED, string $title = 'A Page'): CmsPage
    {
        $page = CmsPage::query()->create([
            'slug' => $slug,
            'title' => $title,
            'status' => $status,
            'published_at' => $status === CmsPageStatus::PUBLISHED ? now()->subDay() : null,
        ]);
        $page->blocks()->create(['type' => 'raw_html', 'sort_order' => 10, 'data' => ['html' => '<p>'.$liveCopy.'</p>']]);

        app(PageDrafts::class)->ensureDraft($page);
        $page->draftBlocks()->firstOrFail()->update(['data' => ['html' => '<p>'.$draftCopy.'</p>']]);

        return $page;
    }

    private function headerMenu(string $label, string $url): CmsNavigation
    {
        return CmsNavigation::query()->updateOrCreate(['name' => 'header'], ['items' => [
            ['label' => $label, 'type' => 'url', 'url' => $url, 'target' => '_self'],
        ]]);
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
