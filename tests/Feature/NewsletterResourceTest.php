<?php

namespace Tests\Feature;

use App\Filament\Pages\NewsletterSettings;
use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Resources\Newsletters\Pages\EditNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Filament\Resources\Newsletters\Pages\ViewNewsletter;
use App\Filament\Resources\Newsletters\RelationManagers\NewsletterLinkClicksRelationManager;
use App\Filament\Resources\Newsletters\RelationManagers\NewsletterSendsRelationManager;
use App\Jobs\SendNewsletterCampaignEmail;
use App\Mail\NewsletterCampaignMail;
use App\Models\Newsletter;
use App\Models\NewsletterBlock;
use App\Models\NewsletterEvent;
use App\Models\NewsletterLinkClick;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\NewsletterEventType;
use App\NewsletterSendStatus;
use App\NewsletterStatus;
use App\NewsletterSubscriberStatus;
use App\Services\NewsletterBrandingService;
use App\Services\NewsletterCampaignMailer;
use App\Services\NewsletterContentRenderer;
use App\Services\NewsletterDeliveryTransport;
use Filagora\Cms\Models\CmsMediaAsset;
use Filagora\Core\Models\Setting;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class NewsletterResourceTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_newsletter_resource_requires_newsletter_entitlement(): void
    {
        $newsletter = $this->newsletter();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get(NewsletterResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(NewsletterResource::getUrl('view', ['record' => $newsletter]))
            ->assertForbidden();
    }

    public function test_staff_with_newsletter_entitlement_can_access_resource_pages(): void
    {
        $newsletter = $this->newsletter();
        $user = $this->newsletterManager();

        foreach ([
            NewsletterResource::getUrl('index'),
            NewsletterResource::getUrl('create'),
            NewsletterResource::getUrl('view', ['record' => $newsletter]),
            NewsletterResource::getUrl('edit', ['record' => $newsletter]),
        ] as $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertTrue(
                in_array($response->status(), [200, 302], true),
                sprintf('Unexpected status %d for %s', $response->status(), $url)
            );
            $this->assertNotSame(route('home'), $response->headers->get('Location'));
        }
    }

    public function test_staff_can_access_newsletter_settings_page(): void
    {
        $response = $this->actingAs($this->newsletterManager())
            ->get(NewsletterSettings::getUrl(panel: 'dashboard'));

        $response->assertOk()
            ->assertSee('Newsletter Settings')
            ->assertSee('Shared header, logo, and footer content used by all newsletter campaigns.');
    }

    public function test_newsletter_preview_page_requires_newsletter_entitlement(): void
    {
        $newsletter = $this->newsletter([
            'subject' => 'Preview restricted subject',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get(route('newsletters.preview', $newsletter))
            ->assertForbidden();
    }

    public function test_staff_can_open_rendered_newsletter_preview_page(): void
    {
        $newsletter = $this->newsletter([
            'subject' => 'Rendered preview subject',
            'preheader' => 'Rendered preview preheader',
            'background_color' => '#E8F1FF',
            'inner_background_color' => '#FFF4D6',
            'content_blocks' => [
                [
                    'type' => 'text',
                    'body_html' => '<p>Rendered preview body</p>',
                ],
            ],
        ]);

        $this->actingAs($this->newsletterManager())
            ->get(route('newsletters.preview', $newsletter))
            ->assertOk()
            ->assertSee('Preview test send. This message has not been sent to newsletter subscribers.')
            ->assertSee('Rendered preview subject')
            ->assertSee('Rendered preview preheader')
            ->assertSee('background:#E8F1FF', false)
            ->assertSee('background:#FFF4D6', false)
            ->assertSee('Rendered preview body', false);
    }

    public function test_rendered_newsletter_preview_uses_shared_branding_and_structured_blocks(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms-media/newsletters/logo.png', 'logo');
        Storage::disk('public')->put('cms-media/newsletters/hero.png', 'hero');

        $logo = CmsMediaAsset::query()->create([
            'disk' => 'public',
            'path' => 'cms-media/newsletters/logo.png',
            'filename' => 'logo.png',
            'mime_type' => 'image/png',
            'size' => 4,
            'alt_text' => 'Zen Cart Logo',
        ]);
        $hero = CmsMediaAsset::query()->create([
            'disk' => 'public',
            'path' => 'cms-media/newsletters/hero.png',
            'filename' => 'hero.png',
            'mime_type' => 'image/png',
            'size' => 4,
            'alt_text' => 'Hero Image',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => NewsletterBrandingService::BRAND_NAME_KEY],
            ['value' => 'Zen Cart', 'value_type' => 'string']
        );
        Setting::query()->updateOrCreate(
            ['key' => NewsletterBrandingService::HEADER_EYEBROW_KEY],
            ['value' => 'Store Platform Updates', 'value_type' => 'string']
        );
        Setting::query()->updateOrCreate(
            ['key' => NewsletterBrandingService::LOGO_ASSET_ID_KEY],
            ['value' => (string) $logo->id, 'value_type' => 'integer']
        );
        Setting::query()->updateOrCreate(
            ['key' => NewsletterBrandingService::LOGO_ALT_KEY],
            ['value' => 'Zen Cart Logo', 'value_type' => 'string']
        );
        Setting::query()->updateOrCreate(
            ['key' => NewsletterBrandingService::FOOTER_TEXT_KEY],
            ['value' => 'Shared footer copy for all newsletters.', 'value_type' => 'text']
        );
        Setting::query()->updateOrCreate(
            ['key' => NewsletterBrandingService::MANAGE_LINK_LABEL_KEY],
            ['value' => 'Manage subscription preferences', 'value_type' => 'string']
        );

        $newsletter = $this->newsletter([
            'subject' => 'Structured update',
            'background_color' => '#E8F1FF',
            'inner_background_color' => '#FFF4D6',
            'content_blocks' => [
                [
                    'type' => 'text',
                    'heading' => 'Release highlights',
                    'body_html' => '<p>Structured body section.</p>',
                ],
                [
                    'type' => 'image',
                    'asset_id' => $hero->id,
                    'asset_alt' => 'Hero Image',
                    'caption' => 'Shared release artwork',
                ],
                [
                    'type' => 'button',
                    'button_label' => 'Read release notes',
                    'button_url' => 'https://example.test/releases',
                ],
            ],
        ]);

        $this->actingAs($this->newsletterManager())
            ->get(route('newsletters.preview', $newsletter))
            ->assertOk()
            ->assertSee('Store Platform Updates')
            ->assertSee('Shared footer copy for all newsletters.')
            ->assertSee('Manage subscription preferences')
            ->assertSee('Release highlights')
            ->assertSee('Structured body section.', false)
            ->assertSee('Shared release artwork')
            ->assertSee('Read release notes')
            ->assertSee('background:#E8F1FF', false)
            ->assertSee('logo.png', false)
            ->assertSee('hero.png', false);
    }

    public function test_newsletter_edit_and_view_pages_include_preview_links(): void
    {
        $newsletter = $this->newsletter();
        $previewUrl = route('newsletters.preview', $newsletter);

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('edit', ['record' => $newsletter]))
            ->assertOk()
            ->assertSee($previewUrl, false);

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('view', ['record' => $newsletter]))
            ->assertOk()
            ->assertSee($previewUrl, false);
    }

    public function test_editing_an_existing_newsletter_does_not_expose_system_header_and_footer_as_editable_blocks(): void
    {
        $newsletter = $this->newsletter();

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('edit', ['record' => $newsletter]))
            ->assertOk()
            ->assertDontSee('The selected block is invalid')
            ->assertDontSee('Shared Header')
            ->assertDontSee('Shared Footer');
    }

    public function test_saving_an_edited_newsletter_still_rewraps_it_with_the_current_header_and_footer(): void
    {
        $user = $this->newsletterManager();
        $newsletter = $this->newsletter();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->fillForm([
                'content_blocks' => [
                    ['type' => 'text', 'heading' => 'Untouched middle content', 'body_html' => '<p>Body</p>'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $headerBlockId = NewsletterBlock::query()->where('key', 'shared_header')->value('id');
        $footerBlockId = NewsletterBlock::query()->where('key', 'shared_footer')->value('id');

        $this->assertSame('reusable', $newsletter->content_blocks[0]['type']);
        $this->assertSame($headerBlockId, $newsletter->content_blocks[0]['newsletter_block_id']);
        $this->assertSame('reusable', $newsletter->content_blocks[array_key_last($newsletter->content_blocks)]['type']);
        $this->assertSame($footerBlockId, $newsletter->content_blocks[array_key_last($newsletter->content_blocks)]['newsletter_block_id']);
    }

    public function test_staff_can_save_from_the_header_action_without_completing_the_wizard(): void
    {
        $user = $this->newsletterManager();
        $newsletter = $this->newsletter();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->fillForm([
                'title' => 'Saved without finishing wizard',
            ])
            ->callAction('save')
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $this->assertSame('Saved without finishing wizard', $newsletter->title);
    }

    public function test_save_for_preview_persists_changes_without_redirecting_or_notifying(): void
    {
        $user = $this->newsletterManager();
        $newsletter = $this->newsletter();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->fillForm([
                'title' => 'Saved for preview refresh',
            ])
            ->call('saveForPreview')
            ->assertHasNoFormErrors()
            ->assertNoRedirect()
            ->assertNotNotified();

        $newsletter->refresh();

        $this->assertSame('Saved for preview refresh', $newsletter->title);
    }

    public function test_staff_can_create_and_edit_draft_newsletter(): void
    {
        $user = $this->newsletterManager();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(CreateNewsletter::class)
            ->fillForm([
                'title' => 'July Project Update',
                'subject' => 'Zen Cart July update',
                'preheader' => 'Project news and release notes.',
                'background_color' => '#E8F1FF',
                'inner_background_color' => '#FFF4D6',
                'content_blocks' => [
                    [
                        'type' => 'text',
                        'heading' => 'Release highlights',
                        'body_html' => '<p>Project news</p>',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $newsletter = Newsletter::query()->where('title', 'July Project Update')->firstOrFail();

        $this->assertSame(NewsletterStatus::Draft, $newsletter->status);
        $this->assertTrue($newsletter->creator->is($user));
        $this->assertTrue($newsletter->updater->is($user));
        $this->assertSame('#E8F1FF', $newsletter->background_color);
        $this->assertSame('#FFF4D6', $newsletter->inner_background_color);
        $this->assertSame('reusable', $newsletter->content_blocks[0]['type']);
        $this->assertSame(NewsletterBlock::query()->where('key', 'shared_header')->value('id'), $newsletter->content_blocks[0]['newsletter_block_id']);
        $this->assertSame('reusable', $newsletter->content_blocks[array_key_last($newsletter->content_blocks)]['type']);
        $this->assertSame(NewsletterBlock::query()->where('key', 'shared_footer')->value('id'), $newsletter->content_blocks[array_key_last($newsletter->content_blocks)]['newsletter_block_id']);
        $this->assertStringContainsString('Release highlights', $newsletter->body_html);
        $this->assertStringContainsString('<p>Project news</p>', $newsletter->body_html);
        $this->assertSame("Release highlights\nProject news", $newsletter->body_text);

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->fillForm([
                'title' => 'July Project Update Revised',
                'subject' => 'Zen Cart July update revised',
                'preheader' => 'Updated project news.',
                'background_color' => '#FFF4D6',
                'inner_background_color' => '#EEF7E8',
                'content_blocks' => [
                    [
                        'type' => 'text',
                        'heading' => 'What changed',
                        'body_html' => '<p>Updated project news</p>',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $this->assertSame('July Project Update Revised', $newsletter->title);
        $this->assertSame('Zen Cart July update revised', $newsletter->subject);
        $this->assertTrue($newsletter->updater->is($user));
        $this->assertSame('#FFF4D6', $newsletter->background_color);
        $this->assertSame('#EEF7E8', $newsletter->inner_background_color);
        $this->assertSame('reusable', $newsletter->content_blocks[0]['type']);
        $this->assertSame(NewsletterBlock::query()->where('key', 'shared_header')->value('id'), $newsletter->content_blocks[0]['newsletter_block_id']);
        $this->assertSame('reusable', $newsletter->content_blocks[array_key_last($newsletter->content_blocks)]['type']);
        $this->assertSame(NewsletterBlock::query()->where('key', 'shared_footer')->value('id'), $newsletter->content_blocks[array_key_last($newsletter->content_blocks)]['newsletter_block_id']);
        $this->assertSame("What changed\nUpdated project news", $newsletter->body_text);
    }

    public function test_sent_newsletter_is_not_editable(): void
    {
        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('edit', ['record' => $newsletter]))
            ->assertForbidden();
    }

    public function test_staff_can_delete_a_draft_newsletter(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter();

        Livewire::test(ListNewsletters::class)
            ->callTableAction('delete', $newsletter)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($newsletter);
    }

    public function test_staff_can_delete_a_scheduled_newsletter_with_no_sends(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);

        Livewire::test(ListNewsletters::class)
            ->callTableAction('delete', $newsletter)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($newsletter);
    }

    public function test_sent_newsletter_cannot_be_deleted(): void
    {
        $user = $this->newsletterManager();
        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('delete', $newsletter));

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(ListNewsletters::class)
            ->assertTableActionHidden('delete', $newsletter);

        $this->assertModelExists($newsletter);
    }

    public function test_newsletter_with_send_records_cannot_be_deleted_even_if_status_reverted(): void
    {
        $user = $this->newsletterManager();
        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Draft,
        ]);
        $subscriber = $this->subscriber();
        NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Sent,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('delete', $newsletter));
    }

    public function test_newsletter_send_and_event_relationships_capture_delivery_state(): void
    {
        $newsletter = $this->newsletter();
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'subscriber@example.test',
            'normalized_email' => 'subscriber@example.test',
            'name' => 'Subscriber',
            'status' => NewsletterSubscriberStatus::Subscribed,
            'source' => 'public_signup',
            'subscribed_at' => now(),
            'confirmed_at' => now(),
        ]);

        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Sent,
            'queued_at' => now()->subMinute(),
            'sent_at' => now(),
            'provider_message_id' => 'message-123',
        ]);

        NewsletterEvent::query()->create([
            'newsletter_send_id' => $send->id,
            'type' => NewsletterEventType::Delivered,
            'occurred_at' => now(),
            'payload' => ['provider' => 'mailgun'],
        ]);

        $this->assertTrue($send->newsletter->is($newsletter));
        $this->assertTrue($send->subscriber->is($subscriber));
        $this->assertTrue($newsletter->sends->first()->is($send));
        $this->assertSame(NewsletterEventType::Delivered, $send->events->first()->type);
    }

    public function test_staff_can_send_newsletter_preview_email(): void
    {
        Mail::fake();

        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter([
            'subject' => 'Preview subject',
            'content_blocks' => [
                [
                    'type' => 'text',
                    'body_html' => '<p>Preview body</p>',
                ],
            ],
        ]);

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->callAction('send_test', data: [
                'email' => 'preview@example.test',
            ])
            ->assertHasNoFormErrors();

        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $mail) use ($newsletter): bool {
            return $mail->hasTo('preview@example.test')
                && $mail->newsletter->is($newsletter)
                && $mail->deliveryProvider === 'mailtrap'
                && $mail->preview === true;
        });
    }

    public function test_newsletter_campaign_mail_renders_campaign_content(): void
    {
        $newsletter = $this->newsletter([
            'subject' => 'Rendered subject',
            'preheader' => 'Rendered preheader',
            'content_blocks' => [
                [
                    'type' => 'text',
                    'body_html' => '<p>Rendered <strong>HTML</strong> body</p>',
                ],
            ],
        ]);

        $html = (new NewsletterCampaignMail(
            newsletter: $newsletter,
            unsubscribeUrl: 'https://example.test/unsubscribe',
            preview: true,
        ))->render();

        $this->assertStringContainsString('Rendered subject', $html);
        $this->assertStringContainsString('Rendered preheader', $html);
        $this->assertStringContainsString('Rendered <strong>HTML</strong> body', $html);
        $this->assertStringContainsString('https://example.test/unsubscribe', $html);
    }

    public function test_staff_can_queue_newsletter_send_for_sendable_subscribers(): void
    {
        Bus::fake();

        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter();
        $sendable = $this->subscriber([
            'email' => 'sendable@example.test',
            'normalized_email' => 'sendable@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);
        $this->subscriber([
            'email' => 'pending@example.test',
            'normalized_email' => 'pending@example.test',
            'status' => NewsletterSubscriberStatus::Pending,
        ]);
        $this->subscriber([
            'email' => 'unsubscribed@example.test',
            'normalized_email' => 'unsubscribed@example.test',
            'status' => NewsletterSubscriberStatus::Unsubscribed,
        ]);
        $this->subscriber([
            'email' => 'suppressed@example.test',
            'normalized_email' => 'suppressed@example.test',
            'suppression_hash' => hash('sha256', 'suppressed@example.test'),
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);

        Livewire::test(ViewNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->callAction('queue_send')
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $this->assertSame(NewsletterStatus::Sending, $newsletter->status);
        $this->assertSame(1, NewsletterSend::query()->count());

        $send = NewsletterSend::query()->firstOrFail();

        $this->assertTrue($send->subscriber->is($sendable));
        $this->assertSame(NewsletterSendStatus::Queued, $send->status);
        $this->assertNotNull($send->queued_at);
        $this->assertNotNull($send->dispatched_at);

        Bus::assertDispatched(SendNewsletterCampaignEmail::class, function (SendNewsletterCampaignEmail $job) use ($send): bool {
            return $job->newsletterSendId === $send->id;
        });
    }

    public function test_newsletter_send_respects_configured_daily_campaign_limit(): void
    {
        Bus::fake();
        config()->set('newsletter.delivery.campaign_daily_limit', 1);

        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter();
        $first = $this->subscriber([
            'email' => 'first@example.test',
            'normalized_email' => 'first@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);
        $second = $this->subscriber([
            'email' => 'second@example.test',
            'normalized_email' => 'second@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);

        Livewire::test(ViewNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->callAction('queue_send')
            ->assertHasNoFormErrors();

        $this->assertSame(2, NewsletterSend::query()->count());

        $this->assertNotNull(NewsletterSend::query()->whereBelongsTo($first, 'subscriber')->firstOrFail()->dispatched_at);
        $this->assertNull(NewsletterSend::query()->whereBelongsTo($second, 'subscriber')->firstOrFail()->dispatched_at);

        Bus::assertDispatchedTimes(SendNewsletterCampaignEmail::class, 1);
    }

    public function test_staff_can_schedule_newsletter_send(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter();
        $scheduledAt = now()->addHour()->seconds(0);

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->callAction('schedule_send', data: [
                'scheduled_at' => $scheduledAt,
            ])
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $this->assertSame(NewsletterStatus::Scheduled, $newsletter->status);
        $this->assertSame($scheduledAt->format('Y-m-d H:i'), $newsletter->scheduled_at?->format('Y-m-d H:i'));
    }

    public function test_staff_can_reschedule_an_already_scheduled_newsletter(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);
        $rescheduledAt = now()->addDay()->seconds(0);

        Livewire::test(EditNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->callAction('schedule_send', data: [
                'scheduled_at' => $rescheduledAt,
            ])
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $this->assertSame(NewsletterStatus::Scheduled, $newsletter->status);
        $this->assertSame($rescheduledAt->format('Y-m-d H:i'), $newsletter->scheduled_at?->format('Y-m-d H:i'));
    }

    public function test_staff_can_cancel_a_scheduled_newsletter_send(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);

        Livewire::test(ViewNewsletter::class, ['record' => $newsletter->getRouteKey()])
            ->callAction('cancel_schedule')
            ->assertHasNoFormErrors();

        $newsletter->refresh();

        $this->assertSame(NewsletterStatus::Draft, $newsletter->status);
        $this->assertNull($newsletter->scheduled_at);
    }

    public function test_edit_and_view_pages_show_the_newsletter_status_badge(): void
    {
        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('edit', ['record' => $newsletter]))
            ->assertOk()
            ->assertSee('Scheduled');

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('view', ['record' => $newsletter]))
            ->assertOk()
            ->assertSee('Scheduled');
    }

    public function test_queue_scheduled_newsletters_command_queues_only_due_campaigns(): void
    {
        Bus::fake();

        $this->subscriber([
            'email' => 'due@example.test',
            'normalized_email' => 'due@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);

        $due = $this->newsletter([
            'title' => 'Due newsletter',
            'status' => NewsletterStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);
        $future = $this->newsletter([
            'title' => 'Future newsletter',
            'status' => NewsletterStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);
        $draft = $this->newsletter([
            'title' => 'Draft newsletter',
            'status' => NewsletterStatus::Draft,
        ]);

        $this->artisan('newsletter:queue-scheduled')
            ->expectsOutput("Queued newsletter [{$due->id}] with 1 delivery record(s); dispatched 1 recipient job(s).")
            ->expectsOutput('Processed 1 due scheduled newsletter(s); queued 1 delivery record(s); dispatched 1 recipient job(s).')
            ->assertSuccessful();

        $due->refresh();
        $future->refresh();
        $draft->refresh();

        $this->assertSame(NewsletterStatus::Sending, $due->status);
        $this->assertSame(NewsletterStatus::Scheduled, $future->status);
        $this->assertSame(NewsletterStatus::Draft, $draft->status);
        $this->assertSame(1, NewsletterSend::query()->whereBelongsTo($due)->count());
        $this->assertSame(0, NewsletterSend::query()->whereBelongsTo($future)->count());

        Bus::assertDispatched(SendNewsletterCampaignEmail::class);
    }

    public function test_queued_newsletter_send_job_sends_email_and_marks_campaign_complete(): void
    {
        Mail::fake();

        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Sending,
        ]);
        $subscriber = $this->subscriber([
            'email' => 'recipient@example.test',
            'normalized_email' => 'recipient@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);
        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Queued,
            'queued_at' => now(),
        ]);

        (new SendNewsletterCampaignEmail($send->id))->handle(app(NewsletterCampaignMailer::class));

        $send->refresh();
        $newsletter->refresh();

        $this->assertSame(NewsletterSendStatus::Sent, $send->status);
        $this->assertNotNull($send->sent_at);
        $this->assertSame(NewsletterStatus::Sent, $newsletter->status);
        $this->assertNotNull($newsletter->sent_at);

        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $mail): bool {
            return $mail->hasTo('recipient@example.test')
                && $mail->deliveryProvider === 'mailtrap'
                && $mail->preview === false;
        });
    }

    public function test_newsletter_delivery_provider_can_be_changed_from_mailgun(): void
    {
        Mail::fake();
        config()->set('newsletter.delivery.provider', 'brevo');

        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Sending,
        ]);
        $subscriber = $this->subscriber([
            'email' => 'brevo@example.test',
            'normalized_email' => 'brevo@example.test',
            'status' => NewsletterSubscriberStatus::Subscribed,
        ]);
        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Queued,
            'queued_at' => now(),
        ]);

        (new SendNewsletterCampaignEmail($send->id))->handle(app(NewsletterCampaignMailer::class));

        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $mail): bool {
            return $mail->hasTo('brevo@example.test')
                && $mail->deliveryProvider === 'brevo';
        });
    }

    public function test_newsletter_delivery_transport_reads_configured_mailer(): void
    {
        config()->set('newsletter.delivery.provider', 'brevo');

        $transport = app(NewsletterDeliveryTransport::class);

        $this->assertSame('brevo', $transport->mailer());
        $this->assertSame('brevo', $transport->provider());
        $this->assertTrue($transport->isProvider('BREVO'));
    }

    public function test_queued_newsletter_send_job_skips_subscriber_that_is_no_longer_sendable(): void
    {
        Mail::fake();

        $newsletter = $this->newsletter([
            'status' => NewsletterStatus::Sending,
        ]);
        $subscriber = $this->subscriber([
            'email' => 'changed@example.test',
            'normalized_email' => 'changed@example.test',
            'status' => NewsletterSubscriberStatus::Unsubscribed,
        ]);
        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Queued,
            'queued_at' => now(),
        ]);

        (new SendNewsletterCampaignEmail($send->id))->handle(app(NewsletterCampaignMailer::class));

        $send->refresh();

        $this->assertSame(NewsletterSendStatus::Skipped, $send->status);
        $this->assertNotNull($send->skipped_at);
        $this->assertSame('Subscriber is no longer sendable.', $send->error);
        Mail::assertNothingSent();
    }

    public function test_reusable_content_block_resolves_library_block_content_at_render_time(): void
    {
        $block = NewsletterBlock::query()->create([
            'key' => 'spring-sale-banner',
            'name' => 'Spring sale banner',
            'type' => 'button',
            'content' => [
                'button_label' => 'Shop the spring sale',
                'button_url' => 'https://example.test/spring-sale',
            ],
        ]);

        $newsletter = $this->newsletter([
            'content_blocks' => [
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ],
        ]);

        $html = app(NewsletterContentRenderer::class)->renderHtml($newsletter);

        $this->assertStringContainsString('Shop the spring sale', $html);
        $this->assertStringNotContainsString('https://example.test/spring-sale', $html);
        $this->assertStringContainsString(route('newsletter.link-click', ['newsletter' => $newsletter, 'blockKey' => $newsletter->content_blocks[1]['block_key']]), $html);
    }

    public function test_editing_a_reusable_block_updates_an_unsent_drafts_rendered_output(): void
    {
        $block = NewsletterBlock::query()->create([
            'key' => 'promo-banner',
            'name' => 'Promo banner',
            'type' => 'text',
            'content' => ['heading' => 'Original promo copy'],
        ]);

        $newsletter = $this->newsletter([
            'content_blocks' => [
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ],
        ]);

        $renderer = app(NewsletterContentRenderer::class);

        $this->assertStringContainsString('Original promo copy', $renderer->renderHtml($newsletter));

        $block->update(['content' => ['heading' => 'Updated promo copy']]);

        $this->assertStringContainsString('Updated promo copy', $renderer->renderHtml($newsletter->fresh()));
        $this->assertStringNotContainsString('Original promo copy', $renderer->renderHtml($newsletter->fresh()));
    }

    public function test_inactive_reusable_block_is_dropped_from_rendered_output(): void
    {
        $block = NewsletterBlock::query()->create([
            'key' => 'retired-banner',
            'name' => 'Retired banner',
            'type' => 'text',
            'content' => ['heading' => 'Retired banner copy'],
            'is_active' => false,
        ]);

        $newsletter = $this->newsletter([
            'content_blocks' => [
                ['type' => 'reusable', 'newsletter_block_id' => $block->id],
            ],
        ]);

        $html = app(NewsletterContentRenderer::class)->renderHtml($newsletter);

        $this->assertStringNotContainsString('Retired banner copy', $html);
    }

    public function test_prepared_blocks_always_wrap_content_with_the_system_header_and_footer_references(): void
    {
        $renderer = app(NewsletterContentRenderer::class);

        $prepared = $renderer->preparedBlocks([
            ['type' => 'text', 'heading' => 'Middle content'],
        ]);

        $headerBlockId = NewsletterBlock::query()->where('key', 'shared_header')->value('id');
        $footerBlockId = NewsletterBlock::query()->where('key', 'shared_footer')->value('id');

        $this->assertSame('reusable', $prepared[0]['type']);
        $this->assertSame($headerBlockId, $prepared[0]['newsletter_block_id']);
        $this->assertSame('reusable', $prepared[array_key_last($prepared)]['type']);
        $this->assertSame($footerBlockId, $prepared[array_key_last($prepared)]['newsletter_block_id']);
        $this->assertNotEmpty($prepared[0]['block_key']);
        $this->assertNotEmpty($prepared[1]['block_key']);
    }

    public function test_newsletter_resource_registers_sends_and_link_clicks_relation_managers(): void
    {
        $relations = NewsletterResource::getRelations();

        $this->assertContains(NewsletterSendsRelationManager::class, $relations);
        $this->assertContains(NewsletterLinkClicksRelationManager::class, $relations);
    }

    public function test_sends_relation_manager_lists_recipient_deliveries(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter();
        $subscriber = $this->subscriber([
            'email' => 'relation-manager@example.test',
            'normalized_email' => 'relation-manager@example.test',
        ]);
        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Sent,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        Livewire::test(NewsletterSendsRelationManager::class, [
            'ownerRecord' => $newsletter,
            'pageClass' => ViewNewsletter::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$send]);
    }

    public function test_link_clicks_relation_manager_lists_recorded_clicks(): void
    {
        $this->actingAs($this->newsletterManager());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        $newsletter = $this->newsletter();
        $click = NewsletterLinkClick::query()->create([
            'newsletter_id' => $newsletter->id,
            'label' => 'Read more',
            'destination_url' => 'https://example.test/read-more',
            'clicked_at' => now(),
        ]);

        Livewire::test(NewsletterLinkClicksRelationManager::class, [
            'ownerRecord' => $newsletter,
            'pageClass' => ViewNewsletter::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$click]);
    }

    public function test_view_page_shows_delivery_and_engagement_stats(): void
    {
        $newsletter = $this->newsletter();
        $subscriber = $this->subscriber([
            'email' => 'stats@example.test',
            'normalized_email' => 'stats@example.test',
        ]);
        $send = NewsletterSend::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'normalized_email' => $subscriber->normalized_email,
            'name' => $subscriber->name,
            'status' => NewsletterSendStatus::Sent,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);
        NewsletterEvent::query()->create([
            'newsletter_send_id' => $send->id,
            'type' => NewsletterEventType::Delivered,
            'occurred_at' => now(),
        ]);
        NewsletterEvent::query()->create([
            'newsletter_send_id' => $send->id,
            'type' => NewsletterEventType::Opened,
            'occurred_at' => now(),
        ]);
        NewsletterLinkClick::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_send_id' => $send->id,
            'block_key' => 'stats-block-key',
            'label' => 'Stats CTA',
            'destination_url' => 'https://example.test/stats-cta',
            'clicked_at' => now(),
        ]);

        $this->actingAs($this->newsletterManager())
            ->get(NewsletterResource::getUrl('view', ['record' => $newsletter]))
            ->assertOk()
            ->assertSee('Delivery')
            ->assertSee('Engagement')
            ->assertSee('Stats CTA');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newsletter(array $attributes = []): Newsletter
    {
        $contentBlocks = $attributes['content_blocks'] ?? [
            [
                'type' => 'text',
                'body_html' => '<p>Project news</p>',
            ],
        ];
        $renderer = app(NewsletterContentRenderer::class);
        $preparedBlocks = $renderer->preparedBlocks($contentBlocks);

        return Newsletter::query()->create([
            'title' => $attributes['title'] ?? 'Project Update',
            'subject' => $attributes['subject'] ?? 'Zen Cart project update',
            'preheader' => $attributes['preheader'] ?? 'Latest Zen Cart news.',
            'background_color' => $attributes['background_color'] ?? '#F4F1EA',
            'inner_background_color' => $attributes['inner_background_color'] ?? '#FBF8F2',
            'body_html' => $attributes['body_html'] ?? $renderer->htmlFromBlocks($preparedBlocks),
            'body_text' => $attributes['body_text'] ?? $renderer->textFromBlocks($preparedBlocks),
            'content_blocks' => $preparedBlocks,
            'status' => $attributes['status'] ?? NewsletterStatus::Draft,
            'scheduled_at' => $attributes['scheduled_at'] ?? null,
            'sent_at' => $attributes['sent_at'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscriber(array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->create([
            'email' => $attributes['email'] ?? 'subscriber@example.test',
            'normalized_email' => $attributes['normalized_email'] ?? 'subscriber@example.test',
            'suppression_hash' => $attributes['suppression_hash'] ?? null,
            'name' => $attributes['name'] ?? 'Subscriber',
            'status' => $attributes['status'] ?? NewsletterSubscriberStatus::Subscribed,
            'source' => $attributes['source'] ?? 'public_signup',
            'subscribed_at' => $attributes['subscribed_at'] ?? now(),
            'confirmed_at' => $attributes['confirmed_at'] ?? now(),
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
