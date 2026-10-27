<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Models\UserPrivacyPolicyAcceptance;
use App\NewsletterSubscriberStatus;
use App\Notifications\NewsletterConfirmSubscriptionNotification;
use App\Services\NewsletterSubscriberService;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterSignupTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_newsletter_signup_creates_pending_subscriber_and_sends_confirmation_notification(): void
    {
        Notification::fake();

        $response = $this->post(route('newsletter.store'), [
            'name' => 'Newsletter User',
            'email' => ' NEWSLETTER@example.TEST ',
            'newsletter_consent' => '1',
        ]);

        $response->assertRedirect(route('newsletter.show'));
        $response->assertSessionHas('newsletter_status', 'If that email can be subscribed, a confirmation link has been sent.');

        $subscriber = NewsletterSubscriber::query()->where('normalized_email', 'newsletter@example.test')->firstOrFail();

        $this->assertSame('NEWSLETTER@example.TEST', $subscriber->email);
        $this->assertSame('Newsletter User', $subscriber->name);
        $this->assertSame(NewsletterSubscriberStatus::Pending, $subscriber->status);
        $this->assertSame('public_signup', $subscriber->source);
        $this->assertSame(NewsletterSubscriberService::PUBLIC_CONSENT_TEXT, $subscriber->consent_text);
        $this->assertSame('public-v1', $subscriber->consent_version);

        Notification::assertSentOnDemand(
            NewsletterConfirmSubscriptionNotification::class,
            function (NewsletterConfirmSubscriptionNotification $notification, array $channels, object $notifiable): bool {
                $mail = $notification->toMail($notifiable);

                return ($notifiable->routes['mail'] ?? null) === 'NEWSLETTER@example.TEST'
                    && $mail->subject === 'Confirm your Zen Cart newsletter subscription';
            }
        );
    }

    public function test_newsletter_signup_honeypot_rejects_bot_submission(): void
    {
        Notification::fake();

        $this->from(route('newsletter.show'))
            ->post(route('newsletter.store'), [
                'name' => 'Bot',
                'email' => 'bot@example.test',
                'newsletter_consent' => '1',
                'website' => 'https://spam.example.test',
            ])
            ->assertRedirect(route('newsletter.show'))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, NewsletterSubscriber::query()->count());
        Notification::assertSentOnDemandTimes(NewsletterConfirmSubscriptionNotification::class, 0);
    }

    public function test_signed_confirmation_link_subscribes_pending_subscriber(): void
    {
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'pending@example.test',
            'normalized_email' => 'pending@example.test',
            'status' => NewsletterSubscriberStatus::Pending,
            'source' => 'public_signup',
        ]);

        $url = app(NewsletterSubscriberService::class)->signedConfirmationUrl($subscriber);

        $this->get($url)
            ->assertOk()
            ->assertSee('Subscription confirmed.');

        $subscriber->refresh();

        $this->assertSame(NewsletterSubscriberStatus::Subscribed, $subscriber->status);
        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNull($subscriber->unsubscribe_reason);
    }

    public function test_unsigned_confirmation_link_is_rejected(): void
    {
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'pending@example.test',
            'normalized_email' => 'pending@example.test',
            'status' => NewsletterSubscriberStatus::Pending,
            'source' => 'public_signup',
        ]);

        $this->get(route('newsletter.confirm', $subscriber))
            ->assertForbidden();

        $this->assertSame(NewsletterSubscriberStatus::Pending, $subscriber->refresh()->status);
    }

    public function test_signed_unsubscribe_link_suppresses_subscriber(): void
    {
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'subscriber@example.test',
            'normalized_email' => 'subscriber@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'source' => 'mailchimp_import',
            'subscribed_at' => now()->subWeek(),
            'confirmed_at' => now()->subWeek(),
        ]);

        $url = app(NewsletterSubscriberService::class)->signedUnsubscribeUrl($subscriber);

        $this->get($url)
            ->assertOk()
            ->assertSee('You are unsubscribed.');

        $subscriber->refresh();

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertSame('signed_link', $subscriber->unsubscribe_reason);
    }

    public function test_footer_newsletter_link_uses_internal_signup_route(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee(route('newsletter.show', absolute: false), false)
            ->assertDontSee('eepurl.com');
    }

    public function test_guest_sees_name_and_email_form_on_newsletter_page(): void
    {
        $this->get(route('newsletter.show'))
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Send Confirmation Link');
    }

    public function test_authenticated_unsubscribed_user_sees_a_consent_only_form(): void
    {
        $user = $this->makeAcknowledgedUser(['email' => 'reader@example.test']);

        $this->actingAs($user)
            ->get(route('newsletter.show'))
            ->assertOk()
            ->assertSee('Subscribe using your account email')
            ->assertSee('reader@example.test')
            ->assertDontSee('Send Confirmation Link');
    }

    public function test_authenticated_subscribed_user_sees_status_and_unsubscribe_option(): void
    {
        $user = $this->makeAcknowledgedUser(['email' => 'reader@example.test']);
        app(NewsletterSubscriberService::class)->subscribeFromProfile($user);

        $this->actingAs($user)
            ->get(route('newsletter.show'))
            ->assertOk()
            ->assertSee('You are subscribed')
            ->assertSee('Unsubscribe')
            ->assertDontSee('Subscribe using your account email');
    }

    public function test_authenticated_user_can_subscribe_from_the_newsletter_page(): void
    {
        $user = $this->makeAcknowledgedUser(['email' => 'reader@example.test']);

        $this->actingAs($user)
            ->from(route('newsletter.show'))
            ->post(route('profile.newsletter.update'), [
                'newsletter_subscribed' => '1',
                'newsletter_consent' => '1',
            ])
            ->assertRedirect(route('newsletter.show'))
            ->assertSessionHas('profile_status', __('profile.flash.newsletter_subscribed'));

        $subscriber = NewsletterSubscriber::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(NewsletterSubscriberStatus::Subscribed, $subscriber->status);
        $this->assertSame('profile', $subscriber->source);
        $this->assertNotNull($subscriber->confirmed_at);
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
