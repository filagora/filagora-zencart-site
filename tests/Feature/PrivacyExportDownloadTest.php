<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class PrivacyExportDownloadTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_request_owner_can_download_export_using_valid_signed_url(): void
    {
        Storage::fake('local');
        config()->set('filagora-compliance.exports.disk', 'local');

        $requester = User::factory()->create(['email_verified_at' => now()]);
        $exportPath = 'exports/privacy/user-'.$requester->id.'/privacy-export-test.json';
        Storage::disk('local')->put($exportPath, '{"ok":true}');

        $privacyRequest = PrivacyRequest::query()->create([
            'user_id' => $requester->id,
            'type' => PrivacyRequestType::EXPORT->value,
            'status' => PrivacyRequestStatus::FULFILLED->value,
            'requested_at' => now()->subHour(),
            'fulfilled_at' => now()->subMinutes(10),
            'export_path' => $exportPath,
        ]);

        $url = URL::temporarySignedRoute(
            'profile.privacy-requests.export.download',
            now()->addMinutes(30),
            ['privacyRequest' => $privacyRequest->id]
        );

        $this->actingAs($requester)
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_another_user_cannot_download_someone_elses_export(): void
    {
        Storage::fake('local');
        config()->set('filagora-compliance.exports.disk', 'local');

        $requester = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $exportPath = 'exports/privacy/user-'.$requester->id.'/privacy-export-test.json';
        Storage::disk('local')->put($exportPath, '{"ok":true}');

        $privacyRequest = PrivacyRequest::query()->create([
            'user_id' => $requester->id,
            'type' => PrivacyRequestType::EXPORT->value,
            'status' => PrivacyRequestStatus::FULFILLED->value,
            'requested_at' => now()->subHour(),
            'fulfilled_at' => now()->subMinutes(10),
            'export_path' => $exportPath,
        ]);

        $url = URL::temporarySignedRoute(
            'profile.privacy-requests.export.download',
            now()->addMinutes(30),
            ['privacyRequest' => $privacyRequest->id]
        );

        $this->actingAs($otherUser)
            ->get($url)
            ->assertForbidden();
    }

    public function test_profile_privacy_tab_links_to_a_working_export_download(): void
    {
        Storage::fake('local');
        config()->set('filagora-compliance.exports.disk', 'local');

        $requester = User::factory()->create(['email_verified_at' => now()]);
        $exportPath = 'exports/privacy/user-'.$requester->id.'/privacy-export-test.json';
        Storage::disk('local')->put($exportPath, '{"ok":true}');

        PrivacyRequest::query()->create([
            'user_id' => $requester->id,
            'type' => PrivacyRequestType::EXPORT->value,
            'status' => PrivacyRequestStatus::FULFILLED->value,
            'requested_at' => now()->subHour(),
            'fulfilled_at' => now()->subMinutes(10),
            'export_path' => $exportPath,
        ]);

        $html = $this->actingAs($requester)
            ->get('/profile?tab=privacy')
            ->assertOk()
            ->assertSee('Download export')
            ->getContent();

        preg_match('/<a href="([^"]+)"[^>]*>Download export<\/a>/', $html, $matches);
        $this->assertNotEmpty($matches, 'Download export link not found.');

        $this->actingAs($requester)
            ->get(html_entity_decode($matches[1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_profile_privacy_tab_hides_download_for_unfulfilled_or_missing_exports(): void
    {
        Storage::fake('local');
        config()->set('filagora-compliance.exports.disk', 'local');

        $requester = User::factory()->create(['email_verified_at' => now()]);

        PrivacyRequest::query()->create([
            'user_id' => $requester->id,
            'type' => PrivacyRequestType::EXPORT->value,
            'status' => PrivacyRequestStatus::RECEIVED->value,
            'requested_at' => now()->subHours(2),
        ]);
        PrivacyRequest::query()->create([
            'user_id' => $requester->id,
            'type' => PrivacyRequestType::EXPORT->value,
            'status' => PrivacyRequestStatus::FULFILLED->value,
            'requested_at' => now()->subHour(),
            'fulfilled_at' => now()->subMinutes(10),
            'export_path' => 'exports/privacy/user-'.$requester->id.'/deleted-export.json',
        ]);

        $this->actingAs($requester)
            ->get('/profile?tab=privacy')
            ->assertOk()
            ->assertDontSee('Download export');
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Storage::fake('local');
        config()->set('filagora-compliance.exports.disk', 'local');

        $requester = User::factory()->create(['email_verified_at' => now()]);
        $privacyRequest = PrivacyRequest::query()->create([
            'user_id' => $requester->id,
            'type' => PrivacyRequestType::EXPORT->value,
            'status' => PrivacyRequestStatus::FULFILLED->value,
            'requested_at' => now()->subHour(),
            'fulfilled_at' => now()->subMinutes(10),
            'export_path' => 'exports/privacy/user-'.$requester->id.'/privacy-export-test.json',
        ]);

        $this->actingAs($requester)
            ->get(route('profile.privacy-requests.export.download', ['privacyRequest' => $privacyRequest->id]))
            ->assertForbidden();
    }
}
