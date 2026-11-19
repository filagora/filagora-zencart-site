<?php

namespace App\Services\Faivelo;

use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Applies Faivelo's delivery events to the member's email status. Transactional mail only: bounces and
 * complaints stop optional mail to the address, via NotificationPreferenceService::isSuppressed().
 */
class FaiveloWebhookHandler
{
    /** Bounce types that mean "try again later"; anything else is treated as permanent. */
    private const SOFT_BOUNCE_TYPES = ['soft', 'temporary', 'transient', 'undetermined'];

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function handle(array $eventData): string
    {
        return match ($eventData['type'] ?? null) {
            'email.bounced' => $this->handleBounced($eventData),
            'email.complained' => $this->handleComplained($eventData),
            'email.delivered' => $this->handleDelivered($eventData),
            default => $this->handleOther($eventData),
        };
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function handleBounced(array $eventData): string
    {
        Log::channel('faivelo')->info('Faivelo bounce event.', ['event_data' => $eventData]);

        $bounceType = strtolower((string) ($eventData['data']['bounce']['type'] ?? ''));

        if (in_array($bounceType, self::SOFT_BOUNCE_TYPES, true)) {
            return 'Soft bounce logged.';
        }

        return $this->updateRecipients($eventData, [
            'email_bounced_at' => now(),
            'email_status' => 'bounced',
            'receives_email' => false,
        ], 'marked as bounced');
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function handleComplained(array $eventData): string
    {
        Log::channel('faivelo')->warning('Faivelo spam complaint event.', ['event_data' => $eventData]);

        return $this->updateRecipients($eventData, [
            'email_complained_at' => now(),
            'email_status' => 'complained',
            'receives_email' => false,
        ], 'marked as complained');
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function handleDelivered(array $eventData): string
    {
        $recipients = $this->recipients($eventData);

        if ($recipients === []) {
            return 'Missing recipient.';
        }

        $deliveredAt = $this->createdAt($eventData);
        $updated = 0;

        foreach (User::query()->whereIn('email', $recipients)->get() as $user) {
            // Events arrive out of order: a late delivery must not clear a bounce or complaint.
            $suppressed = in_array($user->email_status, ['bounced', 'complained'], true);

            $user->forceFill(array_filter([
                'email_last_delivered_at' => $deliveredAt,
                'email_status' => $suppressed ? null : 'deliverable',
            ], static fn (mixed $value): bool => $value !== null))->save();
            $updated++;
        }

        return $updated > 0 ? "{$updated} user(s) delivery recorded." : 'User not found.';
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function handleOther(array $eventData): string
    {
        Log::channel('faivelo')->notice('Unhandled Faivelo event.', ['type' => $eventData['type'] ?? null]);

        return 'Event ignored.';
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @param  array<string, mixed>  $attributes
     */
    private function updateRecipients(array $eventData, array $attributes, string $outcome): string
    {
        $recipients = $this->recipients($eventData);

        if ($recipients === []) {
            return 'Missing recipient.';
        }

        $updated = 0;

        foreach (User::query()->whereIn('email', $recipients)->get() as $user) {
            $user->forceFill($attributes)->save();
            $updated++;
        }

        return $updated > 0 ? "{$updated} user(s) {$outcome}." : 'User not found.';
    }

    /**
     * `data.to` is a single address or a list of addresses.
     *
     * @param  array<string, mixed>  $eventData
     * @return array<int, string>
     */
    private function recipients(array $eventData): array
    {
        $to = $eventData['data']['to'] ?? [];

        return array_values(array_filter(
            is_array($to) ? $to : [$to],
            static fn (mixed $address): bool => is_string($address) && $address !== '',
        ));
    }

    /**
     * Events can arrive out of order, so the event's own time is used rather than now().
     *
     * @param  array<string, mixed>  $eventData
     */
    private function createdAt(array $eventData): Carbon
    {
        $createdAt = $eventData['created_at'] ?? null;

        try {
            return is_string($createdAt) ? Carbon::parse($createdAt) : now();
        } catch (InvalidFormatException) {
            return now();
        }
    }
}
