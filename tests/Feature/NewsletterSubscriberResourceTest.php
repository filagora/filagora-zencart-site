<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\CreateNewsletterSubscriber;
use App\Filament\Resources\NewsletterSubscribers\Pages\EditNewsletterSubscriber;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\NewsletterSubscriberStatus;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterSubscriberResourceTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_newsletter_subscriber_resource_requires_newsletter_entitlement(): void
    {
        $subscriber = $this->subscriber();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get(NewsletterSubscriberResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(NewsletterSubscriberResource::getUrl('view', ['record' => $subscriber]))
            ->assertForbidden();
    }

    public function test_staff_with_newsletter_entitlement_can_access_resource_pages(): void
    {
        $subscriber = $this->subscriber();
        $user = $this->newsletterManager();

        foreach ([
            NewsletterSubscriberResource::getUrl('index'),
            NewsletterSubscriberResource::getUrl('create'),
            NewsletterSubscriberResource::getUrl('view', ['record' => $subscriber]),
            NewsletterSubscriberResource::getUrl('edit', ['record' => $subscriber]),
        ] as $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertTrue(
                in_array($response->status(), [200, 302], true),
                sprintf('Unexpected status %d for %s', $response->status(), $url)
            );
            $this->assertNotSame(route('home'), $response->headers->get('Location'));
        }
    }

    public function test_staff_can_unsubscribe_and_confirm_subscriber_from_table_actions(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $subscriber = $this->subscriber([
            'status' => NewsletterSubscriberStatus::Subscribed,
            'subscribed_at' => now()->subWeek(),
            'confirmed_at' => now()->subWeek(),
        ]);

        Livewire::test(ListNewsletterSubscribers::class)
            ->callTableAction('unsubscribe', $subscriber);

        $subscriber->refresh();

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertSame('admin', $subscriber->unsubscribe_reason);
        $this->assertNotNull($subscriber->unsubscribed_at);

        Livewire::test(ListNewsletterSubscribers::class)
            ->callTableAction('confirm_subscription', $subscriber, [
                'reason' => 'Subscriber requested resubscribe by support ticket.',
            ]);

        $subscriber->refresh();

        $this->assertSame(NewsletterSubscriberStatus::Subscribed, $subscriber->status);
        $this->assertSame('admin', $subscriber->source);
        $this->assertStringContainsString('Subscriber requested resubscribe', (string) $subscriber->consent_text);
        $this->assertSame('admin-v1', $subscriber->consent_version);
        $this->assertNull($subscriber->unsubscribe_reason);
    }

    public function test_create_and_edit_normalize_email_server_side(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(CreateNewsletterSubscriber::class)
            ->fillForm([
                'email' => 'AdminCreate@Example.TEST',
                'name' => 'Admin Created',
                'source' => 'admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscriber = NewsletterSubscriber::query()->where('normalized_email', 'admincreate@example.test')->firstOrFail();

        $this->assertSame('AdminCreate@Example.TEST', $subscriber->email);
        $this->assertSame(NewsletterSubscriberStatus::Pending, $subscriber->status);

        Livewire::test(EditNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])
            ->fillForm([
                'email' => 'UpdatedAdmin@Example.TEST',
                'name' => 'Updated Admin',
                'source' => 'admin',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscriber->refresh();

        $this->assertSame('UpdatedAdmin@Example.TEST', $subscriber->email);
        $this->assertSame('updatedadmin@example.test', $subscriber->normalized_email);
        $this->assertSame('Updated Admin', $subscriber->name);
    }

    public function test_staff_can_bulk_unsubscribe_selected_subscribers(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $subscribed = $this->subscriber([
            'email' => 'bulk-a@example.test',
            'normalized_email' => 'bulk-a@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'subscribed_at' => now()->subWeek(),
        ]);
        $alreadyUnsubscribed = $this->subscriber([
            'email' => 'bulk-b@example.test',
            'normalized_email' => 'bulk-b@example.test',
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now()->subDay(),
        ]);
        $untouched = $this->subscriber([
            'email' => 'bulk-c@example.test',
            'normalized_email' => 'bulk-c@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);

        Livewire::test(ListNewsletterSubscribers::class)
            ->selectTableRecords([$subscribed->id, $alreadyUnsubscribed->id])
            ->callAction(TestAction::make('bulk_unsubscribe')->table()->bulk())
            ->assertNotified();

        $subscribed->refresh();
        $alreadyUnsubscribed->refresh();
        $untouched->refresh();

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $subscribed->status);
        $this->assertSame('admin_bulk', $subscribed->unsubscribe_reason);
        $this->assertNotNull($subscribed->unsubscribed_at);

        $this->assertSame(NewsletterSubscriberStatus::Unsubscribed, $alreadyUnsubscribed->status);

        $this->assertSame(NewsletterSubscriberStatus::Subscribed, $untouched->status);
    }

    public function test_staff_can_export_selected_subscribers_to_csv(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $included = $this->subscriber([
            'email' => 'exported-a@example.test',
            'normalized_email' => 'exported-a@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);
        $excluded = $this->subscriber([
            'email' => 'excluded-b@example.test',
            'normalized_email' => 'excluded-b@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);

        $response = Livewire::test(ListNewsletterSubscribers::class)
            ->selectTableRecords([$included->id])
            ->callAction(TestAction::make('bulk_export')->table()->bulk());

        $response->assertFileDownloaded();

        $content = base64_decode((string) data_get($response->effects, 'download.content'));

        $this->assertStringContainsString('exported-a@example.test', $content);
        $this->assertStringNotContainsString('excluded-b@example.test', $content);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscriber(array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create([
            'email' => $attributes['email'] ?? 'subscriber@example.test',
            'normalized_email' => $attributes['normalized_email'] ?? 'subscriber@example.test',
            'name' => $attributes['name'] ?? 'Subscriber',
            'status' => $attributes['status'] ?? NewsletterSubscriberStatus::Pending,
            'source' => $attributes['source'] ?? 'public_signup',
            'subscribed_at' => $attributes['subscribed_at'] ?? null,
            'confirmed_at' => $attributes['confirmed_at'] ?? null,
            'unsubscribed_at' => $attributes['unsubscribed_at'] ?? null,
        ]);
    }

    private function newsletterManager(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.newsletter.manage');

        return $user;
    }

    private function grant(User $user, string $entitlement): void
    {
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => $entitlement,
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
    }
}
