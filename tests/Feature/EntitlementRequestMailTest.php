<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EntitlementRequestMailNotification;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Models\EntitlementRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Members are told their entitlement request was received and how it ended, and staff are told of a
 * new one at the configured address, in the old site's words.
 */
class EntitlementRequestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_request_is_acknowledged_and_staff_are_told(): void
    {
        Notification::fake();
        app(SettingsRegistry::class)->set('entitlement_request_email', 'entitlements@example.test');
        $user = User::factory()->create();

        $request = $this->makeRequest($user);

        Notification::assertSentTo($user, EntitlementRequestMailNotification::class, function (EntitlementRequestMailNotification $mail) use ($user, $request): bool {
            $message = $mail->toMail($user);

            return $message->subject === 'Entitlement request submitted'
                && in_array('Entitlement: forums.paid', $message->introLines, true)
                && $message->actionUrl === route('profile.show', ['tab' => 'entitlements'])
                && in_array('Request ID: #'.$request->getKey(), $message->introLines, true);
        });
        Notification::assertSentOnDemand(EntitlementRequestMailNotification::class, fn (EntitlementRequestMailNotification $mail, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'entitlements@example.test'
            && $mail->toMail($notifiable)->subject === 'New entitlement request submitted');
    }

    public function test_staff_are_not_told_when_no_address_is_set(): void
    {
        Notification::fake();

        $this->makeRequest(User::factory()->create());

        Notification::assertSentOnDemandTimes(EntitlementRequestMailNotification::class, 0);
    }

    public function test_a_status_change_tells_the_member_with_the_staff_note_and_expiry(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $request = $this->makeRequest($user);

        $request->update(['status' => EntitlementRequestStatus::APPROVED->value, 'review_notes' => 'Welcome aboard.', 'expires_at' => now()->addYear()]);

        Notification::assertSentTo($user, EntitlementRequestMailNotification::class, function (EntitlementRequestMailNotification $mail) use ($user): bool {
            if ($mail->kind !== EntitlementRequestMailNotification::STATUS_UPDATED) {
                return false;
            }

            $lines = $mail->toMail($user)->introLines;

            return in_array('From: PENDING', $lines, true)
                && in_array('To: APPROVED', $lines, true)
                && in_array('Staff note: Welcome aboard.', $lines, true)
                && collect($lines)->contains(fn (string $line): bool => str_starts_with($line, 'Grant expires: '));
        });
    }

    private function makeRequest(User $user): EntitlementRequest
    {
        return EntitlementRequest::query()->forceCreate(['user_id' => $user->id, 'entitlement_key' => 'forums.paid', 'status' => EntitlementRequestStatus::PENDING->value, 'request_reason' => 'Please.', 'requested_at' => now()]);
    }
}
