<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SecurityMailNotification;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginType;
use Filagora\PluginsSecurity\Enums\ScanSeverity;
use Filagora\PluginsSecurity\Enums\ScanStatus;
use Filagora\PluginsSecurity\Enums\ScanTrigger;
use Filagora\PluginsSecurity\Events\QuarantineCleared;
use Filagora\PluginsSecurity\Events\ReleaseQuarantined;
use Filagora\PluginsSecurity\Events\ScanFinished;
use Filagora\PluginsSecurity\Events\SeriousFindingFlagged;
use Filagora\PluginsSecurity\Models\SecurityScan;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Models\PluginRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The plugin security mail keeps lara-forum's subjects, greetings and lines; the package only decides
 * who is told. The expected strings are lara-forum's.
 */
class SecurityMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filagora-plugins.mail.support_email' => 'support@example.test', 'mail.from.name' => 'Zen Cart']);
    }

    public function test_the_site_names_its_class_in_the_package_config(): void
    {
        $this->assertSame(SecurityMailNotification::class, config('filagora-plugins-security.mail.notification'));
        $this->assertTrue(config('filagora-plugins-security.mail.enabled'));
    }

    public function test_scan_complete_for_a_clean_scan(): void
    {
        [$release, $author] = $this->release(PluginStatus::ACTIVE);
        $mail = $this->render('scan_complete', $release, $this->scan($release));

        $this->assertSame('Scan complete: Mail Plugin v1.2.0', $mail->subject);
        $this->assertSame('Submission processed', $mail->greeting);
        $this->assertSame([
            'Your submission of Mail Plugin v1.2.0 has been received and its automated security scan has finished.',
            'Scan result: no issues found.',
            'Status: live in the plugin library.',
        ], $mail->introLines);
        $this->assertSame('View Plugin', $mail->actionText);
        $this->assertSame(route('plugins.show', $release->plugin), $mail->actionUrl);
        $this->assertSame(['support@example.test', 'Zen Cart'], $mail->from);
    }

    public function test_scan_complete_for_a_quarantined_release_names_the_severity(): void
    {
        [$release] = $this->release(PluginStatus::QUARANTINED);
        $mail = $this->render('scan_complete', $release, $this->scan($release, ScanSeverity::HIGH, 2));

        $this->assertStringStartsWith('Scan result: a High finding means the release has been temporarily withheld', $mail->introLines[1]);
        $this->assertSame('Status: withheld from public view.', $mail->introLines[2]);
    }

    public function test_quarantine_hold_mail(): void
    {
        [$release] = $this->release(PluginStatus::QUARANTINED);
        $scan = $this->scan($release, ScanSeverity::HIGH, 1);
        $mail = $this->render('quarantined', $release, $scan);

        $this->assertSame('Security hold on Mail Plugin v1.2.0', $mail->subject);
        $this->assertSame('Security hold', $mail->greeting);
        $this->assertSame('Release v1.2.0 of Mail Plugin has been temporarily removed from public view because an automated security scan reported High finding.', $mail->introLines[0]);
        $this->assertSame('A moderator will review the scan. No action is needed from you yet — if the finding is a false positive it will be cleared, otherwise a moderator will contact you.', $mail->introLines[1]);
        $this->assertSame("Scan reference: #{$scan->id} (scanner v{$scan->scanner_version}).", $mail->outroLines[0] ?? $mail->introLines[2]);
        $this->assertSame('View the plugin', $mail->actionText);
    }

    public function test_quarantine_lifted_mail(): void
    {
        [$release] = $this->release(PluginStatus::ACTIVE);
        $mail = $this->render('quarantine_cleared', $release, null);

        $this->assertSame('Security hold lifted on Mail Plugin v1.2.0', $mail->subject);
        $this->assertSame('Security hold lifted', $mail->greeting);
        $this->assertSame(['Release v1.2.0 of Mail Plugin has passed review and is public again.'], $mail->introLines);
    }

    public function test_staff_finding_mail(): void
    {
        [$release] = $this->release(PluginStatus::QUARANTINED);
        $mail = $this->render('finding_flagged', $release, $this->scan($release, ScanSeverity::CRITICAL, 3), forStaff: true);

        $this->assertSame('[Security] Mail Plugin v1.2.0 — Critical finding (quarantined)', $mail->subject);
        $this->assertSame('Security scan finding', $mail->greeting);
        $this->assertSame([
            'Plugin: Mail Plugin (release v1.2.0)',
            'Highest severity: Critical',
            'Findings: critical: 3',
            'The release has been auto-quarantined and is hidden from the public until cleared.',
        ], $mail->introLines);
        $this->assertSame('Review the release', $mail->actionText);
    }

    public function test_ai_review_mail_is_not_from_the_support_address(): void
    {
        [$release] = $this->release(PluginStatus::ACTIVE);
        $done = $this->render('review_finished', $release, $this->scan($release, ScanSeverity::LOW, 1), forStaff: true);
        $failed = $this->render('review_failed', $release, $this->scan($release, ScanSeverity::NONE, 0, ScanStatus::FAILED, "Bad <b>gateway</b> (502) \u{2014} x"), forStaff: true);

        $this->assertSame('[AI review] Mail Plugin 1.2.0 - finished', $done->subject);
        $this->assertSame('Your AI review has finished', $done->greeting);
        $this->assertSame(['Plugin: Mail Plugin (release 1.2.0)', 'Highest severity: Low', 'Findings: low: 1', 'This review is advisory and does not affect the standard scan or quarantine.'], $done->introLines);
        $this->assertSame([], $done->from);
        $this->assertSame('[AI review] Mail Plugin 1.2.0 - failed', $failed->subject);
        $this->assertSame('The review could not be completed. Reason: Bad gateway  502      x', $failed->introLines[1]);
    }

    public function test_the_package_sends_the_site_class_to_the_right_people(): void
    {
        Notification::fake();
        [$release, $author, $owner] = $this->release(PluginStatus::ACTIVE);
        $scan = $this->scan($release, ScanSeverity::NONE, 0);

        ScanFinished::dispatch($release, $scan);
        ReleaseQuarantined::dispatch($release, $scan, null, true);
        QuarantineCleared::dispatch($release, null);
        SeriousFindingFlagged::dispatch($release, $scan);

        Notification::assertSentTo([$author, $owner], SecurityMailNotification::class, fn (SecurityMailNotification $n) => $n->kind === 'scan_complete');
        Notification::assertSentTo($author, SecurityMailNotification::class, fn (SecurityMailNotification $n) => $n->kind === 'quarantine_cleared');
        Notification::assertSentOnDemand(SecurityMailNotification::class, fn (SecurityMailNotification $n, array $channels, $notifiable) => $n->kind === 'finding_flagged' && $notifiable->routes['mail'] === 'support@example.test');
    }

    private function render(string $kind, PluginRelease $release, ?SecurityScan $scan, bool $forStaff = false): MailMessage
    {
        $release->unsetRelation('plugin');

        return (new SecurityMailNotification($kind, $release, [], [], $forStaff, $scan))->toMail(new \stdClass);
    }

    /**
     * @return array{0: PluginRelease, 1: User, 2: User}
     */
    private function release(PluginStatus $status): array
    {
        $uploader = User::factory()->create(['email_verified_at' => now()]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $type = PluginType::query()->create(['name' => 'Mail Type', 'slug' => 'mail-type', 'is_active' => true, 'position' => 10]);
        $plugin = Plugin::query()->create([
            'name' => 'Mail Plugin', 'slug' => 'mail-plugin', 'plugin_type_id' => $type->id,
            'owner_id' => $owner->id, 'status' => PluginStatus::ACTIVE->value,
        ]);
        $release = PluginRelease::query()->create([
            'plugin_id' => $plugin->id, 'uploaded_by_id' => $uploader->id, 'version' => 'v1.2.0',
            'zip_path' => 'plugins/mail.zip', 'zip_filename' => 'mail.zip', 'status' => $status->value, 'encapsulated' => 'unknown',
        ]);

        return [$release->refresh(), $uploader, $owner];
    }

    private function scan(PluginRelease $release, ScanSeverity $severity = ScanSeverity::NONE, int $findings = 0, ScanStatus $status = ScanStatus::COMPLETED, ?string $error = null): SecurityScan
    {
        return SecurityScan::create([
            'plugin_release_id' => $release->id,
            'scanner_version' => 1,
            'status' => $status,
            'highest_severity' => $severity,
            'finding_counts' => $findings === 0 ? [] : [$severity->value => $findings],
            'findings' => [],
            'triggered_by' => ScanTrigger::UPLOAD,
            'error' => $error,
            'finished_at' => now(),
        ]);
    }
}
