<?php

namespace App\Notifications;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The privacy request mail, with lara-forum's wording and links: the receipt to the requester, the
 * notice to the DSAR address, a status change (with a signed download link for a finished export)
 * and a staff reply. The compliance package's own mail is switched off
 * (`filagora-compliance.mail.enabled`) and `SendPrivacyMail` sends these from the same events.
 */
class PrivacyMailNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const SUBMITTED_USER = 'submitted_user';

    public const SUBMITTED_STAFF = 'submitted_staff';

    public const STATUS_UPDATED = 'status_updated';

    public const STAFF_REPLY = 'staff_reply';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly string $kind,
        private readonly PrivacyRequest $request,
        private readonly ?PrivacyRequestStatus $from = null,
        private readonly ?PrivacyRequestStatus $to = null,
        private readonly ?string $message = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->request->type?->label() ?? strtoupper((string) $this->request->type);
        $key = "notifications.privacy.email.{$this->kind}";

        $mail = (new MailMessage)
            ->subject(__("notifications.privacy.subjects.{$this->kind}"))
            ->line(__("{$key}.intro"))
            ->line(__("{$key}.request_id", ['id' => $this->request->getKey()]))
            ->line(__("{$key}.type", ['type' => $type]));

        return match ($this->kind) {
            self::SUBMITTED_USER => $mail->action(__("{$key}.action"), route('profile.show', ['tab' => 'privacy'])),
            self::SUBMITTED_STAFF => $mail
                ->line(__("{$key}.user", ['user' => $this->request->user?->email ?? 'unknown']))
                ->action(__("{$key}.action"), route('filament.dashboard.resources.privacy-requests.view', ['record' => $this->request->getKey()])),
            self::STAFF_REPLY => $mail
                ->line(__("{$key}.message", ['message' => $this->message]))
                ->action(__("{$key}.action"), route('profile.show', ['tab' => 'privacy'])),
            default => $this->statusUpdated($mail, $key),
        };
    }

    private function statusUpdated(MailMessage $mail, string $key): MailMessage
    {
        $mail
            ->line(__("{$key}.from", ['status' => $this->statusLabel($this->from)]))
            ->line(__("{$key}.to", ['status' => $this->statusLabel($this->to)]));

        if ($this->isFinishedExport()) {
            $minutes = max(1, (int) config('filagora-compliance.exports.download_link_minutes', 10080));
            $url = URL::temporarySignedRoute('profile.privacy-requests.export.download', now()->addMinutes($minutes), ['privacyRequest' => $this->request->getKey()]);

            return $mail
                ->action(__("{$key}.download_action"), $url)
                ->line(__("{$key}.download_expires", ['minutes' => $minutes]))
                ->line(__("{$key}.history_hint"));
        }

        return $mail->action(__("{$key}.action"), route('profile.show', ['tab' => 'privacy']));
    }

    private function statusLabel(?PrivacyRequestStatus $status): string
    {
        return strtoupper(str_replace('_', ' ', (string) $status?->value));
    }

    private function isFinishedExport(): bool
    {
        return $this->request->type === PrivacyRequestType::EXPORT
            && $this->to === PrivacyRequestStatus::FULFILLED
            && is_string($this->request->export_path)
            && trim($this->request->export_path) !== '';
    }
}
