<?php

namespace App\Listeners;

use App\Notifications\PluginMailNotification;
use App\Notifications\PluginReportMailNotification;
use Filagora\Plugins\Enums\ModerationAction;
use Filagora\Plugins\Events\PluginModerated;
use Filagora\Plugins\Events\PluginReported;
use Filagora\Plugins\Events\PluginReportReviewed;
use Filagora\Plugins\Events\PluginSubmitted;
use Filagora\Plugins\Events\ReleasePublished;
use Filagora\Plugins\Events\ReleaseRejected;
use Filagora\Plugins\Events\ReleaseSubmitted;
use Filagora\Plugins\Events\SubscribersToNotify;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Models\PluginRelease;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;

/**
 * The site's plugin mail, from the plugins package's events, in place of the package's own (switched
 * off in config/filagora-plugins.php). Who is told is the package's rule; what they are told is
 * lara-forum's.
 */
class SendPluginMail
{
    /** @var array<int|string, true> Releases already mailed as a new plugin's first. */
    private static array $mailedAsNewPlugin = [];

    public function pluginSubmitted(PluginSubmitted $event): void
    {
        self::$mailedAsNewPlugin[$event->release->getKey()] = true;

        $this->submission($event->plugin, $event->release, $event->submittedBy, 'submitted', 'submitted', 'plugin-submitted', 'submitted_support', 'plugin-submitted-support', route('plugins.index'));
    }

    public function releaseSubmitted(ReleaseSubmitted $event): void
    {
        // The package announces a new plugin's first release twice (as the plugin, then as a release);
        // it is mailed once, as the new plugin.
        if (isset(self::$mailedAsNewPlugin[$event->release->getKey()])) {
            return;
        }

        $this->submission($event->plugin, $event->release, $event->submittedBy, 'submitted', 'release_submitted', 'plugin-release-submitted', 'release_submitted_support', 'plugin-release-submitted-support', route('plugins.show', $event->plugin));
    }

    public function published(ReleasePublished $event): void
    {
        if ($event->moderator === null || $event->author === null) {
            return;
        }

        Notification::send($event->author, new PluginMailNotification(
            'release_approved',
            'release_approved',
            $this->replace($event->plugin, $event->release),
            'plugin-release-approved',
            [
                'pluginName' => $event->plugin->name,
                'pluginId' => $this->canonicalId($event->plugin),
                'releaseVersion' => $event->release->displayVersion(),
                'releaseId' => $event->release->getKey(),
                'actionUrl' => route('plugins.show', $event->plugin),
            ],
        ));
    }

    public function rejected(ReleaseRejected $event): void
    {
        $author = $event->release->uploadedBy ?? $event->plugin->owner;

        if ($author === null || ($event->except !== null && $author->getKey() === $event->except->getAuthIdentifier())) {
            return;
        }

        Notification::send($author, new PluginMailNotification(
            'release_rejected',
            'release_rejected',
            $this->replace($event->plugin, $event->release),
            'plugin-release-rejected',
            [
                'pluginName' => $event->plugin->name,
                'releaseVersion' => $event->release->displayVersion(),
                'moderatorNote' => $event->note,
                'actionUrl' => route('plugins.show', $event->plugin),
            ],
        ));
    }

    public function moderated(PluginModerated $event): void
    {
        $owner = $event->plugin->owner;

        if ($owner === null) {
            return;
        }

        $approved = $event->action === ModerationAction::APPROVE;

        Notification::send($owner, new PluginMailNotification(
            $approved ? 'plugin_approved' : 'plugin_rejected',
            $approved ? 'approved' : 'rejected',
            ['plugin' => $event->plugin->name],
            $approved ? 'plugin-approved' : 'plugin-rejected',
            $approved
                ? ['pluginName' => $event->plugin->name, 'pluginId' => $this->canonicalId($event->plugin), 'actionUrl' => route('plugins.show', $event->plugin)]
                : ['pluginName' => $event->plugin->name, 'moderatorNote' => $event->note, 'actionUrl' => route('plugins.index')],
        ));
    }

    public function subscribers(SubscribersToNotify $event): void
    {
        if ($event->recipients === []) {
            return;
        }

        Notification::send($event->recipients, new PluginMailNotification(
            'release_updated',
            'updated',
            $this->replace($event->plugin, $event->release),
            'plugin-updated',
            [
                'pluginName' => $event->plugin->name,
                'releaseVersion' => $event->release->displayVersion(),
                'actionUrl' => route('plugins.show', $event->plugin),
            ],
            withUnsubscribe: true,
        ));
    }

    public function reported(PluginReported $event): void
    {
        $support = $this->supportEmail();

        if ($support !== null) {
            Notification::route('mail', $support)->notify(new PluginReportMailNotification($event->report, PluginReportMailNotification::SUBMITTED));
        }
    }

    public function reviewed(PluginReportReviewed $event): void
    {
        if ($event->report->reporter !== null) {
            Notification::send($event->report->reporter, new PluginReportMailNotification($event->report, PluginReportMailNotification::STATUS_UPDATED));
        }
    }

    /**
     * The author's receipt and the support copy of a submission.
     */
    private function submission(Plugin $plugin, PluginRelease $release, Authenticatable $by, string $kind, string $subjectKey, string $view, string $supportSubjectKey, string $supportView, string $actionUrl): void
    {
        $data = [
            'pluginName' => $plugin->name,
            'releaseVersion' => $release->displayVersion(),
            'actionUrl' => $actionUrl,
            'autoPublished' => $release->status->value === 'active',
        ];

        if (config('filagora-plugins.mail.acknowledge_submissions', true)) {
            Notification::send($by, new PluginMailNotification($data['autoPublished'] ? $kind.'_live' : $kind, $subjectKey, $this->replace($plugin, $release), $view, $data));
        }

        $support = $this->supportEmail();

        if ($support !== null) {
            $submitter = data_get($by, 'username') ?: (data_get($by, 'name') ?: data_get($by, 'email'));

            Notification::route('mail', $support)->notify(new PluginMailNotification(
                'submitted_support',
                $supportSubjectKey,
                $this->replace($plugin, $release),
                $supportView,
                $data + ['submittedBy' => $submitter, 'actionUrl' => $actionUrl],
            ));
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function replace(Plugin $plugin, PluginRelease $release): array
    {
        return ['plugin' => $plugin->name, 'version' => $release->displayVersion()];
    }

    private function canonicalId(Plugin $plugin): int
    {
        return (int) ($plugin->legacy_vbulletin_file_id ?? $plugin->getKey());
    }

    private function supportEmail(): ?string
    {
        $email = trim((string) config('filagora-plugins.mail.support_email'));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
