<?php

namespace Tests\Feature;

use App\Http\Middleware\RepairLegacyPathEncoding;
use App\Models\LegacyImportUserMap;
use App\Models\User;
use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsPage;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\Messages\Models\PrivateConversationParticipant;
use Filagora\Messages\Models\PrivateMessage;
use Filagora\Messages\Models\PrivateMessageFolder;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class LegacyRedirectTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_showthread_redirects_by_legacy_thread_id(): void
    {
        [, $thread] = $this->seedForumThread();

        $this->get('/showthread.php?t=500')
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_legacy_redirect_logging_is_off_by_default(): void
    {
        [, $thread] = $this->seedForumThread();
        Log::spy();

        $this->get('/showthread.php?t=500')
            ->assertRedirect(route('threads.show', $thread));

        Log::shouldNotHaveReceived('info');
    }

    public function test_legacy_redirect_logging_can_be_enabled_via_config(): void
    {
        config(['services.legacy_redirect.logging_enabled' => true]);
        [, $thread] = $this->seedForumThread();
        Log::spy();

        $this->get('/showthread.php?t=500')
            ->assertRedirect(route('threads.show', $thread));

        Log::shouldHaveReceived('info')->once()->with('legacy.redirect', \Mockery::on(
            fn (array $context): bool => $context['resolution_outcome'] === 'redirect'
                && $context['matched_pattern'] === 'showthread.php'
        ));
    }

    public function test_showthread_prefers_legacy_post_id_over_thread_id(): void
    {
        [, $thread, $post] = $this->seedForumThread();
        [, $otherThread] = $this->seedForumThread([
            'legacy_vbulletin_thread_id' => 501,
            'slug' => 'other-thread',
        ], [
            'legacy_vbulletin_forum_id' => 121,
            'slug' => 'other-installation-help',
        ], [
            'legacy_post_id' => 9002,
        ]);

        $this->get('/showthread.php?p=9001&t=501')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    public function test_showthread_supports_raw_numeric_query_prefix(): void
    {
        [, $thread] = $this->seedForumThread();

        $this->get('/showthread.php?500-thread-title')
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_showthread_redirects_unknown_thread_to_forum_index(): void
    {
        $this->get('/showthread.php?t=999999')
            ->assertRedirect(route('forums.index'));
    }

    public function test_showthread_returns_not_found_for_malformed_input(): void
    {
        $this->get('/showthread.php?highlight=legacy')
            ->assertNotFound();
    }

    public function test_forumdisplay_redirects_by_legacy_forum_id_and_preserves_page(): void
    {
        [$forum] = $this->seedForumThread();

        $this->get('/forumdisplay.php?f=120&page=3')
            ->assertRedirect(route('forums.show', ['forum' => $forum, 'page' => 3]));
    }

    public function test_forumdisplay_supports_path_form(): void
    {
        [$forum] = $this->seedForumThread();

        $this->get('/forumdisplay.php/120-installation-help')
            ->assertRedirect(route('forums.show', $forum));
    }

    /**
     * vBulletin percent-encoded the RAW Windows-1252 bytes of a thread title
     * into its slug, so a curly apostrophe reached the wire as a lone `%92`.
     * That is never valid UTF-8, and Laravel's global ValidatePathEncoding
     * answered 400 before routing -- permanently breaking every bookmark and
     * search result for the 363 imported threads whose titles contain one.
     */
    public function test_showthread_resolves_slugs_carrying_windows_1252_escapes(): void
    {
        [, $thread] = $this->seedForumThread();

        $slugs = [
            'curly apostrophe (%92)' => 'HELP!-Canada-Post-module-doesn%92t-work-in-FRENCH',
            'registered sign (%AE)' => 'Congratulations!-installed-your-Zen-Cart%AE-E-Commerce-Solution',
            'e-acute (%E9)' => 'Acute-%E9-character-truncating-line',
            'curly quotes (%93 %94)' => 'Problem-%93Down-for-Maintenance%94-for-both-Home-and-Admin-Pages',
            'en dash (%96)' => 'Installation%96database-Setup-Problem',
            'trade mark (%99)' => 'Zen-Cart%99-Setup-System-Setup',
            'acute accent (%B4)' => 'I%B4m-Getting-These-Errors-When-I-Checkout',
            'pound sign (%A3)' => 'Currency-shows-%A3-instead-of-symbol',
        ];

        foreach ($slugs as $slug) {
            $this->get('/showthread.php/500-'.$slug)
                ->assertRedirect(route('threads.show', $thread));
        }
    }

    public function test_showthread_prefers_legacy_post_id_on_a_windows_1252_slug(): void
    {
        [, $thread, $post] = $this->seedForumThread();

        $this->get('/showthread.php/500-HELP!-module-doesn%92t-work?p=9001')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    public function test_forum_prefixed_showthread_resolves_a_windows_1252_slug(): void
    {
        [, $thread] = $this->seedForumThread();

        $this->get('/forum/showthread.php/500-module-doesn%92t-work')
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_forumdisplay_and_printthread_resolve_windows_1252_slugs(): void
    {
        [$forum, $thread] = $this->seedForumThread();

        $this->get('/forumdisplay.php/120-Caf%E9-Discussion')
            ->assertRedirect(route('forums.show', $forum));

        $this->get('/printthread.php/500-doesn%92t-work')
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_showpost_resolves_a_windows_1252_slug(): void
    {
        [, $thread, $post] = $this->seedForumThread();

        $this->get('/showpost.php/9001-Acute-%E9-character')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    /**
     * The repair rewrites only the path. The bare-query form carries the id in
     * QUERY_STRING with no `=`, and must still arrive byte-for-byte.
     */
    public function test_windows_1252_repair_leaves_the_raw_query_string_intact(): void
    {
        [, $thread] = $this->seedForumThread();

        $this->get('/showthread.php/500-doesn%92t-work?500-thread-title')
            ->assertRedirect(route('threads.show', $thread));
    }

    /**
     * The repair is scoped to the legacy vBulletin scripts. Nothing else on
     * this site has a legitimate reason to carry a non-UTF-8 path, so those
     * keep the framework's 400 rather than being silently rewritten.
     */
    public function test_non_legacy_paths_still_reject_malformed_encoding(): void
    {
        $this->get('/threads/500-doesn%92t-work')->assertStatus(400);
        $this->get('/some-cms-page-caf%E9')->assertStatus(400);
    }

    /**
     * The repair must rewrite only the offending escapes. Decoding the whole
     * path and re-encoding it per segment also works for the redirect, but it
     * promotes an encoded `%2F` to a real separator -- splitting one slug into
     * several segments and manufacturing the `.php`-after-`.php` shape the
     * vhost exists to reject. Assert the byte-level result, because the
     * redirect resolves either way and would not catch the regression.
     */
    public function test_windows_1252_repair_preserves_path_structure(): void
    {
        $cases = [
            // encoded slash stays encoded, so the slug stays one segment
            '/showthread.php/500-caf%E9%2Fadmin.php' => '/showthread.php/500-caf%C3%A9%2Fadmin.php',
            '/showthread.php/500-a%2F..%2Fb-caf%E9' => '/showthread.php/500-a%2F..%2Fb-caf%C3%A9',
            // an already-encoded percent must not be double-encoded
            '/showthread.php/500-50%25-off-caf%E9' => '/showthread.php/500-50%25-off-caf%C3%A9',
            // sub-delims and literal bytes are left exactly as they arrived
            '/showthread.php/500-HELP!-a+b-caf%E9' => '/showthread.php/500-HELP!-a+b-caf%C3%A9',
        ];

        foreach ($cases as $incoming => $expected) {
            $captured = null;

            (new RepairLegacyPathEncoding)->handle(
                new Request([], [], [], [], [], [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => $incoming,
                    'SCRIPT_NAME' => '/index.php',
                ]),
                function (Request $request) use (&$captured) {
                    $captured = $request->server->get('REQUEST_URI');

                    return new Response;
                },
            );

            $this->assertSame($expected, $captured);
            $this->assertSame(
                substr_count($incoming, '/'),
                substr_count((string) $captured, '/'),
                'repair changed the number of path separators',
            );
        }
    }

    public function test_search_redirects_supported_modes_to_canonical_routes(): void
    {
        $this->get('/search.php')->assertRedirect(route('search.index'));
        $this->get('/search.php?do=getnew')->assertRedirect(route('forums.unread'));
        $this->get('/search.php?do=getdaily')->assertRedirect(route('forums.today'));
        $this->get('/search.php?query=legacy import')->assertRedirect(route('search.index', ['q' => 'legacy import']));
    }

    public function test_downloads_file_redirects_to_plugin_detail_page(): void
    {
        $plugin = $this->seedPlugin();

        $this->get('/downloads.php?do=file&id=2431')
            ->assertRedirect(route('plugins.show', $plugin));
    }

    public function test_downloads_file_falls_back_to_real_plugin_id_when_no_legacy_match(): void
    {
        $type = PluginType::query()->create([
            'name' => 'No Legacy Id Type',
            'slug' => 'no-legacy-id-type',
            'is_active' => true,
            'position' => 20,
        ]);
        $owner = User::factory()->create();

        $plugin = Plugin::query()->create([
            'name' => 'New Plugin Without Legacy Id',
            'slug' => 'new-plugin-without-legacy-id',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->get('/downloads.php?do=file&id='.$plugin->id)
            ->assertRedirect(route('plugins.show', $plugin));
    }

    public function test_downloads_search_redirects_to_canonical_endpoint(): void
    {
        $this->get('/downloads.php?do=search&query=security%20scanner')
            ->assertRedirect(route('search.index', ['q' => 'security scanner']));
    }

    /**
     * do=versioncheck is the one mode this layer answers rather than redirects;
     * its clients cannot follow a Location header. Payload coverage lives in
     * LegacyPluginCheckTest.
     */
    public function test_downloads_versioncheck_is_answered_rather_than_redirected(): void
    {
        $this->markTestSkipped('Needs the profile version-check endpoint (profile slice 8c) before the old downloads.php URL can be cut over.');

        $this->get('/downloads.php?do=versioncheck&id=2431')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_downloads_unknown_file_and_plain_landing_redirect_to_plugins_index(): void
    {
        $this->get('/downloads.php?do=file&id=999999')
            ->assertRedirect(route('plugins.index'));

        $this->get('/downloads.php')
            ->assertRedirect(route('plugins.index'));
    }

    public function test_content_redirects_legacy_content_id_to_slug_page(): void
    {
        $page = CmsPage::query()->forceCreate([
            'legacy_vbulletin_content_id' => 7001,
            'slug' => 'legacy-content-page',
            'title' => 'Legacy Content Page',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/content.php?id=7001&s=session123')
            ->assertRedirect(route('cms.pages.show', ['slug' => $page->slug]));
    }

    public function test_content_redirects_legacy_content_id_to_custom_route_page(): void
    {
        CmsPage::query()->forceCreate([
            'legacy_vbulletin_content_id' => 7002,
            'slug' => 'about-company',
            'custom_route' => '/company/about',
            'title' => 'About Company',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/content.php/7002-about-company')
            ->assertRedirect('/company/about');
    }

    public function test_content_supports_raw_numeric_query_prefix_and_returns_not_found_for_missing_page(): void
    {
        CmsPage::query()->forceCreate([
            'legacy_vbulletin_content_id' => 7003,
            'slug' => 'raw-query-content',
            'title' => 'Raw Query Content',
            'status' => CmsPageStatus::PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/content.php?7003-raw-query-content')
            ->assertRedirect(route('cms.pages.show', ['slug' => 'raw-query-content']));

        $this->get('/content.php?id=999999')
            ->assertNotFound();
    }

    public function test_faq_returns_gone(): void
    {
        $this->get('/faq.php')->assertGone();
    }

    public function test_showpost_redirects_to_the_post_anchor(): void
    {
        [, $thread, $post] = $this->seedForumThread();

        $this->get('/showpost.php?p=9001')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    public function test_showpost_supports_the_numeric_path_form(): void
    {
        [, $thread, $post] = $this->seedForumThread();

        $this->get('/showpost.php/9001-some-post-title')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    public function test_showpost_redirects_unknown_post_to_forum_index(): void
    {
        $this->seedForumThread();

        $this->get('/showpost.php?p=424242')
            ->assertRedirect(route('forums.index'));
    }

    public function test_showpost_without_a_post_id_is_not_found(): void
    {
        $this->get('/showpost.php')->assertNotFound();
    }

    public function test_misc_whoposted_redirects_to_the_thread(): void
    {
        [, $thread] = $this->seedForumThread();

        $this->get('/misc.php?do=whoposted&t=500')
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_misc_whoposted_redirects_unknown_thread_to_forum_index(): void
    {
        $this->seedForumThread();

        $this->get('/misc.php?do=whoposted&t=424242')
            ->assertRedirect(route('forums.index'));
    }

    public function test_misc_other_modes_return_gone(): void
    {
        $this->get('/misc.php?do=showrules&f=120')->assertGone();
        $this->get('/misc.php?do=showsmilies')->assertGone();
        $this->get('/misc.php')->assertGone();
    }

    public function test_sendmessage_mailmember_redirects_to_mapped_member_profile(): void
    {
        $user = User::factory()->create();

        LegacyImportUserMap::query()->create([
            'legacy_user_id' => 211008,
            'user_id' => $user->id,
            'legacy_username' => 'richard7315',
            'legacy_email' => 'richard7315@example.com',
        ]);

        $this->get('/sendmessage.php?do=mailmember&u=211008')
            ->assertRedirect(route('members.show', ['user' => $user->id]));
    }

    public function test_sendmessage_contact_form_returns_gone(): void
    {
        // The shape almost all of this traffic arrives in: the bare form, or
        // the form carrying a harvested vBulletin session hash.
        $this->get('/sendmessage.php')->assertGone();
        $this->get('/sendmessage.php?s=dae5c220cd85ffc9de06efeef8e273fe')->assertGone();
        $this->get('/sendmessage.php?do=mailmember&u=999999')->assertGone();
    }

    public function test_forum_prefixed_legacy_urls_resolve_without_an_extra_hop(): void
    {
        [$forum, $thread, $post] = $this->seedForumThread();

        $this->get('/forum/showthread.php?t=500')
            ->assertRedirect(route('threads.show', $thread));

        $this->get('/forum/showpost.php?p=9001')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $this->get('/forum/forumdisplay.php?f=120')
            ->assertRedirect(route('forums.show', ['forum' => $forum]));

        $this->get('/forum/misc.php?do=whoposted&t=500')
            ->assertRedirect(route('threads.show', $thread));

        $this->get('/forum/faq.php')->assertGone();
    }

    public function test_wiki_urls_redirect_to_the_docs_site(): void
    {
        $this->get('/wiki/index.php/Table_products')
            ->assertRedirect('https://docs.zen-cart.com');

        $this->get('/wiki/search.php?search=coupons')
            ->assertRedirect('https://docs.zen-cart.com');

        $this->get('/wiki/')
            ->assertRedirect('https://docs.zen-cart.com');
    }

    public function test_wiki_redirect_target_is_configurable(): void
    {
        config(['services.legacy_redirect.docs_url' => 'https://docs.example.test/']);

        $this->get('/wiki/index.php/Main_Page')
            ->assertRedirect('https://docs.example.test');
    }

    public function test_member_redirects_to_mapped_member_profile(): void
    {
        $user = User::factory()->create();

        LegacyImportUserMap::query()->create([
            'legacy_user_id' => 211008,
            'user_id' => $user->id,
            'legacy_username' => 'richard7315',
            'legacy_email' => 'richard7315@example.com',
            'imported_at' => now(),
        ]);

        $this->get('/member.php?u=211008')
            ->assertRedirect(route('members.show', ['user' => $user->id]));

        $this->get('/member.php/211008-richard7315')
            ->assertRedirect(route('members.show', ['user' => $user->id]));

        $this->get('/member.php?211008-richard7315')
            ->assertRedirect(route('members.show', ['user' => $user->id]));
    }

    public function test_member_returns_gone_when_legacy_user_is_unmapped(): void
    {
        $this->get('/member.php?u=123')
            ->assertGone();

        $this->get('/member.php/123-legacy-user')
            ->assertGone();

        $this->get('/member.php?123-legacy-user')
            ->assertGone();
    }

    public function test_printthread_redirects_to_canonical_thread_url(): void
    {
        [, $thread] = $this->seedForumThread();

        $this->get('/printthread.php?t=500')
            ->assertRedirect(route('threads.show', $thread));

        $this->get('/printthread.php/500-legacy-thread')
            ->assertRedirect(route('threads.show', $thread));

        $this->get('/printthread.php?500-legacy-thread')
            ->assertRedirect(route('threads.show', $thread));
    }

    public function test_printthread_redirects_unknown_thread_to_forum_index_and_rejects_malformed_input(): void
    {
        $this->get('/printthread.php?t=999999')
            ->assertRedirect(route('forums.index'));

        $this->get('/printthread.php?printable=yes')
            ->assertNotFound();
    }

    public function test_newreply_redirects_to_canonical_thread_post_anchor(): void
    {
        [, $thread, $post] = $this->seedForumThread();

        $this->get('/newreply.php?p=9001')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $this->get('/newreply.php/9001-legacy-post')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);

        $this->get('/newreply.php?9001-legacy-post')
            ->assertRedirect(route('threads.show', $thread).'#post-'.$post->id);
    }

    public function test_newreply_redirects_unknown_post_to_forum_index_and_uses_gone_for_malformed_input(): void
    {
        $this->get('/newreply.php?p=999999')
            ->assertRedirect(route('forums.index'));

        $this->get('/newreply.php?quoteonly=yes')
            ->assertGone();
    }

    public function test_showcase_returns_gone(): void
    {
        $this->get('/showcase.php')
            ->assertGone();
    }

    public function test_external_blog_and_entry_legacy_routes_return_gone(): void
    {
        $this->get('/external.php?type=RSS2')->assertGone();
        $this->get('/blog.php')->assertGone();
        $this->get('/entry.php?b=123')->assertGone();
    }

    public function test_private_showpm_redirects_to_canonical_conversation_when_root_pmid_is_mapped(): void
    {
        [$conversation] = $this->seedPrivateConversation();

        $this->get('/private.php?do=showpm&pmid=306978')
            ->assertRedirect(route('messages.show', $conversation));

        $this->get('/private.php/306978-legacy-pm?do=showpm')
            ->assertRedirect(route('messages.show', $conversation));
    }

    public function test_private_showpm_and_other_modes_fall_back_to_messages_index_when_unmapped_or_unsupported(): void
    {
        $this->get('/private.php?do=showpm&pmid=999999')
            ->assertRedirect(route('messages.index'));

        $this->get('/private.php?do=newpm')
            ->assertRedirect(route('messages.index'));
    }

    public function test_forum_php_redirects_to_forum_index(): void
    {
        $this->get('/forum.php')->assertRedirect('/forums');
    }

    public function test_legacy_routes_with_styleid_return_gone(): void
    {
        $this->get('/showthread.php?t=500&styleid=4')->assertGone();
        $this->get('/external.php?type=RSS2&styleid=4')->assertGone();
        $this->get('/forum.php?styleid=4')->assertGone();
    }

    /**
     * @param  array<string, mixed>  $forumOverrides
     * @param  array<string, mixed>  $threadOverrides
     * @param  array<string, mixed>  $postOverrides
     * @return array{0: Forum, 1: Thread, 2: Post}
     */
    private function seedForumThread(array $threadOverrides = [], array $forumOverrides = [], array $postOverrides = []): array
    {
        $group = ForumGroup::query()->create([
            'name' => 'Legacy Group '.mt_rand(1, 100000),
            'slug' => 'legacy-group-'.mt_rand(1, 100000),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create(array_merge([
            'forum_group_id' => $group->id,
            'legacy_vbulletin_forum_id' => 120,
            'name' => 'Installation Help',
            'slug' => 'installation-help',
            'visibility' => ForumVisibility::PUBLIC,
            'is_active' => true,
        ], $forumOverrides));

        $author = User::factory()->create();

        $thread = Thread::query()->create(array_merge([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'legacy_vbulletin_thread_id' => 500,
            'title' => 'Legacy Thread',
            'slug' => 'legacy-thread',
        ], $threadOverrides));

        $post = Post::query()->create(array_merge([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'legacy_post_id' => 9001,
            'body' => 'Legacy starter post',
        ], $postOverrides));

        return [$forum, $thread, $post];
    }

    private function seedPlugin(): Plugin
    {
        $owner = User::factory()->create([
            'username' => 'legacy-plugin-owner',
        ]);

        $type = PluginType::query()->create([
            'name' => 'Legacy Type',
            'slug' => 'legacy-type',
            'is_active' => true,
            'position' => 10,
        ]);

        return Plugin::query()->create([
            'legacy_vbulletin_file_id' => 2431,
            'name' => 'Legacy Plugin',
            'slug' => 'legacy-plugin',
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
    }

    /**
     * @return array{0: PrivateConversation, 1: User, 2: User}
     */
    private function seedPrivateConversation(): array
    {
        $sender = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $recipient = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $conversation = PrivateConversation::query()->forceCreate([
            'legacy_root_pmid' => 306978,
            'created_by_id' => $sender->id,
            'subject' => 'Legacy PM Subject',
        ]);

        PrivateMessage::query()->forceCreate([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'legacy_pmtext_id' => 400001,
            'body' => 'Legacy PM body',
        ]);

        $senderSent = PrivateMessageFolder::query()->create([
            'user_id' => $sender->id,
            'name' => 'Sent',
            'slug' => 'sent',
            'is_system' => true,
        ]);

        $recipientInbox = PrivateMessageFolder::query()->create([
            'user_id' => $recipient->id,
            'name' => 'Inbox',
            'slug' => 'inbox',
            'is_system' => true,
        ]);

        PrivateConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'folder_id' => $senderSent->id,
            'is_read' => true,
            'last_read_at' => now(),
        ]);

        PrivateConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $recipient->id,
            'folder_id' => $recipientInbox->id,
            'is_read' => false,
        ]);

        return [$conversation, $sender, $recipient];
    }
}
