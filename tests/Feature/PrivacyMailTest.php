<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PrivacyMailNotification;
use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Notifications\PrivacyRequestSubmittedNotification;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The privacy request mail reads as it did on the old site, with its links, and the compliance
 * package's own mail is off.
 */
class PrivacyMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_submitted_request_is_acknowledged_with_a_link_to_the_privacy_tab_and_staff_are_told(): void
    {
        Notification::fake();
        app(SettingsRegistry::class)->set('dsar_request_email', 'dsar@example.test');
        [$request, $user] = $this->request();

        event(new PrivacyRequestSubmitted($request));

        Notification::assertSentTo($user, PrivacyMailNotification::class, function (PrivacyMailNotification $mail) use ($user): bool {
            $message = $mail->toMail($user);

            return $mail->kind === PrivacyMailNotification::SUBMITTED_USER
                && $message->subject === 'We received your privacy request'
                && $message->actionUrl === route('profile.show', ['tab' => 'privacy'])
                && in_array('Your privacy request has been received and queued for review.', $message->introLines, true);
        });
        Notification::assertSentOnDemand(PrivacyMailNotification::class, fn (PrivacyMailNotification $mail, array $channels, object $notifiable): bool => $mail->kind === PrivacyMailNotification::SUBMITTED_STAFF
            && $notifiable->routes['mail'] === 'dsar@example.test'
            && $mail->toMail($notifiable)->actionUrl === route('filament.dashboard.resources.privacy-requests.view', ['record' => $request->getKey()]));
        Notification::assertNotSentTo($user, PrivacyRequestSubmittedNotification::class);
    }

    public function test_a_finished_export_carries_a_signed_download_link(): void
    {
        [$request, $user] = $this->request(['type' => PrivacyRequestType::EXPORT->value, 'status' => PrivacyRequestStatus::FULFILLED->value, 'export_path' => 'exports/privacy/1.zip']);

        $mail = (new PrivacyMailNotification(PrivacyMailNotification::STATUS_UPDATED, $request, PrivacyRequestStatus::IN_REVIEW, PrivacyRequestStatus::FULFILLED))->toMail($user);

        $this->assertSame('Privacy request status updated', $mail->subject);
        $this->assertContains('From: IN REVIEW', $mail->introLines);
        $this->assertContains('To: FULFILLED', $mail->introLines);
        $this->assertSame('Download Your Data Export', $mail->actionText);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
    }

    public function test_a_status_change_and_a_reply_go_to_the_requester(): void
    {
        Notification::fake();
        [$request, $user] = $this->request();

        event(new PrivacyRequestStatusChanged($request, PrivacyRequestStatus::RECEIVED, PrivacyRequestStatus::IN_REVIEW));
        event(new PrivacyReplySent($request, 'We need one more detail.'));

        Notification::assertSentTo($user, PrivacyMailNotification::class, fn (PrivacyMailNotification $mail): bool => $mail->kind === PrivacyMailNotification::STATUS_UPDATED);
        Notification::assertSentTo($user, PrivacyMailNotification::class, fn (PrivacyMailNotification $mail): bool => $mail->kind === PrivacyMailNotification::STAFF_REPLY
            && in_array('We need one more detail.', $mail->toMail($user)->introLines, true));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: PrivacyRequest, 1: User}
     */
    private function request(array $attributes = []): array
    {
        $user = User::factory()->create();
        $request = PrivacyRequest::query()->forceCreate($attributes + ['user_id' => $user->id, 'type' => PrivacyRequestType::ACCESS->value, 'status' => PrivacyRequestStatus::RECEIVED->value, 'requested_at' => now()]);

        return [$request, $user];
    }
}
