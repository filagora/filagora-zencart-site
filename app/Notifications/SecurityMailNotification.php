<?php

namespace App\Notifications;

use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Filament\Resources\PluginReleases\PluginReleaseResource;
use Filagora\PluginsSecurity\Enums\ScanSeverity;
use Filagora\PluginsSecurity\Enums\ScanStatus;
use Filagora\PluginsSecurity\Notifications\SecurityNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The plugin security mail with lara-forum's wording and layout, in place of the package's. The
 * package decides who is told and when (and uses this class for the dashboard's "email the result
 * again" too); this decides what they read. Named in config/filagora-plugins-security.php.
 */
class SecurityMailNotification extends SecurityNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->kind) {
            'scan_complete' => $this->scanComplete(),
            'quarantined' => $this->quarantined(),
            'quarantine_cleared' => $this->quarantineCleared(),
            'finding_flagged' => $this->findingFlagged(),
            default => $this->reviewFinished(),
        };
    }

    private function scanComplete(): MailMessage
    {
        $plugin = $this->release->plugin;
        $pluginName = (string) ($plugin->name ?? 'your plugin');
        $quarantined = $this->release->status === PluginStatus::QUARANTINED;
        $failed = $this->scan?->status === ScanStatus::FAILED;
        $severity = $this->scan?->severity() ?? ScanSeverity::NONE;
        $hasFindings = ! $failed && $severity !== ScanSeverity::NONE;

        $outcome = match (true) {
            $failed => __('notifications.plugin.email.scan_completed.outcome_failed'),
            $quarantined => __('notifications.plugin.email.scan_completed.outcome_quarantined', ['severity' => $severity->label()]),
            $hasFindings => __('notifications.plugin.email.scan_completed.outcome_flagged', [
                'count' => (int) collect($this->scan?->finding_counts ?? [])->sum(),
                'severity' => $severity->label(),
            ]),
            default => __('notifications.plugin.email.scan_completed.outcome_clear'),
        };

        $statusLine = match (true) {
            $quarantined => __('notifications.plugin.email.scan_completed.status_held'),
            $this->release->status === PluginStatus::ACTIVE => __('notifications.plugin.email.scan_completed.status_live'),
            default => __('notifications.plugin.email.scan_completed.status_pending'),
        };

        $replace = ['plugin' => $pluginName, 'version' => $this->release->displayVersion()];

        return $this->fromSupportAddress(new MailMessage)
            ->subject(__('notifications.plugin.email.scan_completed.subject', $replace))
            ->greeting(__('notifications.plugin.email.scan_completed.title'))
            ->line(__('notifications.plugin.email.scan_completed.intro', $replace))
            ->line($outcome)
            ->line($statusLine)
            ->action(__('notifications.plugin.email.scan_completed.action'), route('plugins.show', $plugin));
    }

    private function quarantined(): MailMessage
    {
        $pluginName = (string) ($this->release->plugin?->name ?? 'your plugin');
        $version = $this->release->version;
        $severity = $this->scan?->severity()->label() ?? 'a security';

        $message = $this->fromSupportAddress(new MailMessage)
            ->subject("Security hold on {$pluginName} {$version}")
            ->greeting('Security hold')
            ->line("Release {$version} of {$pluginName} has been temporarily removed from public view because an automated security scan reported {$severity} finding.")
            ->line('A moderator will review the scan. No action is needed from you yet — if the finding is a false positive it will be cleared, otherwise a moderator will contact you.')
            ->action('View the plugin', route('plugins.show', $this->release->plugin));

        if ($this->scan !== null) {
            $message->line("Scan reference: #{$this->scan->id} (scanner v{$this->scan->scanner_version}).");
        }

        return $message;
    }

    private function quarantineCleared(): MailMessage
    {
        $pluginName = (string) ($this->release->plugin?->name ?? 'your plugin');
        $version = $this->release->version;

        return $this->fromSupportAddress(new MailMessage)
            ->subject("Security hold lifted on {$pluginName} {$version}")
            ->greeting('Security hold lifted')
            ->line("Release {$version} of {$pluginName} has passed review and is public again.")
            ->action('View the plugin', route('plugins.show', $this->release->plugin));
    }

    private function findingFlagged(): MailMessage
    {
        $pluginName = (string) ($this->release->plugin?->name ?? 'Unknown plugin');
        $version = $this->release->version;
        $quarantined = $this->release->status === PluginStatus::QUARANTINED;
        $severity = $this->scan?->severity()->label() ?? ScanSeverity::NONE->label();

        return $this->fromSupportAddress(new MailMessage)
            ->subject(sprintf('[Security] %s %s — %s finding%s', $pluginName, $version, $severity, $quarantined ? ' (quarantined)' : ''))
            ->greeting('Security scan finding')
            ->line("Plugin: {$pluginName} (release {$version})")
            ->line("Highest severity: {$severity}")
            ->line('Findings: '.($this->counts() !== '' ? $this->counts() : 'none recorded'))
            ->line($quarantined
                ? 'The release has been auto-quarantined and is hidden from the public until cleared.'
                : 'The release remains public. Review and quarantine manually if needed.')
            ->action('Review the release', PluginReleaseResource::getUrl('edit', ['record' => $this->release]));
    }

    /**
     * The AI review's mail is not sent from the support address, as before.
     */
    private function reviewFinished(): MailMessage
    {
        $pluginName = (string) ($this->release->plugin?->name ?? 'Unknown plugin');
        $version = $this->release->displayVersion();
        $failed = $this->kind === 'review_failed';

        $message = (new MailMessage)
            ->subject(sprintf('[AI review] %s %s - %s', $pluginName, $version, $failed ? 'failed' : 'finished'))
            ->greeting('Your AI review has finished')
            ->line("Plugin: {$pluginName} (release {$version})");

        if ($failed) {
            $message->line('The review could not be completed. Reason: '.$this->plainError());
        } else {
            $message
                ->line('Highest severity: '.($this->scan?->severity()->label() ?? ScanSeverity::NONE->label()))
                ->line('Findings: '.($this->counts() !== '' ? $this->counts() : 'none'))
                ->line('This review is advisory and does not affect the standard scan or quarantine.');
        }

        return $message->action('Open the release', PluginReleaseResource::getUrl('edit', ['record' => $this->release]));
    }

    private function counts(): string
    {
        return collect($this->scan?->finding_counts ?? [])
            ->filter()
            ->map(fn ($n, $severity): string => "{$severity}: {$n}")
            ->implode(', ');
    }

    private function plainError(): string
    {
        $error = trim(strip_tags((string) $this->scan?->error));
        $error = preg_replace('/[^\x20-\x7E]/', ' ', $error) ?? '';
        $error = str_replace(['[', ']', '(', ')', '`', '*', '_', '<', '>'], ' ', $error);

        return mb_substr(trim($error) !== '' ? trim($error) : 'unknown error', 0, 200);
    }

    /**
     * Plugin mail comes from the plugin support address when one is set, as before.
     */
    private function fromSupportAddress(MailMessage $message): MailMessage
    {
        $configured = trim((string) config('filagora-plugins.mail.support_email'));
        $address = filter_var($configured, FILTER_VALIDATE_EMAIL) !== false ? $configured : trim((string) config('mail.from.address', ''));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return $message;
        }

        $name = config('mail.from.name');

        return $message->from($address, is_string($name) && $name !== '' ? $name : null);
    }
}
