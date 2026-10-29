<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PluginMailNotification;
use App\Notifications\PluginReportMailNotification;
use Filagora\Plugins\Enums\ModerationAction;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Events\PluginModerated;
use Filagora\Plugins\Events\PluginSubmitted;
use Filagora\Plugins\Events\ReleaseRejected;
use Filagora\Plugins\Events\SubscribersToNotify;
use Filagora\Plugins\Models\PluginReport;
use Filagora\Plugins\Models\PluginType;
use Filagora\Plugins\Notifications\PluginNotification;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * The plugin mail reads as it did on the old site (subjects, sections, the moderator's note, the ids
 * moderators quote) and plugin updates carry the unsubscribe footer. The package's own mail is off.
 */
class PluginMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rejected_release_names_the_note_and_uses_the_old_subject(): void
    {
        Notification::fake();
        [$plugin, $release, $owner] = $this->pluginWithRelease();

        event(new ReleaseRejected($plugin, $release, 'Please add a readme.'));

        Notification::assertSentTo($owner, PluginMailNotification::class, function (PluginMailNotification $mail) use ($owner): bool {
            $message = $mail->toMail($owner);

            return $message->subject === 'Plugin release rejected: Admin Audit Trail v1.0.0'
                && str_contains((string) $message->render(), 'Please add a readme.')
                && str_contains((string) $message->render(), 'Your plugin release submission was reviewed and rejected.');
        });
        Notification::assertNotSentTo($owner, PluginNotification::class);
    }

    public function test_an_approved_plugin_shows_the_id_moderators_quote(): void
    {
        Notification::fake();
        [$plugin, , $owner] = $this->pluginWithRelease();
        $plugin->forceFill(['legacy_vbulletin_file_id' => 2441])->save();

        event(new PluginModerated($plugin, ModerationAction::APPROVE, User::factory()->create(), ''));

        Notification::assertSentTo($owner, PluginMailNotification::class, function (PluginMailNotification $mail) use ($owner): bool {
            $message = $mail->toMail($owner);

            return $message->subject === 'Plugin approved: Admin Audit Trail' && str_contains((string) $message->render(), '2441');
        });
    }

    public function test_a_new_plugin_acknowledges_the_author_and_tells_support(): void
    {
        Notification::fake();
        config(['filagora-plugins.mail.support_email' => 'plugins@zen-cart.com']);
        [$plugin, $release, $owner] = $this->pluginWithRelease();

        event(new PluginSubmitted($plugin, $release, $owner));

        Notification::assertSentTo($owner, PluginMailNotification::class);
        Notification::assertSentOnDemand(PluginMailNotification::class, fn (PluginMailNotification $mail, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'plugins@zen-cart.com'
            && $mail->toMail($notifiable)->subject === 'Plugin submitted for moderation: Admin Audit Trail');
    }

    public function test_a_plugin_update_carries_the_unsubscribe_footer_and_headers(): void
    {
        [$plugin, $release] = $this->pluginWithRelease();
        $subscriber = User::factory()->create();

        $mail = (new PluginMailNotification('release_updated', 'updated', ['plugin' => $plugin->name, 'version' => $release->displayVersion()], 'plugin-updated', ['pluginName' => $plugin->name, 'releaseVersion' => $release->displayVersion(), 'actionUrl' => route('plugins.show', $plugin)], withUnsubscribe: true))->toMail($subscriber);
        $html = (string) $mail->render();

        $this->assertSame('Plugin updated: Admin Audit Trail v1.0.0', $mail->subject);
        $this->assertStringContainsString('You are receiving this because you subscribed to this plugin.', $html);
        $this->assertStringContainsString('Unsubscribe from plugin update emails:', $html);
        $this->assertStringContainsString('email-preferences/unsubscribe/'.$subscriber->getKey().'/plugins.release_updates', $html);

        $symfony = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($symfony);
        }
        $this->assertTrue($symfony->getHeaders()->has('List-Unsubscribe'));
    }

    public function test_release_update_events_send_the_sites_mail_not_the_packages(): void
    {
        Notification::fake();
        [$plugin, $release] = $this->pluginWithRelease();
        $subscriber = User::factory()->create();

        event(new SubscribersToNotify($plugin, $release, [$subscriber]));

        Notification::assertSentTo($subscriber, PluginMailNotification::class);
        Notification::assertNotSentTo($subscriber, PluginNotification::class);
    }

    public function test_a_report_is_mailed_to_support_and_the_outcome_to_the_reporter_in_the_old_words(): void
    {
        [$plugin, $release, $owner] = $this->pluginWithRelease();
        $reporter = User::factory()->create(['username' => 'the_reporter']);
        $report = PluginReport::query()->forceCreate([
            'plugin_id' => $plugin->id,
            'plugin_release_id' => $release->id,
            'reporter_id' => $reporter->id,
            'reason' => 'It phones home.',
            'status' => 'reviewed',
            'resolution_note' => 'Removed the call.',
        ]);

        $submitted = (new PluginReportMailNotification($report, PluginReportMailNotification::SUBMITTED))->toMail($owner);
        $reviewed = (new PluginReportMailNotification($report, PluginReportMailNotification::STATUS_UPDATED))->toMail($reporter);

        $this->assertSame('New plugin report submitted', $submitted->subject);
        $this->assertContains('Reporter: the_reporter', $submitted->introLines);
        $this->assertContains('Reason: It phones home.', $submitted->introLines);
        $this->assertSame('Your plugin report has been updated', $reviewed->subject);
        $this->assertSame(route('plugins.show', $plugin), $reviewed->actionUrl);
        $this->assertTrue(collect($reviewed->introLines)->contains(fn (string $line): bool => str_contains($line, 'Removed the call.')));
    }

    /**
     * @return array{0: Plugin, 1: PluginRelease, 2: User}
     */
    private function pluginWithRelease(): array
    {
        $owner = User::factory()->create();
        $type = PluginType::query()->create(['name' => 'Admin Tools', 'slug' => 'admin-tools', 'is_active' => true, 'position' => 30]);
        $plugin = Plugin::query()->create(['name' => 'Admin Audit Trail', 'slug' => 'admin-audit-trail', 'plugin_type_id' => $type->id, 'owner_id' => $owner->id, 'status' => PluginStatus::ACTIVE->value]);
        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id,
            'uploaded_by_id' => $owner->id,
            'version' => '1.0.0',
            'internal_version' => 1,
            'zip_path' => 'plugins/missing.zip',
            'zip_filename' => 'missing.zip',
            'status' => PluginStatus::ACTIVE->value,
            'encapsulated' => 'unknown',
        ]);

        return [$plugin, $release, $owner];
    }
}
