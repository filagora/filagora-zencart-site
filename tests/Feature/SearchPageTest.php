<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PluginSearchRankingService;
use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsPage;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\Plugins\Search\SyncReleaseDocuments;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_header_search_returns_mixed_scout_results_with_source_notes(): void
    {
        $user = User::factory()->create();

        $forumGroup = ForumGroup::query()->create([
            'name' => 'Needle Group',
            'slug' => 'needle-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Needle Forum',
            'slug' => 'needle-forum',
            'description' => 'Forum entry for needle search coverage.',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Needle Thread Title',
            'slug' => 'needle-thread-title',
        ]);

        $pluginType = PluginType::query()->create([
            'name' => 'Needle Plugin Type',
            'slug' => 'needle-plugin-type',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Needle Plugin',
            'slug' => 'needle-plugin',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/needle.zip',
            'zip_filename' => 'needle.zip',
            'changelog' => 'Needle release notes.',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $cmsPage = CmsPage::query()->create([
            'slug' => 'needle-page',
            'title' => 'Needle CMS Page',
            'excerpt' => 'Needle CMS excerpt.',
            'status' => CmsPageStatus::PUBLISHED->value,
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->get('/search?q=needle');

        $response->assertOk();
        $response->assertSeeText('Results for');
        $response->assertSeeText('needle');

        $response->assertSee('Needle Forum');
        $response->assertSee(route('forums.show', $forum), false);
        $response->assertSee('Source: Forum');

        $response->assertSee('Needle Thread Title');
        $response->assertSee(route('threads.show', $thread), false);
        $response->assertSee('Source: Thread');

        $response->assertSee('Needle Plugin 1.0.0');
        $response->assertSee(route('plugins.show', $plugin), false);
        $response->assertSee('Source: Plugin Release');

        $response->assertSee('Needle CMS Page');
        $response->assertSee(route('cms.pages.show', ['slug' => 'needle-page']), false);
        $response->assertSee('Source: CMS Page');
    }

    public function test_type_filter_narrows_results_to_the_selected_section(): void
    {
        $user = User::factory()->create();

        $forumGroup = ForumGroup::query()->create([
            'name' => 'Needle Group',
            'slug' => 'needle-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Needle Forum',
            'slug' => 'needle-forum',
            'is_active' => true,
        ]);

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Needle Thread Title',
            'slug' => 'needle-thread-title',
        ]);

        $pluginType = PluginType::query()->create([
            'name' => 'Needle Plugin Type',
            'slug' => 'needle-plugin-type',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Needle Plugin',
            'slug' => 'needle-plugin',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/needle.zip',
            'zip_filename' => 'needle.zip',
            'changelog' => 'Needle release notes.',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        CmsPage::query()->create([
            'slug' => 'needle-page',
            'title' => 'Needle CMS Page',
            'excerpt' => 'Needle CMS excerpt.',
            'status' => CmsPageStatus::PUBLISHED->value,
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->get('/search?q=needle&type=plugin_release');

        $response->assertOk();
        $response->assertSee('Needle Plugin 1.0.0');
        $response->assertSee(route('plugins.show', $plugin), false);
        $response->assertSee('Source: Plugin Release');

        $response->assertDontSee('Needle Forum');
        $response->assertDontSee('Source: Forum');
        $response->assertDontSee('Needle Thread Title');
        $response->assertDontSee('Source: Thread');
        $response->assertDontSee('Needle CMS Page');
        $response->assertDontSee('Source: CMS Page');
    }

    public function test_unknown_type_value_behaves_like_no_filter(): void
    {
        $user = User::factory()->create();

        $forumGroup = ForumGroup::query()->create([
            'name' => 'Needle Group',
            'slug' => 'needle-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Needle Forum',
            'slug' => 'needle-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Needle Thread Title',
            'slug' => 'needle-thread-title',
        ]);

        $response = $this->get('/search?q=needle&type=totally-bogus');

        $response->assertOk();
        $response->assertSee('Needle Forum');
        $response->assertSee(route('forums.show', $forum), false);
        $response->assertSee('Needle Thread Title');
        $response->assertSee(route('threads.show', $thread), false);
    }

    public function test_type_filter_with_no_matches_shows_empty_state_without_falling_back(): void
    {
        $user = User::factory()->create();

        $forumGroup = ForumGroup::query()->create([
            'name' => 'Needle Group',
            'slug' => 'needle-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Needle Forum',
            'slug' => 'needle-forum',
            'is_active' => true,
        ]);

        Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Needle Thread Title',
            'slug' => 'needle-thread-title',
        ]);

        $response = $this->get('/search?q=needle&type=plugin_release');

        $response->assertOk();
        $response->assertSee('No results found.');
        $response->assertDontSee('Needle Forum');
        $response->assertDontSee('Source: Forum');
        $response->assertDontSee('Needle Thread Title');
        $response->assertDontSee('Source: Thread');

        // Pills for populated types stay visible even while filtered elsewhere,
        // so switching to a type that does have matches is one click away.
        $response->assertSee('Forums (1)');
        $response->assertSee('Threads (1)');
        $response->assertSee('Plugins (0)');
    }

    public function test_search_filters_out_inactive_forums_threads_plugins_and_unpublished_cms_content(): void
    {
        $user = User::factory()->create();

        $group = ForumGroup::query()->create([
            'name' => 'Filter Group',
            'slug' => 'filter-group',
            'is_active' => true,
        ]);

        $inactiveForum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Filtered Forum',
            'slug' => 'filtered-forum',
            'description' => 'Should not appear in search',
            'is_active' => false,
        ]);

        $inactiveThread = Thread::query()->create([
            'forum_id' => $inactiveForum->id,
            'author_id' => $user->id,
            'title' => 'Filtered Thread',
            'slug' => 'filtered-thread',
        ]);

        $inactiveType = PluginType::query()->create([
            'name' => 'Inactive Type',
            'slug' => 'inactive-type',
            'is_active' => false,
        ]);

        $inactivePlugin = Plugin::query()->create([
            'name' => 'Filtered Plugin',
            'slug' => 'filtered-plugin',
            'plugin_type_id' => $inactiveType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $inactivePlugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/filtered.zip',
            'zip_filename' => 'filtered.zip',
            'changelog' => 'Filtered changelog',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $draftPage = CmsPage::query()->create([
            'slug' => 'filtered-draft-page',
            'title' => 'Filtered Draft Page',
            'status' => CmsPageStatus::DRAFT->value,
        ]);

        $futurePage = CmsPage::query()->create([
            'slug' => 'filtered-future-page',
            'title' => 'Filtered Future Page',
            'status' => CmsPageStatus::PUBLISHED->value,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get('/search?q=filtered');

        $response->assertOk();
        $response->assertDontSee('Filtered Forum');
        $response->assertDontSee(route('forums.show', $inactiveForum), false);
        $response->assertDontSee('Filtered Thread');
        $response->assertDontSee(route('threads.show', $inactiveThread), false);
        $response->assertDontSee('Filtered Plugin 1.0.0');
        $response->assertDontSee(route('plugins.show', $inactivePlugin), false);
        $response->assertDontSee('Filtered Draft Page');
        $response->assertDontSee('Filtered Future Page');
    }

    public function test_empty_query_shows_prompt_and_no_results_sections(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee('Enter a search term to find content across forums, plugins, and CMS pages.');
        $response->assertDontSee('Results for');
        $response->assertDontSee('No results found.');
    }

    public function test_short_query_shows_minimum_length_message_without_running_search(): void
    {
        $response = $this->get('/search?q=a');

        $response->assertOk();
        $response->assertSee('Enter at least 2 characters to search.');
        $response->assertDontSee('No results found.');
    }

    public function test_query_is_normalized_before_searching(): void
    {
        $forumGroup = ForumGroup::query()->create([
            'name' => 'Normalized Group',
            'slug' => 'normalized-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $forumGroup->id,
            'name' => 'Needle Normalized Forum',
            'slug' => 'needle-normalized-forum',
            'description' => 'Normalization should still find this forum.',
            'is_active' => true,
        ]);

        $response = $this->get('/search?q=%20%20%3Cb%3Eneedle%3C%2Fb%3E%20%20');

        $response->assertOk();
        $response->assertSeeText('Results for');
        $response->assertSeeText('needle');
        $response->assertDontSee('<b>needle</b>', false);
        $response->assertSee('Needle Normalized Forum');
        $response->assertSee(route('forums.show', $forum), false);
    }

    public function test_plugin_release_results_can_match_plugin_name_from_indexed_field(): void
    {
        $user = User::factory()->create();

        $pluginType = PluginType::query()->create([
            'name' => 'General Type',
            'slug' => 'general-type',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'NameMatch Plugin',
            'slug' => 'name-match-plugin',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '9.9.9',
            'internal_version' => 1,
            'zip_path' => 'plugins/name-match.zip',
            'zip_filename' => 'release-file.zip',
            'changelog' => 'Unrelated changelog text.',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $response = $this->get('/search?q=namematch');

        $response->assertOk();
        $response->assertSee('NameMatch Plugin 9.9.9');
        $response->assertSee(route('plugins.show', $plugin), false);
        $response->assertSee('Source: Plugin Release');
    }

    /**
     * Regression: the plugin-release search (used by both the header search
     * and, via the shared PluginSearchRankingService, the dedicated plugin
     * search page) used to only check the plugin's name/slug and the
     * release's version/changelog - a query matching only the plugin's
     * description was invisible here even though it matched on the plugins
     * page (which queried a separate index/query with description search).
     */
    public function test_plugin_release_results_show_encapsulated_badge_and_supported_versions(): void
    {
        $user = User::factory()->create();

        $pluginType = PluginType::query()->create([
            'name' => 'Payments',
            'slug' => 'payments',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Badge Plugin',
            'slug' => 'badge-plugin',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '4.5.6',
            'internal_version' => 1,
            'zip_path' => 'plugins/badge-plugin.zip',
            'zip_filename' => 'badge-plugin.zip',
            'changelog' => 'Release with metadata.',
            'encapsulated' => 'yes',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $versionA = ZcVersion::query()->create([
            'version' => '2.1.0',
            'is_active' => true,
            'position' => 1,
        ]);
        $versionB = ZcVersion::query()->create([
            'version' => '2.2.0',
            'is_active' => true,
            'position' => 2,
        ]);

        $release->zcVersions()->sync([$versionA->id, $versionB->id]);

        $response = $this->get('/search?q=badge');

        $response->assertOk();
        $response->assertSee('Badge Plugin 4.5.6');
        $response->assertSee('Encapsulated');
        $response->assertSee('ZC versions: 2.1.0, 2.2.0');
        $response->assertSee(route('plugins.show', $plugin), false);
    }

    public function test_plugin_search_row_uses_latest_release_compatibility_not_an_older_matching_release(): void
    {
        $user = User::factory()->create();

        $pluginType = PluginType::query()->create([
            'name' => 'Layout',
            'slug' => 'layout',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Slideshow Manager',
            'slug' => 'slideshow-manager',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $oldZcVersion = ZcVersion::query()->create([
            'version' => '1.5.1',
            'is_active' => true,
            'position' => 1,
        ]);
        $newZcVersion = ZcVersion::query()->create([
            'version' => '1.5.8',
            'is_active' => true,
            'position' => 2,
        ]);

        $oldRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '2.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/slideshow-20.zip',
            'zip_filename' => 'slideshow-20.zip',
            'changelog' => 'Original slideshow release.',
            'encapsulated' => 'unknown',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $oldRelease->zcVersions()->sync([$oldZcVersion->id]);
        // Make the stale release look freshly touched: the pre-fix ranker
        // tie-broke on updated_at and would have surfaced this row.
        $oldRelease->forceFill(['updated_at' => now()])->saveQuietly();

        $latestRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '3.0.0',
            'internal_version' => 2,
            'zip_path' => 'plugins/slideshow-300.zip',
            'zip_filename' => 'slideshow-300.zip',
            'changelog' => 'Zen Cart compatibility refresh.',
            'encapsulated' => 'yes',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $latestRelease->zcVersions()->sync([$newZcVersion->id]);
        $latestRelease->forceFill(['updated_at' => now()->subYear()])->saveQuietly();

        $response = $this->get('/search?q=slideshow');

        $response->assertOk();
        $response->assertSee('Slideshow Manager 3.0.0');
        $response->assertSee('ZC versions: 1.5.8');
        $response->assertDontSee('Slideshow Manager 2.0');
        $response->assertDontSee('ZC versions: 1.5.1');
    }

    public function test_search_page_surfaces_a_plugin_by_zen_cart_version_token(): void
    {
        $user = User::factory()->create();

        $pluginType = PluginType::query()->create([
            'name' => 'Shipping',
            'slug' => 'shipping',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Carrier Rates',
            'slug' => 'carrier-rates',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '2.3.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/carrier-rates.zip',
            'zip_filename' => 'carrier-rates.zip',
            'changelog' => 'Rate table refresh.',
            'encapsulated' => 'yes',
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $zc = ZcVersion::query()->create(['version' => '1.5.7', 'is_active' => true, 'position' => 1]);
        $release->zcVersions()->sync([$zc->id]);
        SyncReleaseDocuments::dispatch('plugin', $plugin->id);

        $response = $this->get('/search?q=carrier 1.5.7');

        $response->assertOk();
        $response->assertSee('Carrier Rates 2.3.0');
        $response->assertSee('ZC versions: 1.5.7');
    }

    public function test_zen_cart_version_token_does_not_surface_an_archived_release(): void
    {
        $user = User::factory()->create();

        $pluginType = PluginType::query()->create([
            'name' => 'Shipping',
            'slug' => 'shipping',
            'is_active' => true,
        ]);

        $plugin = Plugin::query()->create([
            'name' => 'Carrier Rates',
            'slug' => 'carrier-rates',
            'plugin_type_id' => $pluginType->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $zc = ZcVersion::query()->create(['version' => '1.5.7', 'is_active' => true, 'position' => 1]);

        // An archived release that is tagged 1.5.7-compatible.
        $archivedRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/carrier-rates-100.zip',
            'zip_filename' => 'carrier-rates-100.zip',
            'changelog' => 'Legacy build.',
            'encapsulated' => 'yes',
            'status' => PluginStatus::ARCHIVED->value,
        ]);
        $archivedRelease->zcVersions()->sync([$zc->id]);

        // The current, active release does NOT support 1.5.7.
        $newZc = ZcVersion::query()->create(['version' => '2.0.0', 'is_active' => true, 'position' => 2]);
        $activeRelease = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $user->id,
            'version' => '3.0.0',
            'internal_version' => 2,
            'zip_path' => 'plugins/carrier-rates-300.zip',
            'zip_filename' => 'carrier-rates-300.zip',
            'changelog' => 'Modern build.',
            'encapsulated' => 'yes',
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $activeRelease->zcVersions()->sync([$newZc->id]);

        $response = $this->get('/search?q=carrier 1.5.7');

        $response->assertOk();
        // The archived 1.0.0 release is never the face; if the plugin appears
        // at all it is via its active 3.0.0 release.
        $response->assertDontSee('Carrier Rates 1.0.0');
    }

    public function test_thread_results_can_match_forum_name_from_indexed_field(): void
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Search Group',
            'slug' => 'search-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Billing Insights Forum',
            'slug' => 'billing-insights-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread title without keyword',
            'slug' => 'thread-title-without-keyword',
        ]);

        $response = $this->get('/search?q=insights');

        $response->assertOk();
        $response->assertSee('Thread title without keyword');
        $response->assertSee(route('threads.show', $thread), false);
        $response->assertSee('Source: Thread');
    }

    public function test_thread_results_show_forum_and_activity_context(): void
    {
        $author = User::factory()->create(['username' => 'context-starter']);
        $lastPoster = User::factory()->create(['username' => 'context-replier']);
        $group = ForumGroup::query()->create([
            'name' => 'Context Group',
            'slug' => 'context-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Context Forum',
            'slug' => 'context-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread context search result',
            'slug' => 'thread-context-search-result',
            'reply_count' => 4,
            'view_count' => 128,
            'last_post_author_id' => $lastPoster->id,
            'created_at' => now()->subDays(12),
            'last_post_at' => now()->subDays(3),
        ]);

        $response = $this->get('/search?q=context');

        $response->assertOk();
        $response->assertSee('Thread context search result');
        $response->assertSee('In forum: Context Forum');
        $response->assertSee('Started');
        $response->assertSee('4 replies');
        $response->assertSee('128 views');
        $response->assertSee('Last post');

        // Thread starter's username links to their member profile.
        $response->assertSee(
            '<a class="text-[#5c6570] hover:text-[#8f3f1f] hover:underline" href="'.route('members.show', ['user' => $author->id]).'">context-starter</a>',
            false
        );

        // Thread creation date links to the thread's first post.
        $response->assertSee(
            '<a class="text-[#5c6570] hover:text-[#8f3f1f] hover:underline" href="'.route('threads.show', $thread).'">'.$thread->created_at?->format('d M Y').'</a>',
            false
        );

        // Last post date links to the page containing the latest post.
        $response->assertSee(
            '<a class="text-[#5c6570] hover:text-[#8f3f1f] hover:underline" href="'.route('threads.show', $thread).'">'.$thread->last_post_at?->format('d M Y').'</a>',
            false
        );

        // Last poster's username links to their member profile.
        $response->assertSee(
            '<a class="text-[#5c6570] hover:text-[#8f3f1f] hover:underline" href="'.route('members.show', ['user' => $lastPoster->id]).'">context-replier</a>',
            false
        );
    }

    public function test_thread_results_link_last_post_date_to_its_page_for_long_threads(): void
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Pagination Group',
            'slug' => 'pagination-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Pagination Forum',
            'slug' => 'pagination-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread pagination search result',
            'slug' => 'thread-pagination-search-result',
            'reply_count' => 25,
            'last_post_at' => now(),
        ]);

        $response = $this->get('/search?q=pagination');

        $response->assertOk();
        $response->assertSee(route('threads.show', ['thread' => $thread, 'page' => 2]), false);
    }

    public function test_thread_results_deep_link_to_the_matching_posts_actual_page(): void
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Deep Link Group',
            'slug' => 'deep-link-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Deep Link Forum',
            'slug' => 'deep-link-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread title without the matching term',
            'slug' => 'thread-title-without-the-matching-term',
        ]);

        // 21 filler replies before the matching one, so it lands on page 2
        // (THREAD_POSTS_PER_PAGE = 20): position 22 => ceil(22 / 20) = page 2.
        for ($i = 0; $i < 21; $i++) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => 'Filler reply number '.$i,
            ]);
        }

        $matchingPost = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'This is the reply with paginatedreplyterm in it.',
        ]);

        $response = $this->get('/search?q=paginatedreplyterm');

        $response->assertOk();
        $response->assertSee(
            route('threads.show', ['thread' => $thread, 'page' => 2]).'#post-'.$matchingPost->id,
            false
        );
    }

    public function test_thread_results_use_bare_url_and_no_excerpt_when_match_is_not_in_a_post(): void
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Title Only Group',
            'slug' => 'title-only-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Title Only Forum',
            'slug' => 'title-only-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread titleonlymatchterm in the subject',
            'slug' => 'thread-titleonlymatchterm-in-the-subject',
        ]);

        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'This reply does not contain the search term at all.',
        ]);

        $response = $this->get('/search?q=titleonlymatchterm');

        $response->assertOk();
        $response->assertSee(route('threads.show', $thread), false);
        $response->assertDontSee(route('threads.show', $thread).'#post-', false);
        $response->assertDontSee('This reply does not contain the search term at all.');
    }

    public function test_thread_results_can_match_post_body_without_rendering_post_results(): void
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Post Search Group',
            'slug' => 'post-search-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Post Search Forum',
            'slug' => 'post-search-forum',
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Thread title without the special term',
            'slug' => 'thread-title-without-the-special-term',
        ]);

        $post = Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'This reply contains ultrauniquephrase for boosting thread search results.',
        ]);

        $response = $this->get('/search?q=ultrauniquephrase');

        $response->assertOk();
        $response->assertSee('Thread title without the special term');
        $response->assertSee(route('threads.show', $thread), false);
        $response->assertSee('Source: Thread');
        $response->assertDontSee('Source: Post');

        // Deep-links to the actual matching post instead of just page 1.
        $response->assertSee('#post-'.$post->id, false);
        $response->assertSee('This reply contains ultrauniquephrase');
    }

    public function test_guest_search_excludes_registered_forum_and_thread_results(): void
    {
        $author = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Registered Group',
            'slug' => 'registered-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Members Lounge',
            'slug' => 'members-lounge',
            'description' => 'Members only discussions.',
            'visibility' => ForumVisibility::REGISTERED,
            'is_active' => true,
        ]);

        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Members Lounge Roadmap',
            'slug' => 'members-lounge-roadmap',
        ]);

        $response = $this->get('/search?q=members lounge');

        $response->assertOk();
        $response->assertDontSee('Members Lounge');
        $response->assertDontSee(route('forums.show', $forum), false);
        $response->assertDontSee('Members Lounge Roadmap');
        $response->assertDontSee(route('threads.show', $thread), false);
    }

    public function test_search_only_returns_owned_threads_from_staff_author_only_forums(): void
    {
        $viewer = User::factory()->create();
        $otherAuthor = User::factory()->create();
        $group = ForumGroup::query()->create([
            'name' => 'Author Group',
            'slug' => 'author-group',
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Author Cases',
            'slug' => 'author-cases',
            'visibility' => ForumVisibility::STAFF_AUTHOR_ONLY,
            'show_when_empty' => false,
            'is_active' => true,
        ]);

        $ownedThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $viewer->id,
            'title' => 'Private case alpha',
            'slug' => 'private-case-alpha',
        ]);

        $otherThread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $otherAuthor->id,
            'title' => 'Private case beta',
            'slug' => 'private-case-beta',
        ]);

        $response = $this->actingAs($viewer)->get('/search?q=private case');

        $response->assertOk();
        $response->assertSee('Private case alpha');
        $response->assertSee(route('threads.show', $ownedThread), false);
        $response->assertDontSee('Private case beta');
        $response->assertDontSee(route('threads.show', $otherThread), false);
    }
}
