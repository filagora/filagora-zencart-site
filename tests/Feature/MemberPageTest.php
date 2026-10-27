<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Support\LocalTime;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Messages\Models\IgnoredUser;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\ZenCart\Enums\Encapsulated;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class MemberPageTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_members_index_page_is_available(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('members.index'));

        $response->assertOk();
        $response->assertSee('Members / Contact');
        $response->assertSee('Contacts');
        $response->assertSee('All Members');
        $response->assertSee('<main id="main-content" tabindex="-1" class="bg-white">', false);
        $response->assertDontSee('rounded-[14px] border border-[#cabca8] bg-white', false);
    }

    public function test_members_index_shows_authenticated_users_contacts(): void
    {
        $user = User::factory()->create(['username' => 'owner']);
        $contactUser = User::factory()->create(['username' => 'contact_one']);

        Contact::query()->create([
            'user_id' => $user->id,
            'contact_user_id' => $contactUser->id,
        ]);

        $response = $this->actingAs($user)->get(route('members.index'));

        $response->assertOk();
        $response->assertSee('contact_one');
    }

    public function test_all_members_list_is_alphabetical_and_paginated(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);

        foreach (range(1, 26) as $index) {
            User::factory()->create([
                'username' => sprintf('member%03d', $index),
                'name' => sprintf('Member %03d', $index),
                'email' => sprintf('member%03d@example.com', $index),
            ]);
        }

        $firstPage = $this->actingAs($viewer)->get(route('members.index'));
        $firstPage->assertOk();
        $firstPage->assertSeeInOrder(['member001', 'member002', 'member003']);
        $firstPage->assertDontSee('member026');
        $firstPage->assertSee('?page=2');

        $secondPage = $this->actingAs($viewer)->get(route('members.index', ['page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertSee('member026');
    }

    public function test_all_members_list_is_searchable(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        User::factory()->create(['username' => 'zulu_member', 'name' => 'Alpha']);
        User::factory()->create(['username' => 'bravo_member', 'name' => 'Bravo']);

        $response = $this->actingAs($viewer)->get(route('members.index', ['q' => 'alpha']));

        $response->assertOk();
        $response->assertSee('zulu_member');
        $response->assertDontSee('bravo_member');
    }

    public function test_all_members_search_exact_match_only_returns_literal_matches(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $kim = User::factory()->create(['username' => 'kim', 'name' => 'Kimball Jones']);
        $kimberley = User::factory()->create(['username' => 'kimberley', 'name' => 'Kimberley Doe']);
        $hakim = User::factory()->create(['username' => 'hakim', 'name' => 'Hakim Reed']);
        $namedKim = User::factory()->create(['username' => 'k_smith', 'name' => 'Kim']);

        // Default (fuzzy) search matches every username containing "kim".
        $this->actingAs($viewer)
            ->get(route('members.index', ['q' => 'kim']))
            ->assertOk()
            ->assertSee(route('members.show', $kimberley), false)
            ->assertSee(route('members.show', $hakim), false);

        // Exact match returns only members whose username or name is literally "kim".
        $response = $this->actingAs($viewer)->get(route('members.index', ['q' => 'KIM', 'exact' => '1']));
        $response->assertOk();
        $response->assertSee(route('members.show', $kim), false);
        $response->assertSee(route('members.show', $namedKim), false);
        $response->assertDontSee(route('members.show', $kimberley), false);
        $response->assertDontSee(route('members.show', $hakim), false);
        $response->assertSee('name="exact"', false);
        $response->assertSee('id="members-search-exact" checked>', false);
    }

    public function test_all_members_search_uses_database_filtering_even_when_scout_is_meilisearch(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        User::factory()->create(['username' => 'fallback_alpha', 'name' => 'Fallback Alpha']);
        User::factory()->create(['username' => 'fallback_bravo', 'name' => 'Fallback Bravo']);

        config()->set('scout.driver', 'meilisearch');
        config()->set('scout.meilisearch.host', 'http://127.0.0.1:9');
        config()->set('scout.meilisearch.key', null);

        $response = $this->actingAs($viewer)->get(route('members.index', ['q' => 'alpha']));

        $response->assertOk();
        $response->assertSee('fallback_alpha');
        $response->assertDontSee('fallback_bravo');
    }

    public function test_members_index_does_not_render_other_users_email_addresses(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $member = User::factory()->create([
            'username' => 'directory_member',
            'name' => 'Directory Member',
            'email' => 'directory-member@example.com',
        ]);

        Contact::query()->create([
            'user_id' => $viewer->id,
            'contact_user_id' => $member->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.index'));

        $response->assertOk();
        $response->assertSee('directory_member');
        $response->assertDontSee('directory-member@example.com');
        $response->assertDontSee('Search by username, name, or email');
        $response->assertSee('Search by username or name');
    }

    public function test_all_members_search_does_not_match_email_addresses(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        User::factory()->create([
            'username' => 'alpha_member',
            'name' => 'Alpha Member',
            'email' => 'alpha.member@example.com',
        ]);
        User::factory()->create([
            'username' => 'bravo_member',
            'name' => 'Bravo Member',
            'email' => 'bravo.member@example.com',
        ]);

        $response = $this->actingAs($viewer)->get(route('members.index', ['q' => 'alpha.member@example.com']));

        $response->assertOk();
        $response->assertDontSee('alpha_member');
        $response->assertDontSee('bravo_member');
        $response->assertSee('No members found.');
    }

    public function test_all_members_list_excludes_users_who_disallow_private_messages(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        User::factory()->create([
            'username' => 'pm_allowed_user',
            'allow_private_messages' => true,
        ]);
        User::factory()->create([
            'username' => 'pm_blocked_user',
            'allow_private_messages' => false,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.index'));

        $response->assertOk();
        $response->assertSee('pm_allowed_user');
        $response->assertDontSee('pm_blocked_user');
    }

    public function test_members_index_shows_pm_link_to_compose_for_other_members(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create([
            'username' => 'target_user',
            'allow_private_messages' => true,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.index'));

        $response->assertOk();
        $response->assertSee(route('messages.compose', ['to' => $target->username]));
    }

    public function test_member_profile_shows_message_button_for_other_messageable_members(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create([
            'username' => 'target_user',
            'allow_private_messages' => true,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $target));

        $response->assertOk();
        $response->assertSee('Message');
        $response->assertSee(route('messages.compose', ['to' => 'target_user']));
    }

    public function test_member_profile_hides_message_button_for_self_or_when_pm_disabled(): void
    {
        $viewer = User::factory()->create([
            'username' => 'viewer',
            'allow_private_messages' => true,
        ]);
        $blockedTarget = User::factory()->create([
            'username' => 'blocked_target',
            'allow_private_messages' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('members.show', $viewer))
            ->assertOk()
            ->assertDontSee(route('messages.compose', ['to' => 'viewer']));

        $this->actingAs($viewer)
            ->get(route('members.show', $blockedTarget))
            ->assertOk()
            ->assertDontSee(route('messages.compose', ['to' => 'blocked_target']));
    }

    public function test_member_profile_shows_email_link_to_staff_only(): void
    {
        $staff = User::factory()->create(['username' => 'staff_member']);
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $regularViewer = User::factory()->create(['username' => 'regular_viewer']);
        $target = User::factory()->create([
            'username' => 'target_user',
            'email' => 'target@example.test',
        ]);

        $this->actingAs($staff)
            ->get(route('members.show', $target))
            ->assertOk()
            ->assertSee('mailto:target@example.test', false);

        $this->actingAs($regularViewer)
            ->get(route('members.show', $target))
            ->assertOk()
            ->assertDontSee('mailto:target@example.test', false);

        $this->actingAs($staff)
            ->get(route('members.show', $staff))
            ->assertOk()
            ->assertDontSee('mailto:'.$staff->email, false);
    }

    public function test_member_profile_shows_activity_links_and_ignore_action(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create([
            'username' => 'target_user',
            'allow_private_messages' => true,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $target));

        $response->assertOk();
        $response->assertSee('Find latest posts');
        $response->assertSee(route('members.posts', $target));
        $response->assertSee('Find latest started threads');
        $response->assertSee(route('members.threads', $target));
        $response->assertSee('Add to ignore list');
    }

    public function test_member_profile_always_shows_location_row(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create([
            'username' => 'target_user',
            'location' => null,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $target));

        $response->assertOk();
        $response->assertSeeText('Location:');
        $response->assertSeeText('Not specified');
    }

    public function test_member_profile_falls_back_to_legacy_location_when_current_profile_field_is_blank(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create([
            'username' => 'swguy',
            'name' => 'swguy',
            'location' => null,
        ]);

        DB::table('legacy_import_user_maps')->insert([
            'legacy_user_id' => 22320,
            'user_id' => $target->id,
            'legacy_username' => 'swguy',
            'legacy_email' => 'help@thatsoftwareguy.com',
            'legacy_primary_group_id' => 6,
            'legacy_member_group_ids' => '2,7,32,34',
            'legacy_is_banned' => false,
            'imported_at' => now(),
        ]);

        if (! Schema::hasTable('vb_userfield')) {
            Schema::create('vb_userfield', function (Blueprint $table): void {
                $table->unsignedInteger('userid')->primary();
                $table->mediumText('temp')->nullable();
                $table->mediumText('field1')->nullable();
                $table->mediumText('field2')->nullable();
                $table->mediumText('field3')->nullable();
                $table->mediumText('field4')->nullable();
                $table->mediumText('field5')->nullable();
            });
        }

        DB::table('vb_userfield')->insert([
            'userid' => 22320,
            'field1' => 'Legacy biography',
            'field2' => 'Tampa Bay, Florida',
            'field3' => 'Helping you make more money.',
            'field4' => 'With a name like That Software Guy, you have to ask?',
            'field5' => 'Scott',
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $target));

        $response->assertOk();
        $response->assertSeeText('Location:');
        $response->assertSeeText('Tampa Bay, Florida');
        $response->assertSeeText('Real Name:');
        $response->assertSeeText('Scott');
    }

    public function test_member_profile_falls_back_to_legacy_last_activity_when_no_session_exists(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create([
            'username' => 'swguy',
        ]);
        $legacyLastActivity = 1782118312;

        DB::table('legacy_import_user_maps')->insert([
            'legacy_user_id' => 22320,
            'user_id' => $target->id,
            'legacy_username' => 'swguy',
            'legacy_email' => 'help@thatsoftwareguy.com',
            'legacy_primary_group_id' => 6,
            'legacy_member_group_ids' => '2,7,32,34',
            'legacy_is_banned' => false,
            'imported_at' => now(),
        ]);

        if (! Schema::hasTable('vb_user')) {
            Schema::create('vb_user', function (Blueprint $table): void {
                $table->unsignedInteger('userid')->primary();
                $table->unsignedInteger('lastactivity')->default(0);
            });
        }

        DB::table('vb_user')->insert([
            'userid' => 22320,
            'lastactivity' => $legacyLastActivity,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $target));

        $response->assertOk();
        $response->assertSeeText('Last Activity:');
        $response->assertSeeText(LocalTime::format(Carbon::createFromTimestamp($legacyLastActivity)));
        $response->assertDontSeeText('No recent session activity recorded');
    }

    public function test_authenticated_user_can_add_contact_from_members_list(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create(['username' => 'target_user']);

        $response = $this->actingAs($viewer)->post(route('members.contacts.store', $target), [
            'return_q' => 'target',
            'return_page' => 2,
        ]);

        $response->assertRedirect(route('members.index', ['q' => 'target', 'page' => 2]));
        $response->assertSessionHas('members_status', 'Contact added.');

        $this->assertDatabaseHas('contacts', [
            'user_id' => $viewer->id,
            'contact_user_id' => $target->id,
        ]);
    }

    public function test_add_contact_prevents_self_contact_and_duplicate_contact(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create(['username' => 'target_user']);

        $selfResponse = $this->actingAs($viewer)->post(route('members.contacts.store', $viewer));
        $selfResponse->assertRedirect(route('members.index'));
        $selfResponse->assertSessionHas('members_status', 'You cannot add yourself as a contact.');

        Contact::query()->create([
            'user_id' => $viewer->id,
            'contact_user_id' => $target->id,
        ]);

        $duplicateResponse = $this->actingAs($viewer)->post(route('members.contacts.store', $target));
        $duplicateResponse->assertRedirect(route('members.index'));
        $duplicateResponse->assertSessionHas('members_status', 'That member is already in your contacts.');

        $this->assertSame(1, Contact::query()
            ->where('user_id', $viewer->id)
            ->where('contact_user_id', $target->id)
            ->count());
    }

    public function test_authenticated_user_can_remove_contact_from_contacts_section(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create(['username' => 'target_user']);

        Contact::query()->create([
            'user_id' => $viewer->id,
            'contact_user_id' => $target->id,
        ]);

        $response = $this->actingAs($viewer)->delete(route('members.contacts.destroy', $target), [
            'return_q' => 'target',
            'return_page' => 3,
        ]);

        $response->assertRedirect(route('members.index', ['q' => 'target', 'page' => 3]));
        $response->assertSessionHas('members_status', 'Contact removed.');

        $this->assertDatabaseMissing('contacts', [
            'user_id' => $viewer->id,
            'contact_user_id' => $target->id,
        ]);
    }

    public function test_authenticated_user_can_add_and_remove_ignore_from_member_profile(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create(['username' => 'target_user']);

        $storeResponse = $this->actingAs($viewer)->post(route('members.ignore.store', $target), [
            'return_tab' => 'overview',
        ]);

        $storeResponse->assertRedirect(route('members.show', ['user' => $target->id, 'tab' => 'overview']));
        $storeResponse->assertSessionHas('members_status', 'Member added to ignore list.');
        $this->assertDatabaseHas('ignored_users', [
            'user_id' => $viewer->id,
            'ignored_user_id' => $target->id,
        ]);

        $destroyResponse = $this->actingAs($viewer)->delete(route('members.ignore.destroy', $target), [
            'return_tab' => 'overview',
        ]);

        $destroyResponse->assertRedirect(route('members.show', ['user' => $target->id, 'tab' => 'overview']));
        $destroyResponse->assertSessionHas('members_status', 'Member removed from ignore list.');
        $this->assertDatabaseMissing('ignored_users', [
            'user_id' => $viewer->id,
            'ignored_user_id' => $target->id,
        ]);
    }

    public function test_ignore_prevents_self_ignore_and_hides_message_button(): void
    {
        $viewer = User::factory()->create([
            'username' => 'viewer',
            'allow_private_messages' => true,
        ]);
        $target = User::factory()->create([
            'username' => 'target_user',
            'allow_private_messages' => true,
        ]);

        IgnoredUser::query()->create([
            'user_id' => $viewer->id,
            'ignored_user_id' => $target->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('members.show', $target))
            ->assertOk()
            ->assertDontSee(route('messages.compose', ['to' => 'target_user']))
            ->assertSee('Remove from ignore list');

        $selfResponse = $this->actingAs($viewer)->post(route('members.ignore.store', $viewer));
        $selfResponse->assertRedirect(route('members.show', $viewer));
        $selfResponse->assertSessionHas('members_status', 'You cannot add yourself to your ignore list.');
    }

    public function test_member_page_is_available(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create([
            'username' => 'legacymember',
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $user));

        $response->assertOk();
        $response->assertSee('Member Profile');
        $response->assertSee('legacymember');
        $response->assertSee('Back to Members Overview');
        $response->assertSee(route('members.index'));
        $response->assertSee($user->avatarUrl(160));
        $response->assertSee('<main id="main-content" tabindex="-1" class="bg-white">', false);
        $response->assertDontSee('rounded-[14px] border border-[#cabca8] bg-white', false);
    }

    public function test_member_page_overview_shows_profile_and_statistics_sections(): void
    {
        $viewer = User::factory()->create();
        $member = User::factory()->create([
            'username' => 'mike',
            'name' => 'Mike',
            'biography' => 'Long-time community member.',
            'user_title' => 'Community Guide',
            'location' => 'Oregon',
            'occupation' => 'Store owner',
            'interests' => 'Zen Cart, PHP',
            'created_at' => now()->subDays(4),
            'legacy_registered_at' => now()->subYears(10),
        ]);
        $forumGroupId = DB::table('forum_groups')->insertGetId([
            'name' => 'Support',
            'slug' => 'support-'.Str::lower(Str::random(6)),
            'description' => 'Support group',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $forumId = DB::table('forums')->insertGetId([
            'forum_group_id' => $forumGroupId,
            'name' => 'General Help',
            'slug' => 'general-help-'.Str::lower(Str::random(6)),
            'description' => 'General help forum',
            'visibility' => ForumVisibility::PUBLIC->value,
            'posting_policy' => ForumPostingPolicy::REGISTERED->value,
            'is_read_only' => false,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $threadId = DB::table('threads')->insertGetId([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Need help with setup',
            'slug' => 'need-help-with-setup-'.Str::lower(Str::random(6)),
            'is_pinned' => false,
            'is_locked' => false,
            'reply_count' => 1,
            'last_post_author_id' => $member->id,
            'last_post_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Post::query()->create([
            'thread_id' => $threadId,
            'author_id' => $member->id,
            'body' => 'First post body',
            'is_deleted' => false,
        ]);
        Post::query()->create([
            'thread_id' => $threadId,
            'author_id' => $member->id,
            'body' => 'Second post body',
            'is_deleted' => false,
        ]);

        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $member->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => now()->subHour()->timestamp,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.show', $member));

        $response->assertOk();
        $response->assertSeeText('Basic Information');
        $response->assertSeeText("About 'mike'");
        $response->assertSeeText('Long-time community member.');
        $response->assertSeeText('User Title:');
        $response->assertSeeText('Community Guide');
        $response->assertSeeText('Contact');
        $response->assertSee(route('members.show', $member));
        $response->assertSeeText('Statistics');
        $response->assertSeeText('Total Posts:');
        $response->assertSeeText('2');
        $response->assertSeeText('Plugin Contributions:');
        $response->assertSeeText('Uploads:');
        $response->assertSeeText('Join Date:');
        $response->assertSeeText('Last Activity:');
        $response->assertSeeText(now()->subYears(10)->format('j M Y'));
    }

    public function test_member_page_lists_plugins_and_releases_for_public_viewers(): void
    {
        $viewer = User::factory()->create();
        $member = User::factory()->create(['username' => 'plugin_author']);
        $type = $this->createPluginType();
        $plugin = Plugin::query()->create([
            'name' => 'Catalog Booster',
            'slug' => 'catalog-booster-'.Str::lower(Str::random(6)),
            'plugin_type_id' => $type->id,
            'owner_id' => $member->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        $plugin->forceFill([
            'created_at' => now()->subYears(5),
            'updated_at' => now()->subYears(2),
        ])->saveQuietly();
        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $member->id,
            'version' => '1.2.3',
            'internal_version' => 1,
            'zip_path' => 'plugins/catalog-booster-1.2.3.zip',
            'zip_filename' => 'catalog-booster-1.2.3.zip',
            'encapsulated' => Encapsulated::YES->value,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'plugins']))
            ->assertOk()
            ->assertSee('Plugins Authored')
            ->assertSee('Catalog Booster')
            ->assertSee('Active')
            ->assertSee(LocalTime::format($plugin->updated_at, 'date'))
            ->assertDontSee(LocalTime::format($plugin->created_at, 'date'));

        $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'releases']))
            ->assertOk()
            ->assertSee('Uploaded Releases')
            ->assertSee('1.2.3')
            ->assertSee('Catalog Booster');
    }

    public function test_member_page_lists_plugins_uploaded_by_member_even_when_owned_by_another_user(): void
    {
        $viewer = User::factory()->create();
        $member = User::factory()->create(['username' => 'plugin_uploader']);
        $owner = User::factory()->create(['username' => 'plugin_owner']);
        $type = $this->createPluginType();
        $plugin = Plugin::query()->create([
            'name' => 'Responsive Classic Redesign Template',
            'slug' => 'responsive-classic-redesign-template-'.Str::lower(Str::random(6)),
            'plugin_type_id' => $type->id,
            'owner_id' => $owner->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $member->id,
            'version' => 'v5',
            'internal_version' => 1,
            'zip_path' => 'plugins/responsive-classic-redesign-template-v5.zip',
            'zip_filename' => 'responsive-classic-redesign-template-v5.zip',
            'encapsulated' => Encapsulated::YES->value,
            'status' => PluginStatus::ACTIVE->value,
        ]);

        $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'plugins']))
            ->assertOk()
            ->assertSee('Responsive Classic Redesign Template');

        $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'releases']))
            ->assertOk()
            ->assertSee('v5')
            ->assertSee('Responsive Classic Redesign Template');
    }

    public function test_member_page_hides_moderated_contributions_from_non_staff_viewers(): void
    {
        $viewer = User::factory()->create();
        $member = User::factory()->create(['username' => 'moderated_author']);
        $type = $this->createPluginType();
        $activePlugin = Plugin::query()->create([
            'name' => 'Active Plugin',
            'slug' => 'active-plugin-'.Str::lower(Str::random(6)),
            'plugin_type_id' => $type->id,
            'owner_id' => $member->id,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        Plugin::query()->create([
            'name' => 'Pending Plugin',
            'slug' => 'pending-plugin-'.Str::lower(Str::random(6)),
            'plugin_type_id' => $type->id,
            'owner_id' => $member->id,
            'status' => PluginStatus::MODERATED->value,
        ]);
        PluginRelease::query()->create([
            'plugin_id' => $activePlugin->id,
            'uploaded_by_id' => $member->id,
            'version' => '2.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/active-plugin-2.0.0.zip',
            'zip_filename' => 'active-plugin-2.0.0.zip',
            'encapsulated' => Encapsulated::UNKNOWN->value,
            'status' => PluginStatus::ACTIVE->value,
        ]);
        PluginRelease::query()->create([
            'plugin_id' => $activePlugin->id,
            'uploaded_by_id' => $member->id,
            'version' => '2.1.0-beta',
            'internal_version' => 2,
            'zip_path' => 'plugins/active-plugin-2.1.0-beta.zip',
            'zip_filename' => 'active-plugin-2.1.0-beta.zip',
            'encapsulated' => Encapsulated::UNKNOWN->value,
            'status' => PluginStatus::MODERATED->value,
        ]);

        $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'plugins']))
            ->assertOk()
            ->assertSee('Active Plugin')
            ->assertDontSee('Pending Plugin');

        $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'releases']))
            ->assertOk()
            ->assertSee('2.0.0')
            ->assertDontSee('2.1.0-beta');
    }

    public function test_member_page_shows_non_public_contributions_to_owner_and_staff(): void
    {
        $member = User::factory()->create(['username' => 'owner_author']);
        $staff = User::factory()->create(['username' => 'staff_member']);
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');
        $type = $this->createPluginType();
        $plugin = Plugin::query()->create([
            'name' => 'Owner Draft Plugin',
            'slug' => 'owner-draft-plugin-'.Str::lower(Str::random(6)),
            'plugin_type_id' => $type->id,
            'owner_id' => $member->id,
            'status' => PluginStatus::MODERATED->value,
        ]);
        PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $member->id,
            'version' => '0.9.0-rc1',
            'internal_version' => 1,
            'zip_path' => 'plugins/owner-draft-plugin-0.9.0-rc1.zip',
            'zip_filename' => 'owner-draft-plugin-0.9.0-rc1.zip',
            'encapsulated' => Encapsulated::NO->value,
            'status' => PluginStatus::MODERATED->value,
        ]);

        $this->actingAs($member)
            ->get(route('members.show', ['user' => $member, 'tab' => 'plugins']))
            ->assertOk()
            ->assertSee('Owner Draft Plugin');

        $this->actingAs($member)
            ->get(route('members.show', ['user' => $member, 'tab' => 'releases']))
            ->assertOk()
            ->assertSee('0.9.0-rc1');

        $this->actingAs($staff)
            ->get(route('members.show', ['user' => $member, 'tab' => 'plugins']))
            ->assertOk()
            ->assertSee('Owner Draft Plugin');

        $this->actingAs($staff)
            ->get(route('members.show', ['user' => $member, 'tab' => 'releases']))
            ->assertOk()
            ->assertSee('0.9.0-rc1');
    }

    public function test_member_page_threads_and_posts_tabs_show_only_visible_content(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $member = User::factory()->create(['username' => 'contributor']);
        $forumGroupId = DB::table('forum_groups')->insertGetId([
            'name' => 'Support',
            'slug' => 'support-tabs-'.Str::lower(Str::random(6)),
            'description' => 'Support group',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $forumId = DB::table('forums')->insertGetId([
            'forum_group_id' => $forumGroupId,
            'name' => 'General Help',
            'slug' => 'general-help-tabs-'.Str::lower(Str::random(6)),
            'description' => 'General help forum',
            'visibility' => ForumVisibility::PUBLIC->value,
            'posting_policy' => ForumPostingPolicy::REGISTERED->value,
            'is_read_only' => false,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $approvedThread = Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Approved thread by contributor',
            'slug' => 'approved-thread-by-contributor-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Pending thread by contributor',
            'slug' => 'pending-thread-by-contributor-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        Post::query()->create([
            'thread_id' => $approvedThread->id,
            'author_id' => $member->id,
            'body' => 'Visible tab post body',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'is_deleted' => false,
        ]);
        Post::query()->create([
            'thread_id' => $approvedThread->id,
            'author_id' => $member->id,
            'body' => 'Deleted tab post body',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'is_deleted' => true,
        ]);

        $threadsResponse = $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'threads']));

        $threadsResponse->assertOk();
        $threadsResponse->assertSee('Started Threads');
        $threadsResponse->assertSee('Approved thread by contributor');
        $threadsResponse->assertDontSee('Pending thread by contributor');

        $postsResponse = $this->actingAs($viewer)
            ->get(route('members.show', ['user' => $member, 'tab' => 'posts']));

        $postsResponse->assertOk();
        $postsResponse->assertSeeText('Posts');
        $postsResponse->assertSee('Visible tab post body');
        $postsResponse->assertDontSee('Deleted tab post body');
    }

    public function test_member_page_menu_includes_threads_and_posts_links(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $member = User::factory()->create(['username' => 'menu_target']);

        $response = $this->actingAs($viewer)->get(route('members.show', $member));

        $response->assertOk();
        $response->assertSee(route('members.show', ['user' => $member->id, 'tab' => 'threads']));
        $response->assertSee(route('members.show', ['user' => $member->id, 'tab' => 'posts']));
        $response->assertSeeInOrder(['Overview', 'Plugins', 'Releases', 'Threads', 'Posts']);
    }

    public function test_member_latest_posts_page_shows_only_visible_posts_for_non_staff_viewer(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $member = User::factory()->create(['username' => 'poster']);
        $forumGroupId = DB::table('forum_groups')->insertGetId([
            'name' => 'Support',
            'slug' => 'support-posts-'.Str::lower(Str::random(6)),
            'description' => 'Support group',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $forumId = DB::table('forums')->insertGetId([
            'forum_group_id' => $forumGroupId,
            'name' => 'General Help',
            'slug' => 'general-help-posts-'.Str::lower(Str::random(6)),
            'description' => 'General help forum',
            'visibility' => ForumVisibility::PUBLIC->value,
            'posting_policy' => ForumPostingPolicy::REGISTERED->value,
            'is_read_only' => false,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $approvedThread = Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Approved thread',
            'slug' => 'approved-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        $pendingThread = Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Pending thread',
            'slug' => 'pending-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        Post::query()->create([
            'thread_id' => $approvedThread->id,
            'author_id' => $member->id,
            'body' => 'Visible post body',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'is_deleted' => false,
        ]);
        Post::query()->create([
            'thread_id' => $approvedThread->id,
            'author_id' => $member->id,
            'body' => 'Deleted post body',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'is_deleted' => true,
        ]);
        Post::query()->create([
            'thread_id' => $pendingThread->id,
            'author_id' => $member->id,
            'body' => 'Hidden pending post body',
            'moderation_status' => ContentModerationStatus::PENDING,
            'is_deleted' => false,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.posts', $member));

        $response->assertOk();
        $response->assertSee('Visible post body');
        $response->assertSee('Approved thread');
        $response->assertDontSee('Deleted post body');
        $response->assertDontSee('Hidden pending post body');
        $response->assertDontSee('Pending thread');
    }

    public function test_member_latest_threads_page_shows_only_visible_threads_for_non_staff_viewer(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $member = User::factory()->create(['username' => 'starter']);
        $forumGroupId = DB::table('forum_groups')->insertGetId([
            'name' => 'Support',
            'slug' => 'support-threads-'.Str::lower(Str::random(6)),
            'description' => 'Support group',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $forumId = DB::table('forums')->insertGetId([
            'forum_group_id' => $forumGroupId,
            'name' => 'General Help',
            'slug' => 'general-help-threads-'.Str::lower(Str::random(6)),
            'description' => 'General help forum',
            'visibility' => ForumVisibility::PUBLIC->value,
            'posting_policy' => ForumPostingPolicy::REGISTERED->value,
            'is_read_only' => false,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Visible started thread',
            'slug' => 'visible-started-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
        Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Hidden started thread',
            'slug' => 'hidden-started-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::PENDING,
        ]);

        $response = $this->actingAs($viewer)->get(route('members.threads', $member));

        $response->assertOk();
        $response->assertSee('Visible started thread');
        $response->assertDontSee('Hidden started thread');
    }

    public function test_member_latest_threads_page_includes_entitlement_only_threads_for_entitled_viewer(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $entitledViewer = User::factory()->create(['username' => 'entitledviewer']);
        $member = User::factory()->create(['username' => 'restrictedstarter']);
        $forumGroupId = DB::table('forum_groups')->insertGetId([
            'name' => 'Restricted',
            'slug' => 'restricted-threads-'.Str::lower(Str::random(6)),
            'description' => 'Restricted group',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $forumId = DB::table('forums')->insertGetId([
            'forum_group_id' => $forumGroupId,
            'name' => 'New Website Feedback',
            'slug' => 'new-website-feedback-threads-'.Str::lower(Str::random(6)),
            'description' => 'Restricted feedback forum',
            'visibility' => ForumVisibility::ENTITLEMENT_ONLY->value,
            'required_entitlement_scope' => 'forums.new_website_feedback.view',
            'posting_policy' => ForumPostingPolicy::REGISTERED->value,
            'is_read_only' => false,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Restricted started thread',
            'slug' => 'restricted-started-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $this->actingAs($viewer)
            ->get(route('members.threads', $member))
            ->assertOk()
            ->assertDontSee('Restricted started thread');

        UserEntitlementGrant::query()->create([
            'user_id' => $entitledViewer->id,
            'entitlement_key' => 'forums.new_website_feedback.view',
            'grant_type' => 'manual',
            'granted_by_id' => $entitledViewer->id,
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($entitledViewer)
            ->get(route('members.threads', $member))
            ->assertOk()
            ->assertSee('Restricted started thread');
    }

    public function test_guest_is_redirected_from_members_routes(): void
    {
        $user = User::factory()->create();

        $this->get(route('members.index'))
            ->assertRedirect('/?auth=login&intended=%2Fmembers');
        $this->get(route('members.show', $user))
            ->assertRedirect('/?auth=login&intended=%2Fmembers%2F'.$user->id);
    }

    public function test_unverified_user_is_redirected_from_members_routes_to_verify_flow(): void
    {
        $viewer = User::factory()->unverified()->create();
        $target = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('members.index'))
            ->assertRedirect(route('verification.notice', ['intended' => '/members']));

        $this->actingAs($viewer)
            ->get(route('members.show', $target))
            ->assertRedirect(route('verification.notice', ['intended' => '/members/'.$target->id]));
    }

    public function test_member_profile_post_count_is_cached_across_repeated_views(): void
    {
        $viewer = User::factory()->create(['username' => 'count_viewer']);
        $member = User::factory()->create(['username' => 'count_target']);
        $thread = $this->createThreadForMember($member);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $member->id,
            'body' => 'Counted post body',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'is_deleted' => false,
        ]);

        $countQueries = [];
        DB::listen(function ($query) use (&$countQueries): void {
            if ($this->isVisiblePostCountQuery($query->sql)) {
                $countQueries[] = $query->sql;
            }
        });

        $first = $this->actingAs($viewer)->get(route('members.show', $member));
        $first->assertOk();
        $first->assertViewHas('visiblePostCount', 1);

        $second = $this->actingAs($viewer)->get(route('members.show', $member));
        $second->assertOk();
        $second->assertViewHas('visiblePostCount', 1);

        $this->assertCount(1, $countQueries, 'The member post count should be counted once and served from cache thereafter.');
    }

    public function test_member_profile_post_count_is_cached_separately_for_staff_and_public_viewers(): void
    {
        $viewer = User::factory()->create(['username' => 'public_count_viewer']);
        $member = User::factory()->create(['username' => 'unapproved_author']);
        $staff = User::factory()->create(['username' => 'count_staff_member']);
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $thread = $this->createThreadForMember($member);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $member->id,
            'body' => 'Approved counted post',
            'moderation_status' => ContentModerationStatus::APPROVED,
            'is_deleted' => false,
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $member->id,
            'body' => 'Pending counted post',
            'moderation_status' => ContentModerationStatus::PENDING,
            'is_deleted' => false,
        ]);

        $publicResponse = $this->actingAs($viewer)->get(route('members.show', $member));
        $publicResponse->assertOk();
        $publicResponse->assertViewHas('visiblePostCount', 1);

        $staffResponse = $this->actingAs($staff)->get(route('members.show', $member));
        $staffResponse->assertOk();
        $staffResponse->assertViewHas('visiblePostCount', 2);

        // Re-read both from cache: neither audience may inherit the other's total.
        $this->actingAs($viewer)
            ->get(route('members.show', $member))
            ->assertViewHas('visiblePostCount', 1);
        $this->actingAs($staff)
            ->get(route('members.show', $member))
            ->assertViewHas('visiblePostCount', 2);
    }

    public function test_member_posts_pagination_total_is_cached_per_viewer_and_not_recounted(): void
    {
        $viewer = User::factory()->create(['username' => 'page_total_viewer']);
        $member = User::factory()->create(['username' => 'page_total_member']);
        $staff = User::factory()->create(['username' => 'page_total_staff']);
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        $thread = $this->createThreadForMember($member);
        foreach ([ContentModerationStatus::APPROVED, ContentModerationStatus::PENDING] as $status) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $member->id,
                'body' => 'Paginated post '.$status->value,
                'moderation_status' => $status,
                'is_deleted' => false,
            ]);
        }

        $joinedCounts = 0;
        DB::listen(function ($query) use (&$joinedCounts): void {
            $normalized = str_replace('`', '"', strtolower($query->sql));
            if (str_starts_with($normalized, 'select count(*) as "aggregate" from "posts" inner join "threads"')) {
                $joinedCounts++;
            }
        });

        foreach ([[$viewer, 1], [$staff, 2], [$viewer, 1], [$staff, 2]] as [$who, $expectedTotal]) {
            $this->actingAs($who)
                ->get(route('members.show', $member))
                ->assertOk()
                ->assertViewHas('posts', fn ($posts): bool => $posts->total() === $expectedTotal && $posts->count() === $expectedTotal);
        }

        $this->assertSame(2, $joinedCounts, 'Each viewer audience should run the joined post count once, then hit the cache.');

        $this->actingAs($viewer)
            ->get(route('members.posts', $member))
            ->assertOk()
            ->assertViewHas('posts', fn ($posts): bool => $posts->total() === 1);
    }

    private function isVisiblePostCountQuery(string $sql): bool
    {
        $normalized = str_replace('`', '"', strtolower($sql));

        // The paginated posts listing also counts posts, but joins threads and
        // forums first; only the profile statistic counts the bare table.
        return str_starts_with($normalized, 'select count(*) as "aggregate" from "posts" where')
            && str_contains($normalized, 'author_id');
    }

    private function createThreadForMember(User $member): Thread
    {
        $forumGroupId = DB::table('forum_groups')->insertGetId([
            'name' => 'Support',
            'slug' => 'support-count-'.Str::lower(Str::random(6)),
            'description' => 'Support group',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $forumId = DB::table('forums')->insertGetId([
            'forum_group_id' => $forumGroupId,
            'name' => 'General Help',
            'slug' => 'general-help-count-'.Str::lower(Str::random(6)),
            'description' => 'General help forum',
            'visibility' => ForumVisibility::PUBLIC->value,
            'posting_policy' => ForumPostingPolicy::REGISTERED->value,
            'is_read_only' => false,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Thread::query()->create([
            'forum_id' => $forumId,
            'author_id' => $member->id,
            'title' => 'Counted thread',
            'slug' => 'counted-thread-'.Str::lower(Str::random(6)),
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);
    }

    private function createPluginType(): PluginType
    {
        return PluginType::query()->create([
            'name' => 'Utilities',
            'slug' => 'utilities-'.Str::lower(Str::random(6)),
            'description' => 'Utility plugins',
            'is_active' => true,
            'position' => 1,
        ]);
    }
}
