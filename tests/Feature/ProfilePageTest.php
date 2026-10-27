<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\NewsletterSubscriberStatus;
use App\Services\NewsletterSubscriberService;
use App\TimeFormat;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Core\Models\Setting;
use Filagora\Entitlements\Models\EntitlementDefinition;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Notifications\Services\Preferences;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_guest_is_redirected_to_dashboard_login(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/?auth=login&intended=%2Fprofile');
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = $this->makeAcknowledgedUser();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Profile');
        $response->assertSee('<main id="main-content" tabindex="-1" class="bg-white">', false);
        $response->assertSee('data-markdown-target="profile-signature"', false);
        $response->assertSee('aria-label="Markdown tools"', false);
        $response->assertSee('data-markdown-preview-panel="profile-signature-preview"', false);
        $response->assertSee('API Keys');
        $response->assertSee('class="mt-[18px] grid gap-3"', false);
        $response->assertSee('class="block p-4"', false);
        $response->assertDontSee('class="block border border-[#d8dce2] bg-white p-4"', false);
        $response->assertDontSee('rounded-[14px] border border-[#cabca8] bg-[#ececed] p-4', false);
        $response->assertDontSee('rounded-[10px] border border-[#cabca8] bg-[#ececed] p-4', false);
        $response->assertDontSee('>Current</span>', false);
    }

    public function test_entitlements_tab_shows_effective_entitlement_provenance(): void
    {
        $user = $this->makeAcknowledgedUser(['email_verified_at' => now()]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.download',
            'grant_type' => 'manual',
            'reason' => 'Approved for migration testing.',
            'starts_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('profile.show', ['tab' => 'entitlements']));

        $response->assertOk();
        $response->assertSee('api.plugins.download');
        $response->assertSee('Direct grant');
        $response->assertSee('Approved for migration testing.');
    }

    public function test_entitlements_tab_shows_reviewed_request_history_details(): void
    {
        $user = $this->makeAcknowledgedUser(['email_verified_at' => now()]);

        EntitlementRequest::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'downloads.premium_plugins',
            'status' => 'revoked',
            'request_reason' => 'Need time-boxed access for package validation.',
            'review_notes' => 'Access window ended after validation.',
            'requested_at' => now()->subDays(3),
            'reviewed_at' => now()->subDay(),
            'expires_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($user)->get(route('profile.show', ['tab' => 'entitlements']));

        $response->assertOk();
        $response->assertSee('downloads.premium_plugins');
        $response->assertSee('Revoked');
        $response->assertSee('Submitted:');
        $response->assertSee('Reviewed:');
        $response->assertSee('Expires:');
        $response->assertSee('Access window ended after validation.');
    }

    public function test_entitlements_tab_hides_requestable_entitlements_user_already_has(): void
    {
        $user = $this->makeAcknowledgedUser(['email_verified_at' => now()]);

        EntitlementDefinition::query()->updateOrCreate(
            ['key' => 'api.plugins.read'],
            [
                'label' => 'API Plugins Read',
                'namespace' => 'api.plugins',
                'is_grantable' => true,
                'is_requestable' => true,
                'supports_wildcard_children' => false,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        EntitlementDefinition::query()->create([
            'key' => 'api.plugins.download',
            'label' => 'API Plugins Download',
            'namespace' => 'api.plugins',
            'is_grantable' => true,
            'is_requestable' => true,
            'supports_wildcard_children' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.read',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('profile.show', ['tab' => 'entitlements']));

        $response->assertOk();
        $response->assertDontSee('API Plugins Read (api.plugins.read)');
        $response->assertSee('API Plugins Download (api.plugins.download)');
    }

    public function test_authenticated_user_can_create_api_key_from_profile(): void
    {
        $user = $this->makeAcknowledgedUser();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.read',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->post('/profile/api-keys', [
            'name' => 'CLI integration',
            'abilities' => ['plugins:read'],
        ]);

        $response->assertRedirect('/profile?tab=api-keys');
        $response->assertSessionHas('profile_status', __('profile.flash.api_key_created'));
        $response->assertSessionHas('new_api_token');

        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();

        $this->assertNotNull($token);
        $this->assertSame('CLI integration', $token->name);
        $this->assertSame(['plugins:read'], $token->abilities);

        $profileResponse = $this->actingAs($user)->get('/profile?tab=api-keys');
        $profileResponse->assertOk();
        $profileResponse->assertSee('CLI integration');
        $profileResponse->assertSee('Copy your new API key now.');
        $profileResponse->assertSee('Plugins Read');
        $profileResponse->assertSee('border border-[#d8dce2] bg-white p-3', false);
        $profileResponse->assertDontSee('border border-[#cabca8] bg-[#f8f6f1] p-3', false);
    }

    public function test_api_key_name_is_required(): void
    {
        $user = $this->makeAcknowledgedUser();
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => 'api.plugins.read',
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->from('/profile?tab=api-keys')
            ->post('/profile/api-keys', [
                'name' => '',
                'abilities' => ['plugins:read'],
            ]);

        $response->assertRedirect('/profile?tab=api-keys');
        $response->assertSessionHasErrors(['name'], null, 'apiToken');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_api_key_requires_at_least_one_entitled_scope(): void
    {
        $user = $this->makeAcknowledgedUser();

        $response = $this->actingAs($user)
            ->from('/profile?tab=api-keys')
            ->post('/profile/api-keys', [
                'name' => 'CLI integration',
                'abilities' => [],
            ]);

        $response->assertRedirect('/profile?tab=api-keys');
        $response->assertSessionHasErrors(['abilities'], null, 'apiToken');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_api_key_rejects_scope_without_matching_entitlement(): void
    {
        $user = $this->makeAcknowledgedUser();

        $response = $this->actingAs($user)
            ->from('/profile?tab=api-keys')
            ->post('/profile/api-keys', [
                'name' => 'CLI integration',
                'abilities' => ['plugins:write'],
            ]);

        $response->assertRedirect('/profile?tab=api-keys');
        $response->assertSessionHasErrors(['abilities.0'], null, 'apiToken');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_authenticated_user_can_revoke_own_api_key(): void
    {
        $user = $this->makeAcknowledgedUser();
        $token = $user->createToken('Desktop app');
        $tokenId = (int) explode('|', $token->plainTextToken, 2)[0];

        $response = $this->actingAs($user)->post('/profile/api-keys/'.$tokenId.'/revoke');

        $response->assertRedirect('/profile?tab=api-keys');
        $response->assertSessionHas('profile_status', __('profile.flash.api_key_revoked'));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    public function test_user_cannot_revoke_another_users_api_key(): void
    {
        $user = $this->makeAcknowledgedUser();
        $otherUser = $this->makeAcknowledgedUser();
        $token = $otherUser->createToken('Other device');
        $tokenId = (int) explode('|', $token->plainTextToken, 2)[0];

        $this->actingAs($user)
            ->post('/profile/api-keys/'.$tokenId.'/revoke')
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $otherUser->id,
        ]);
    }

    public function test_profile_hides_privacy_tab_when_gdpr_enforcement_is_disabled(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'enforce_gdpr'],
            [
                'label' => 'Enforce GDPR',
                'value' => '0',
                'value_type' => 'boolean',
                'is_public' => false,
            ]
        );

        $user = $this->makeAcknowledgedUser();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertDontSee('Privacy Requests');
    }

    public function test_authenticated_user_can_update_non_email_details_without_losing_verification(): void
    {
        $user = $this->makeAcknowledgedUser([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
            'user_title' => null,
            'location' => null,
            'occupation' => null,
            'interests' => null,
            'biography' => null,
        ]);

        $response = $this->actingAs($user)->post('/profile/details', [
            'name' => 'New Name',
            'username' => 'new_user',
            'email' => 'old@example.com',
            'user_title' => 'Migration Maintainer',
            'location' => 'Dallas, TX',
            'occupation' => 'Developer',
            'interests' => 'Laravel, vBulletin, migration',
            'biography' => "Long-form biography\nwith two lines.",
        ]);

        $response->assertRedirect('/profile?tab=details');
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new_user', $user->username);
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('Migration Maintainer', $user->user_title);
        $this->assertSame('Dallas, TX', $user->location);
        $this->assertSame('Developer', $user->occupation);
        $this->assertSame('Laravel, vBulletin, migration', $user->interests);
        $this->assertSame("Long-form biography\nwith two lines.", $user->biography);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_profile_details_reject_username_characters_disallowed_at_registration(): void
    {
        $user = $this->makeAcknowledgedUser([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from('/profile?tab=details')
            ->post('/profile/details', [
                'name' => 'Old Name',
                'username' => '<img src=x onerror=alert(1)>',
                'email' => 'old@example.com',
                'user_title' => null,
                'location' => null,
                'occupation' => null,
                'interests' => null,
                'biography' => null,
            ]);

        $response->assertRedirect('/profile?tab=details');
        $response->assertSessionHasErrorsIn('details', ['username']);

        $user->refresh();
        $this->assertSame('old_user', $user->username);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_change_resets_verification_and_redirects_to_verify_flow(): void
    {
        Notification::fake();

        $user = $this->makeAcknowledgedUser([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/profile/details', [
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'new@example.com',
            'user_title' => null,
            'location' => null,
            'occupation' => null,
            'interests' => null,
            'biography' => null,
        ]);

        $response->assertRedirect(route('verification.notice', ['intended' => '/profile?tab=details']));
        $response->assertSessionHas('url.intended', '/profile?tab=details');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_change_does_not_error_when_building_the_verification_email(): void
    {
        // Regression test: the default MustVerifyEmail trait builds its
        // verification link via the `verification.verify` route, which this
        // app never registers (email verification is handled entirely
        // through Filament's panel routes), so sending it 500'd with a
        // RouteNotFoundException. Deliberately does NOT fake notifications,
        // so this exercises the real toMail()/verificationUrl() build that
        // the bug lived in — Notification::fake() would intercept the send
        // before that code ever runs and mask the failure, as it did before
        // this test was added.
        $user = $this->makeAcknowledgedUser([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/profile/details', [
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'fresh@example.com',
            'user_title' => null,
            'location' => null,
            'occupation' => null,
            'interests' => null,
            'biography' => null,
        ]);

        $response->assertRedirect(route('verification.notice', ['intended' => '/profile?tab=details']));

        $user->refresh();
        $this->assertSame('fresh@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_change_keeps_newsletter_consent_with_old_address(): void
    {
        Notification::fake();

        $user = $this->makeAcknowledgedUser([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $subscriber = NewsletterSubscriber::query()->create([
            'user_id' => $user->id,
            'email' => 'old@example.com',
            'normalized_email' => 'old@example.com',
            'name' => 'Old Name',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'source' => 'profile',
            'subscribed_at' => now(),
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)->post('/profile/details', [
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'new@example.com',
            'user_title' => null,
            'location' => null,
            'occupation' => null,
            'interests' => null,
            'biography' => null,
        ])->assertRedirect(route('verification.notice', ['intended' => '/profile?tab=details']));

        $user->refresh();
        $subscriber->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($subscriber->user_id);
        $this->assertSame('old@example.com', $subscriber->email);
        $this->assertSame('old@example.com', $subscriber->normalized_email);
        $this->assertSame(NewsletterSubscriberStatus::Subscribed, $subscriber->status);
        $this->assertNull(app(NewsletterSubscriberService::class)->forUser($user));
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = $this->makeAcknowledgedUser([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'current-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect('/profile?tab=password');
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', (string) $user->password));
    }

    public function test_authenticated_user_can_enable_two_factor(): void
    {
        $user = $this->makeAcknowledgedUser();

        $this->actingAs($user)->post('/profile/2fa/setup')
            ->assertRedirect('/profile?tab=two-factor');

        $user->refresh();
        $secret = $user->getTwoFactorPendingSecret();
        $this->assertNotNull($secret);

        $google2fa = app(Google2FA::class);
        $otp = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)->post('/profile/2fa/confirm', [
            'code' => $otp,
        ]);
        $response->assertRedirect('/profile?tab=two-factor');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame(8, $user->recoveryCodesRemainingCount());
        $this->assertSame(8, count($response->getSession()->get('new_recovery_codes', [])));
    }

    public function test_two_factor_confirmation_recovery_codes_are_shown_once_on_the_profile_page(): void
    {
        $user = $this->makeAcknowledgedUser();

        $this->actingAs($user)->post('/profile/2fa/setup');
        $secret = $user->refresh()->getTwoFactorPendingSecret();
        $otp = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($user)->post('/profile/2fa/confirm', ['code' => $otp]);

        $this->actingAs($user)->get('/profile?tab=two-factor')
            ->assertOk()
            ->assertSee('Save these recovery codes now');

        // The flashed codes don't survive a second, unrelated request.
        $this->actingAs($user)->get('/profile?tab=two-factor')
            ->assertOk()
            ->assertDontSee('Save these recovery codes now')
            ->assertSee('8 recovery codes remaining');
    }

    public function test_authenticated_user_can_regenerate_recovery_codes(): void
    {
        $user = $this->makeAcknowledgedUser();
        $user->enableTwoFactor(app(Google2FA::class)->generateSecretKey(32));
        $originalCodes = $user->generateRecoveryCodes();
        $user->save();

        $response = $this->actingAs($user)->post('/profile/2fa/recovery-codes/regenerate');
        $response->assertRedirect('/profile?tab=two-factor');

        $newCodes = $response->getSession()->get('new_recovery_codes');
        $this->assertIsArray($newCodes);
        $this->assertCount(8, $newCodes);
        $this->assertEmpty(array_intersect($originalCodes, $newCodes));

        $user->refresh();
        foreach ($originalCodes as $originalCode) {
            $this->assertFalse($user->redeemRecoveryCode($originalCode));
        }
    }

    public function test_regenerating_recovery_codes_requires_two_factor_to_be_enabled(): void
    {
        $user = $this->makeAcknowledgedUser();

        $response = $this->actingAs($user)->post('/profile/2fa/recovery-codes/regenerate');
        $response->assertRedirect('/profile?tab=two-factor');

        $this->assertNull($response->getSession()->get('new_recovery_codes'));
    }

    public function test_account_pages_do_not_emit_public_structured_data(): void
    {
        $user = $this->makeAcknowledgedUser();

        $this->actingAs($user)
            ->get('/profile?tab=settings')
            ->assertOk()
            ->assertDontSee('application/ld+json', false);
    }

    public function test_subscribe_when_i_reply_setting_defaults_off_and_can_be_changed(): void
    {
        $user = $this->makeAcknowledgedUser();
        $this->assertFalse((bool) $user->fresh()->auto_subscribe_on_post);

        $this->actingAs($user)
            ->get('/profile?tab=settings')
            ->assertOk()
            ->assertSee('Subscribe When I Reply')
            ->assertSee('Do not subscribe me automatically');

        $settings = [
            'thread_emails_enabled' => '1',
            'thread_subscription_email_frequency' => 'instant',
            'plugin_release_updates' => '1',
            'allow_private_messages' => '1',
            'search_icon_enabled' => '1',
            'show_archived_plugin_releases' => '1',
            'prefers_rich_editor' => '1',
            'time_format' => '12h',
        ];

        $this->actingAs($user)->post('/profile/settings', $settings + ['auto_subscribe_on_post' => '1'])
            ->assertRedirect('/profile?tab=settings');
        $this->assertTrue((bool) $user->fresh()->auto_subscribe_on_post);

        // An older form that doesn't send the field leaves the choice alone.
        $this->actingAs($user)->post('/profile/settings', $settings)
            ->assertRedirect('/profile?tab=settings');
        $this->assertTrue((bool) $user->fresh()->auto_subscribe_on_post);

        $this->actingAs($user)->post('/profile/settings', $settings + ['auto_subscribe_on_post' => '0'])
            ->assertRedirect('/profile?tab=settings');
        $this->assertFalse((bool) $user->fresh()->auto_subscribe_on_post);
    }

    public function test_authenticated_user_can_update_settings(): void
    {
        $user = $this->makeAcknowledgedUser([
            'allow_private_messages' => true,
            'timezone' => null,
            'signature' => null,
        ]);

        $response = $this->actingAs($user)->post('/profile/settings', [
            'allow_private_messages' => '0',
            'search_icon_enabled' => '0',
            'show_archived_plugin_releases' => '0',
            'prefers_rich_editor' => '0',
            'timezone' => 'America/New_York',
            'time_format' => '24h',
            'signature' => "**Bold**\n*Italic*",
        ]);

        $response->assertRedirect('/profile?tab=settings');
        $user->refresh();
        $this->assertFalse((bool) $user->allow_private_messages);
        $this->assertSame('America/New_York', $user->timezone);
        $this->assertSame(TimeFormat::TWENTY_FOUR_HOUR, $user->time_format);
        $this->assertFalse((bool) $user->show_archived_plugin_releases);
        $this->assertFalse((bool) $user->prefers_rich_editor);
        $this->assertSame("**Bold**\n*Italic*", $user->signature);
        $this->assertSame('markdown', $user->signature_format);

        $this->actingAs($user)->get('/profile?tab=settings')->assertOk();
    }

    public function test_notification_preferences_are_saved_from_the_matrix_on_the_settings_tab(): void
    {
        $user = $this->makeAcknowledgedUser();
        $preferences = app(Preferences::class);

        $switch = collect($preferences->matrix($user))->first(fn (array $row): bool => $row['mode'] === null && isset($row['channels']['email']));
        $this->assertNotNull($switch, 'The packages register at least one email switch.');
        $category = $switch['category'];

        $this->actingAs($user)->get('/profile?tab=settings')->assertOk()->assertSee($category->label());

        $this->actingAs($user)->post(route('profile.notifications.update'), [])->assertRedirect('/profile?tab=settings');
        $preferences->flush();
        $this->assertFalse($preferences->allows($user, $category));

        $this->actingAs($user)->post(route('profile.notifications.update'), ['channel' => [$category->id => ['email' => '1']]])->assertRedirect('/profile?tab=settings');
        $preferences->flush();
        $this->assertTrue($preferences->allows($user, $category));
    }

    public function test_settings_tab_offers_browser_push_controls_and_the_page_carries_the_vapid_key(): void
    {
        config(['webpush.vapid.public_key' => 'BExamplePublicKey']);
        $user = $this->makeAcknowledgedUser();

        $this->actingAs($user)->get('/profile?tab=settings')
            ->assertOk()
            ->assertSee('data-push-settings', false)
            ->assertSee('data-push-enable', false)
            ->assertSee('<meta name="vapid-public-key" content="BExamplePublicKey">', false);
    }

    public function test_time_format_defaults_to_twelve_hour_for_a_new_user(): void
    {
        $user = User::factory()->create();

        $this->assertSame(TimeFormat::TWELVE_HOUR, $user->refresh()->time_format);
    }

    public function test_show_archived_plugin_releases_defaults_to_false_for_a_new_user(): void
    {
        $user = User::factory()->create();

        $this->assertFalse((bool) $user->refresh()->show_archived_plugin_releases);
    }

    public function test_invalid_time_format_is_rejected(): void
    {
        $user = $this->makeAcknowledgedUser(['time_format' => TimeFormat::TWELVE_HOUR]);

        $response = $this->actingAs($user)->post('/profile/settings', [
            'thread_emails_enabled' => '1',
            'thread_subscription_email_frequency' => 'instant',
            'plugin_release_updates' => '1',
            'allow_private_messages' => '1',
            'search_icon_enabled' => '0',
            'show_archived_plugin_releases' => '0',
            'prefers_rich_editor' => '1',
            'time_format' => 'not-a-real-format',
        ]);

        $response->assertSessionHasErrors('time_format', null, 'settings');
        $this->assertSame(TimeFormat::TWELVE_HOUR, $user->refresh()->time_format);
    }

    public function test_signature_over_500_characters_is_rejected(): void
    {
        $user = $this->makeAcknowledgedUser(['signature' => null]);

        $response = $this->actingAs($user)->post('/profile/settings', [
            'thread_emails_enabled' => '1',
            'thread_subscription_email_frequency' => 'instant',
            'plugin_release_updates' => '1',
            'allow_private_messages' => '1',
            'search_icon_enabled' => '0',
            'show_archived_plugin_releases' => '0',
            'prefers_rich_editor' => '1',
            'time_format' => '12h',
            'signature' => str_repeat('a', 501),
        ]);

        $response->assertSessionHasErrors('signature', null, 'settings');
        $this->assertNull($user->refresh()->signature);
    }

    public function test_signature_of_500_characters_is_accepted(): void
    {
        $user = $this->makeAcknowledgedUser(['signature' => null]);
        $signature = str_repeat('a', 500);

        $response = $this->actingAs($user)->post('/profile/settings', [
            'thread_emails_enabled' => '1',
            'thread_subscription_email_frequency' => 'instant',
            'plugin_release_updates' => '1',
            'allow_private_messages' => '1',
            'search_icon_enabled' => '0',
            'show_archived_plugin_releases' => '0',
            'prefers_rich_editor' => '1',
            'time_format' => '12h',
            'signature' => $signature,
        ]);

        $response->assertRedirect('/profile?tab=settings');
        $user->refresh();
        $this->assertSame($signature, $user->signature);
        $this->assertSame('markdown', $user->signature_format);
    }

    public function test_authenticated_user_can_manage_newsletter_consent_from_profile(): void
    {
        $user = $this->makeAcknowledgedUser([
            'email' => 'Subscriber@Example.test',
            'name' => 'Subscriber Name',
        ]);

        $this->actingAs($user)
            ->from('/profile?tab=settings')
            ->post(route('profile.newsletter.update'), [
                'newsletter_subscribed' => '1',
                'newsletter_consent' => '1',
            ])
            ->assertRedirect('/profile?tab=settings')
            ->assertSessionHas('profile_status', __('profile.flash.newsletter_subscribed'));

        $subscriber = NewsletterSubscriber::query()->first();

        $this->assertNotNull($subscriber);
        $this->assertSame($user->id, $subscriber->user_id);
        $this->assertSame('Subscriber@Example.test', $subscriber->email);
        $this->assertSame('subscriber@example.test', $subscriber->normalized_email);
        $this->assertSame(NewsletterSubscriberStatus::Subscribed, $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertSame('profile', $subscriber->source);

        $response = $this->actingAs($user)->get('/profile?tab=settings');

        $response->assertOk();
        $response->assertSee('Newsletter Consent');
        $response->assertSee('Current status:');
        $response->assertSee('Subscribed');

        $this->actingAs($user)
            ->from('/profile?tab=settings')
            ->post(route('profile.newsletter.update'), [
                'newsletter_subscribed' => '0',
            ])
            ->assertRedirect('/profile?tab=settings')
            ->assertSessionHas('profile_status', __('profile.flash.newsletter_unsubscribed'));

        $subscriber->refresh();

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertSame('profile', $subscriber->unsubscribe_reason);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function test_newsletter_profile_subscription_requires_explicit_consent(): void
    {
        $user = $this->makeAcknowledgedUser();

        $this->actingAs($user)
            ->from('/profile?tab=settings')
            ->post(route('profile.newsletter.update'), [
                'newsletter_subscribed' => '1',
            ])
            ->assertRedirect('/profile?tab=settings')
            ->assertSessionHasErrors(['newsletter_consent'], null, 'newsletter');

        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_authenticated_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $user = $this->makeAcknowledgedUser([
            'avatar_path' => null,
        ]);

        $uploadResponse = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('profile.jpg'),
        ]);

        $uploadResponse->assertRedirect('/profile?tab=avatar');
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists((string) $user->avatar_path);

        $oldPath = (string) $user->avatar_path;
        $removeResponse = $this->actingAs($user)->post('/profile/avatar/remove');
        $removeResponse->assertRedirect('/profile?tab=avatar');

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_public_avatar_route_streams_current_avatar_file(): void
    {
        Storage::fake('public');

        $user = $this->makeAcknowledgedUser([
            'avatar_path' => null,
        ]);

        $path = UploadedFile::fake()->image('legacy-vbulletin.jpg')
            ->storePublicly(sprintf('avatars/%d', $user->id), 'public');

        $user->forceFill([
            'avatar_path' => $path,
        ])->save();

        $response = $this->get($user->avatarUrl());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_public_avatar_route_sets_no_cookies_so_the_edge_can_cache_it(): void
    {
        Storage::fake('public');

        $user = $this->makeAcknowledgedUser([
            'avatar_path' => null,
        ]);

        $path = UploadedFile::fake()->image('legacy-vbulletin.jpg')
            ->storePublicly(sprintf('avatars/%d', $user->id), 'public');

        $user->forceFill([
            'avatar_path' => $path,
        ])->save();

        $response = $this->get($user->avatarUrl());

        $response->assertOk();

        // A Set-Cookie on a cacheable response makes Cloudflare serve it BYPASS,
        // so every avatar hit would reach PHP. The session middleware is stripped
        // from this route in routes/web.php to keep the response cacheable.
        $this->assertSame([], $response->headers->getCookies());
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
    }

    public function test_public_avatar_route_redirects_to_gravatar_when_file_is_missing(): void
    {
        Storage::fake('public');

        $user = $this->makeAcknowledgedUser([
            'email' => 'missing-avatar@example.com',
            'avatar_path' => 'avatars/999/missing.jpg',
        ]);

        $response = $this->get(route('users.avatar.show', [
            'user' => $user,
            'filename' => 'missing.jpg',
            's' => 160,
        ]));

        $response->assertRedirect($user->gravatarUrl(160));
    }

    public function test_two_factor_confirm_requires_initialized_setup(): void
    {
        $user = $this->makeAcknowledgedUser();

        $response = $this->actingAs($user)->post('/profile/2fa/confirm', [
            'code' => '123456',
        ]);

        $response->assertRedirect('/profile?tab=two-factor');
        $response->assertSessionHasErrors(['code'], null, 'twoFactor');
    }

    public function test_two_factor_confirm_rejects_invalid_code(): void
    {
        $user = $this->makeAcknowledgedUser();
        $user->setTwoFactorPendingSecret(app(Google2FA::class)->generateSecretKey(32));
        $user->save();

        $response = $this->actingAs($user)->post('/profile/2fa/confirm', [
            'code' => '000000',
        ]);

        $response->assertRedirect('/profile?tab=two-factor');
        $response->assertSessionHasErrors(['code'], null, 'twoFactor');

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_authenticated_user_can_disable_two_factor(): void
    {
        $user = $this->makeAcknowledgedUser();
        $user->enableTwoFactor(app(Google2FA::class)->generateSecretKey(32));
        $user->generateRecoveryCodes();
        $user->save();
        $this->assertTrue($user->hasTwoFactorEnabled());

        $response = $this->actingAs($user)->post('/profile/2fa/disable');

        $response->assertRedirect('/profile?tab=two-factor');
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertSame(0, $user->recoveryCodesRemainingCount());
    }

    public function test_unverified_user_profile_redirect_does_not_reuse_search_referer_query(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->withHeader('referer', url('/search?q=foo'))
            ->get('/profile');

        $response->assertRedirect(route('verification.notice', ['intended' => '/profile']));
        $response->assertSessionHas('url.intended', '/profile');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeAcknowledgedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $policy = PrivacyPolicyVersion::query()
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        if ($policy instanceof PrivacyPolicyVersion) {
            UserPrivacyPolicyAcceptance::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'privacy_policy_version_id' => $policy->id,
                ],
                [
                    'accepted_at' => now(),
                    'ip_hash' => hash('sha256', '127.0.0.1'),
                    'user_agent_hash' => hash('sha256', 'phpunit'),
                ]
            );
        }

        return $user;
    }
}
