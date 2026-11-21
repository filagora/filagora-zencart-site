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

    public function test_previewing_a_page_opens_it_at_its_public_url_with_a_link_to_its_working_draft(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy');

        $response = $this->get(route('cms.pages.preview.start', $page));
        $share = CmsPreviewShare::query()->latest('id')->firstOrFail();
        $token = $share->getRawOriginal('token');

        $response->assertRedirect(route('cms.pages.show', 'faq').'?preview='.$token)->assertSessionMissing('cms_preview');
        $this->assertSame([$page->id => true], $share->pages());
        $this->assertSame('Editor preview', $share->label);
        $this->assertSame($this->staff->id, $share->created_by_id);

        $this->get('/pages/faq?preview='.$token)
            ->assertOk()
            ->assertSee('Draft copy')
            ->assertDontSee('Live copy')
            ->assertSee('Previewing unpublished changes');

        // The same address without the token is the original, for the same signed-in editor.
        $this->get('/pages/faq')->assertSee('Live copy')->assertDontSee('Draft copy')->assertDontSee('Previewing unpublished');

        auth()->logout();
        $this->get('/pages/faq')->assertSee('Live copy')->assertDontSee('Draft copy');
    }

    public function test_the_link_follows_the_working_draft_as_the_editor_keeps_editing(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'First edit');
        $this->get(route('cms.pages.preview.start', $page));
        $token = CmsPreviewShare::query()->latest('id')->firstOrFail()->getRawOriginal('token');

        $this->get('/pages/faq?preview='.$token)->assertSee('First edit');

        $page->draftBlocks()->firstOrFail()->update(['data' => ['html' => '<p>Second edit</p>']]);

        $this->get('/pages/faq?preview='.$token)->assertSee('Second edit')->assertDontSee('First edit');
        $this->get(route('cms.pages.preview.start', $page));
        $this->assertSame(1, CmsPreviewShare::query()->count());
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
        $token = $this->token(menus: ['header'], pages: [$linked->id]);

        $this->get('/pages/linked?preview='.$token)
            ->assertOk()
            ->assertSee('Draft linked copy')
            ->assertSee('New Section Entry')
            ->assertSee('Other Entry');

        // A page that is not in the preview shows its live version, inside the draft menu.
        $this->get('/pages/other?preview='.$token)
            ->assertOk()
            ->assertSee('Live other copy')
            ->assertDontSee('Draft other copy')
            ->assertSee('New Section Entry');

        // Add it, and the same URL now shows its draft.
        $both = $this->token(menus: ['header'], pages: [$linked->id, $untouched->id]);
        $this->get('/pages/other?preview='.$both)->assertSee('Draft other copy');
    }

    public function test_links_on_a_previewed_page_keep_the_token_but_not_into_the_staff_area_or_off_site(): void
    {
        $page = $this->pageWithDraft('linked', 'Live linked copy', 'Draft linked copy');
        $menu = $this->headerMenu('Old Entry', '/pages/linked');
        app(NavigationDrafts::class)->saveDraft($menu, [
            ['label' => 'Entry', 'type' => 'url', 'url' => '/pages/other', 'target' => '_self'],
            ['label' => 'Away', 'type' => 'url', 'url' => 'https://other.example/x', 'target' => '_self'],
        ], $this->staff);
        $token = $this->token(menus: ['header'], pages: [$page->id]);

        $html = $this->get('/pages/linked?preview='.$token)->assertOk()->getContent();

        $this->assertStringContainsString('href="/pages/other?preview='.$token.'"', $html);
        $this->assertStringContainsString('href="https://other.example/x"', $html);
        $this->assertStringNotContainsString('/login?preview=', $html);
        $this->assertStringNotContainsString('/staff?preview=', $html);

        $plain = $this->get('/pages/linked')->getContent();
        $this->assertStringNotContainsString('preview='.$token, $plain);
    }

    public function test_a_page_that_is_not_published_yet_is_visible_only_with_the_token(): void
    {
        $page = $this->pageWithDraft('coming-soon', 'Not live yet', 'Not live yet', status: CmsPageStatus::DRAFT);
        $token = $this->token(pages: [$page->id]);

        auth()->logout();
        $this->get('/pages/coming-soon')->assertNotFound();
        $this->get('/pages/coming-soon?preview=wrong')->assertNotFound();
        $this->get('/pages/coming-soon?preview='.$token)->assertOk()->assertSee('Not live yet');
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

        $token = $this->token(pages: [$home->id, $about->id]);

        $this->get('/?preview='.$token)->assertOk()->assertSee('<title>Draft home title</title>', false);
        $this->get('/about?preview='.$token)->assertOk()->assertSee('<title>Draft about title</title>', false);

        $this->get('/')->assertOk()->assertSee('<title>Live home title</title>', false);
        $this->get('/about')->assertOk()->assertSee('<title>Live about title</title>', false);
    }

    public function test_a_draft_that_changes_the_slug_is_previewed_at_the_new_address(): void
    {
        $page = $this->pageWithDraft('old-slug', 'Live copy', 'Draft copy');
        app(PageDrafts::class)->saveDraftFields($page, ['slug' => 'new-slug']);

        $this->get(route('cms.pages.preview.start', $page))
            ->assertRedirect(route('cms.pages.show', 'new-slug').'?preview='.CmsPreviewShare::query()->latest('id')->firstOrFail()->getRawOriginal('token'));

        $this->get('/pages/new-slug?preview='.$this->token(pages: [$page->id]))
            ->assertOk()
            ->assertSee('Draft copy');
    }

    public function test_previewing_a_menu_opens_the_home_page_with_a_link_to_its_working_draft(): void
    {
        $menu = $this->headerMenu('Old Entry', '/pages/faq');
        app(NavigationDrafts::class)->saveDraft($menu, [
            ['label' => 'Draft Entry', 'type' => 'url', 'url' => '/pages/faq', 'target' => '_self'],
        ], $this->staff);

        $response = $this->get(route('cms.navigations.preview', $menu));
        $share = CmsPreviewShare::query()->latest('id')->firstOrFail();

        $response->assertRedirect('/?preview='.$share->getRawOriginal('token'));
        $this->assertSame(['header' => true], $share->navigations());

        $this->get('/about?preview='.$share->getRawOriginal('token'))->assertSee('Draft Entry')->assertDontSee('Old Entry');
        $this->get('/about')->assertSee('Old Entry')->assertDontSee('Draft Entry');
    }

    public function test_starting_a_preview_needs_the_right_entitlement(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy');
        $member = $this->userWith([]);

        $this->actingAs($member)->get(route('cms.pages.preview.start', $page))->assertForbidden();
        $this->assertSame(0, CmsPreviewShare::query()->count());

        $previewer = $this->userWith(['operator.cms.preview']);
        $this->actingAs($previewer)->get(route('cms.pages.preview.start', $page))
            ->assertRedirectContains(route('cms.pages.show', 'faq').'?preview=');
    }

    public function test_responses_to_a_request_with_a_token_are_never_cacheable_or_indexable(): void
    {
        $page = $this->pageWithDraft('faq', 'Live copy', 'Draft copy');
        $token = $this->token(pages: [$page->id]);

        $previewing = $this->get('/pages/faq?preview='.$token)->assertOk();
        $this->assertStringContainsString('no-store', (string) $previewing->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $previewing->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $previewing->headers->get('X-Robots-Tag'));
        $this->assertSame('no-referrer', $previewing->headers->get('Referrer-Policy'));

        $ordinary = $this->get('/pages/faq')->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $ordinary->headers->get('Cache-Control'));
        $this->assertNull($ordinary->headers->get('X-Robots-Tag'));

        $this->get('/pages/faq?preview=wrong')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_a_page_without_a_draft_cannot_be_previewed(): void
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
     * A working-draft link for the signed-in staff member; returns its token.
     *
     * @param  list<string>  $menus
     * @param  list<int>  $pages
     */
    private function token(array $menus = [], array $pages = []): string
    {
        return app(PreviewShares::class)->workingDraft($this->staff, $menus, $pages)->getRawOriginal('token');
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
