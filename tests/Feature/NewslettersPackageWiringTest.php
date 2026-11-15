<?php

namespace Tests\Feature;

use App\Filament\RichEditor\Plugins\InsertMediaLibraryImagePlugin;
use App\Jobs\SendNewsletterCampaignEmail;
use App\Models\User;
use Filagora\Newsletters\Contracts\NewsletterMedia;
use Filagora\Newsletters\Filament\RichEditor\EditorSetup;
use Filagora\Newsletters\Jobs\SendCampaignEmail;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\ZenCart\Support\CmsNewsletterMedia;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

/**
 * What the site decides about the newsletters package: the wording, the pages, the media library,
 * who may preview, and that nothing is registered twice.
 */
class NewslettersPackageWiringTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_the_package_serves_the_public_routes_with_the_sites_own_pages(): void
    {
        $this->assertSame('newsletter.show', config('filagora-newsletters.routes.views.show'));
        $this->get(route('newsletter.show'))->assertOk()->assertSee('Zen Cart updates by email.');
    }

    public function test_it_keeps_the_zen_cart_wording_in_the_confirmation_mail_and_the_consent_record(): void
    {
        $this->assertSame('Zen Cart newsletter', config('filagora-newsletters.name'));
        $this->assertSame('I agree to receive the Zen Cart newsletter by email.', app(Subscribers::class)->publicConsentText());
        $this->assertSame('mail', config('filagora-newsletters.delivery.queue'));
        $this->assertSame(90, config('filagora-newsletters.delivery.campaign_daily_limit'));
    }

    public function test_the_media_library_and_its_editor_button_are_the_cms_ones(): void
    {
        $this->assertInstanceOf(CmsNewsletterMedia::class, app(NewsletterMedia::class));
        $this->assertContains('insertMediaLibraryImage', EditorSetup::toolbarButtons());
        $this->assertContains('insertTrackedLink', EditorSetup::toolbarButtons());
        $this->assertContains(InsertMediaLibraryImagePlugin::class, array_map(fn (object $plugin): string => $plugin::class, EditorSetup::plugins()));
    }

    public function test_the_preview_is_for_signed_in_staff_only(): void
    {
        $newsletter = Newsletter::query()->create(['title' => 'T', 'subject' => 'S', 'body_html' => '']);

        $this->get(route('newsletters.preview', $newsletter))->assertRedirect();

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('newsletters.preview', $newsletter))
            ->assertForbidden();
    }

    public function test_the_scheduler_runs_the_queue_command_once_and_the_old_command_names_still_work(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'queue-scheduled'));

        $this->assertCount(1, $events);
        $this->artisan('newsletter:queue-scheduled')->assertExitCode(0);
        $this->artisan('newsletter:export-subscribers', ['path' => sys_get_temp_dir().'/newsletter-site-export.csv'])->assertExitCode(0);
        @unlink(sys_get_temp_dir().'/newsletter-site-export.csv');
    }

    public function test_a_recipient_job_queued_under_the_old_class_name_still_runs(): void
    {
        $job = new SendNewsletterCampaignEmail(41);

        $this->assertInstanceOf(SendCampaignEmail::class, $job);
        $this->assertSame(41, $job->newsletterSendId);
        $this->assertInstanceOf(SendCampaignEmail::class, unserialize(str_replace(
            ['O:'.strlen(SendCampaignEmail::class).':"'.SendCampaignEmail::class.'"'],
            ['O:'.strlen('App\\Jobs\\SendNewsletterCampaignEmail').':"App\\Jobs\\SendNewsletterCampaignEmail"'],
            serialize($job),
        )));
    }

    public function test_the_site_keeps_one_route_for_each_newsletter_name(): void
    {
        foreach (['newsletter.show', 'newsletter.store', 'newsletter.confirm', 'newsletter.unsubscribe', 'newsletter.link-click', 'newsletters.preview', 'profile.newsletter.update'] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
    }
}
