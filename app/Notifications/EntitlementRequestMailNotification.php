<?php

namespace App\Notifications;

use Filagora\Entitlements\Models\EntitlementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The entitlement request mail, with lara-forum's wording: the receipt to the member, the notice to
 * staff and the member's notice when the status changes (with the staff note and the grant's expiry).
 */
class EntitlementRequestMailNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const SUBMITTED_USER = 'submitted_user';

    public const SUBMITTED_STAFF = 'submitted_staff';

    public const STATUS_UPDATED = 'status_updated';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly string $kind,
        private readonly EntitlementRequest $request,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
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
        $key = "notifications.entitlement.email.{$this->kind}";

        $mail = (new MailMessage)
            ->subject(__("notifications.entitlement.subjects.{$this->kind}"))
            ->line(__("{$key}.intro"))
            ->line(__("{$key}.request_id", ['id' => $this->request->getKey()]))
            ->line(__("{$key}.entitlement", ['entitlement' => $this->request->entitlement_key]));

        return match ($this->kind) {
            self::SUBMITTED_USER => $mail->action(__("{$key}.action"), route('profile.show', ['tab' => 'entitlements'])),
            self::SUBMITTED_STAFF => $mail
                ->line(__("{$key}.user", ['user' => $this->request->user?->email ?? 'unknown']))
                ->action(__("{$key}.action"), route('filament.dashboard.resources.entitlement-requests.view', ['record' => $this->request->getKey()])),
            default => $this->statusUpdated($mail, $key),
        };
    }

    private function statusUpdated(MailMessage $mail, string $key): MailMessage
    {
        $mail
            ->line(__("{$key}.from", ['status' => $this->label($this->from)]))
            ->line(__("{$key}.to", ['status' => $this->label($this->to)]));

        $notes = trim((string) ($this->request->review_notes ?? ''));

        if ($notes !== '') {
            $mail->line(__("{$key}.staff_note", ['note' => $notes]));
        }

        if ($this->request->expires_at !== null) {
            $mail->line(__("{$key}.grant_expires", ['date' => $this->request->expires_at->format('Y-m-d H:i')]));
        }

        return $mail->action(__("{$key}.action"), route('profile.show', ['tab' => 'entitlements']));
    }

    private function label(?string $status): string
    {
        return strtoupper(str_replace('_', ' ', (string) $status));
    }
}
