<?php

namespace App\Services;

use App\Models\NewsletterEvent;
use App\Models\NewsletterLinkClick;
use App\Models\NewsletterSend;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\NewsletterSubscriberStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class NewsletterSubscriberService
{
    public const PROFILE_CONSENT_TEXT = 'I agree to receive the Zen Cart newsletter by email.';

    public const PUBLIC_CONSENT_TEXT = 'I agree to receive the Zen Cart newsletter by email.';

    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function suppressionHash(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }

    public function forUser(User $user): ?NewsletterSubscriber
    {
        $normalizedEmail = $this->normalizeEmail((string) $user->email);

        return NewsletterSubscriber::query()
            ->where(function ($query) use ($user, $normalizedEmail): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhere('normalized_email', $normalizedEmail);
            })
            ->orderByRaw('user_id = ? desc', [$user->id])
            ->latest('id')
            ->first();
    }

    public function unlinkUserForEmailChange(User $user, string $oldEmail): int
    {
        $normalizedOldEmail = $this->normalizeEmail($oldEmail);

        if ($normalizedOldEmail === '') {
            return 0;
        }

        return NewsletterSubscriber::query()
            ->where('user_id', $user->id)
            ->where('normalized_email', $normalizedOldEmail)
            ->update(['user_id' => null]);
    }

    public function subscribeFromProfile(User $user, ?string $ip = null, ?string $userAgent = null): NewsletterSubscriber
    {
        $now = now();
        $email = trim((string) $user->email);
        $normalizedEmail = $this->normalizeEmail($email);

        return DB::transaction(function () use ($user, $email, $normalizedEmail, $ip, $userAgent, $now): NewsletterSubscriber {
            $subscriber = $this->subscriberForEmail($normalizedEmail) ?? new NewsletterSubscriber;

            $subscriber->fill([
                'user_id' => $user->id,
                'email' => $email,
                'name' => $user->name,
                'normalized_email' => $normalizedEmail,
                'suppression_hash' => null,
                'status' => NewsletterSubscriberStatus::Subscribed,
                'source' => 'profile',
                'subscribed_at' => $subscriber->subscribed_at ?? $now,
                'confirmed_at' => $subscriber->confirmed_at ?? $now,
                'unsubscribed_at' => null,
                'unsubscribe_reason' => null,
                'consent_text' => self::PROFILE_CONSENT_TEXT,
                'consent_version' => 'profile-v1',
                'consent_ip' => $ip,
                'consent_user_agent' => $userAgent,
            ]);

            $subscriber->save();

            return $subscriber;
        });
    }

    public function requestPublicSubscription(string $email, ?string $name = null, ?string $ip = null, ?string $userAgent = null): NewsletterSubscriber
    {
        $email = trim($email);
        $normalizedEmail = $this->normalizeEmail($email);

        return DB::transaction(function () use ($email, $normalizedEmail, $name, $ip, $userAgent): NewsletterSubscriber {
            $subscriber = $this->subscriberForEmail($normalizedEmail) ?? new NewsletterSubscriber;

            if ($subscriber->status === NewsletterSubscriberStatus::Subscribed) {
                return $subscriber;
            }

            $subscriber->fill([
                'email' => $email,
                'normalized_email' => $normalizedEmail,
                'suppression_hash' => null,
                'name' => trim((string) $name) !== '' ? trim((string) $name) : $subscriber->name,
                'status' => NewsletterSubscriberStatus::Pending,
                'source' => 'public_signup',
                'consent_text' => self::PUBLIC_CONSENT_TEXT,
                'consent_version' => 'public-v1',
                'consent_ip' => $ip,
                'consent_user_agent' => $userAgent,
            ]);

            $subscriber->save();

            return $subscriber;
        });
    }

    public function confirm(NewsletterSubscriber $subscriber): NewsletterSubscriber
    {
        $subscriber->fill([
            'status' => NewsletterSubscriberStatus::Subscribed,
            'subscribed_at' => $subscriber->subscribed_at ?? now(),
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ]);
        $subscriber->save();

        return $subscriber;
    }

    public function anonymizeForErasure(User $user): int
    {
        $normalizedUserEmail = $this->normalizeEmail((string) $user->email);
        $subscribers = NewsletterSubscriber::query()
            ->where(function ($query) use ($user, $normalizedUserEmail): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhere('normalized_email', $normalizedUserEmail);
            })
            ->get();

        foreach ($subscribers as $subscriber) {
            $normalizedEmail = $this->normalizeEmail((string) ($subscriber->normalized_email ?: $subscriber->email));
            $subscriber->fill([
                'user_id' => null,
                'email' => sprintf('erased-subscriber-%d@anonymized.invalid', $subscriber->id),
                'normalized_email' => sprintf('erased-subscriber-%d@anonymized.invalid', $subscriber->id),
                'suppression_hash' => $this->suppressionHash($normalizedEmail),
                'name' => null,
                'status' => NewsletterSubscriberStatus::Unsubscribed,
                'source' => 'dsar_erasure',
                'mailchimp_id' => null,
                'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
                'unsubscribe_reason' => 'dsar_erasure',
                'consent_text' => 'Suppression retained after DSAR erasure.',
                'consent_version' => 'dsar-erasure-v1',
                'consent_ip' => null,
                'consent_user_agent' => null,
                'metadata' => null,
            ]);
            $subscriber->save();
        }

        $this->anonymizeSendsForErasure($normalizedUserEmail);

        return $subscribers->count();
    }

    /**
     * Anonymizes delivery-history rows (newsletter_sends) matching the erased email, redacts
     * raw webhook payloads on their events (may contain the recipient email in provider-specific
     * shapes not covered by the indexed columns), and severs newsletter_link_clicks from the
     * now-anonymized sends. Click rows themselves are not personally identifying (no email/name;
     * ip_hash is a one-way hash) so they're kept, just unlinked, as anonymous engagement analytics.
     */
    private function anonymizeSendsForErasure(string $normalizedUserEmail): void
    {
        $sends = NewsletterSend::query()
            ->where('normalized_email', $normalizedUserEmail)
            ->get();

        if ($sends->isEmpty()) {
            return;
        }

        $sendIds = $sends->pluck('id');

        NewsletterEvent::query()
            ->whereIn('newsletter_send_id', $sendIds)
            ->update(['payload' => null]);

        NewsletterLinkClick::query()
            ->whereIn('newsletter_send_id', $sendIds)
            ->update(['newsletter_send_id' => null]);

        foreach ($sends as $send) {
            $send->fill([
                'email' => sprintf('erased-send-%d@anonymized.invalid', $send->id),
                'normalized_email' => sprintf('erased-send-%d@anonymized.invalid', $send->id),
                'name' => null,
            ]);
            $send->save();
        }
    }

    public function unsubscribe(NewsletterSubscriber $subscriber, string $reason = 'signed_link'): NewsletterSubscriber
    {
        $subscriber->fill([
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
            'unsubscribe_reason' => $reason,
        ]);
        $subscriber->save();

        return $subscriber;
    }

    public function signedConfirmationUrl(NewsletterSubscriber $subscriber): string
    {
        return URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), [
            'subscriber' => $subscriber,
        ]);
    }

    public function signedUnsubscribeUrl(NewsletterSubscriber $subscriber): string
    {
        return URL::signedRoute('newsletter.unsubscribe', [
            'subscriber' => $subscriber,
        ]);
    }

    public function unsubscribeFromProfile(User $user): ?NewsletterSubscriber
    {
        $subscriber = $this->forUser($user);

        if (! $subscriber instanceof NewsletterSubscriber) {
            return null;
        }

        $subscriber->user_id = $subscriber->user_id ?? $user->id;

        return $this->unsubscribe($subscriber, 'profile');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function importMailchimpRow(array $row, bool $dryRun = false): string
    {
        $email = $this->stringValue($row, ['email address', 'email', 'Email Address', 'EMAIL']);
        $normalizedEmail = $this->normalizeEmail($email);

        if (! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return 'invalid';
        }

        $status = $this->statusFromMailchimp($this->stringValue($row, ['email marketing status', 'status', 'Email Marketing Status']));
        $now = now();

        $attributes = [
            'email' => $email,
            'normalized_email' => $normalizedEmail,
            'suppression_hash' => null,
            'name' => $this->nameFromRow($row),
            'status' => $status,
            'source' => 'mailchimp_import',
            'mailchimp_id' => $this->nullableStringValue($row, ['contact id', 'mailchimp id', 'member id', 'id']),
            'subscribed_at' => $this->dateValue($row, ['opt-in time', 'timestamp signup', 'member since']),
            'confirmed_at' => $status === NewsletterSubscriberStatus::Subscribed
                ? ($this->dateValue($row, ['opt-in time', 'timestamp opt']) ?? $now)
                : null,
            'unsubscribed_at' => $status === NewsletterSubscriberStatus::Unsubscribed
                ? ($this->dateValue($row, ['timestamp unsubscribe', 'last changed']) ?? $now)
                : null,
            'bounced_at' => $status === NewsletterSubscriberStatus::Bounced ? $now : null,
            'complained_at' => $status === NewsletterSubscriberStatus::Complained ? $now : null,
            'unsubscribe_reason' => $status === NewsletterSubscriberStatus::Unsubscribed ? 'mailchimp_import' : null,
            'metadata' => $row,
        ];

        $existingSubscriber = $this->subscriberForEmail($normalizedEmail);

        if ($existingSubscriber?->suppression_hash !== null && $existingSubscriber->status?->isSuppressed()) {
            return 'suppressed';
        }

        if ($dryRun) {
            return $existingSubscriber instanceof NewsletterSubscriber ? 'updated' : 'imported';
        }

        $subscriber = $existingSubscriber ?? new NewsletterSubscriber;

        $subscriber->fill($attributes);
        $subscriber->save();

        return $subscriber->wasRecentlyCreated ? 'imported' : 'updated';
    }

    private function subscriberForEmail(string $normalizedEmail): ?NewsletterSubscriber
    {
        $suppressionHash = $this->suppressionHash($normalizedEmail);

        return NewsletterSubscriber::query()
            ->where('normalized_email', $normalizedEmail)
            ->orWhere('suppression_hash', $suppressionHash)
            ->latest('id')
            ->first();
    }

    private function statusFromMailchimp(string $status): NewsletterSubscriberStatus
    {
        return match (Str::lower(trim($status))) {
            'subscribed', 'transactional' => NewsletterSubscriberStatus::Subscribed,
            'unsubscribed' => NewsletterSubscriberStatus::Unsubscribed,
            'cleaned', 'bounced' => NewsletterSubscriberStatus::Bounced,
            'complained', 'abuse' => NewsletterSubscriberStatus::Complained,
            default => NewsletterSubscriberStatus::Pending,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nameFromRow(array $row): ?string
    {
        $fullName = $this->nullableStringValue($row, ['name', 'full name']);
        if ($fullName !== null) {
            return $fullName;
        }

        $first = $this->nullableStringValue($row, ['first name', 'fname', 'FNAME']);
        $last = $this->nullableStringValue($row, ['last name', 'lname', 'LNAME']);
        $name = trim(implode(' ', array_filter([$first, $last])));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function stringValue(array $row, array $keys): string
    {
        return (string) ($this->nullableStringValue($row, $keys) ?? '');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function nullableStringValue(array $row, array $keys): ?string
    {
        $normalized = collect($row)->mapWithKeys(
            fn (mixed $value, string|int $key): array => [Str::lower(trim((string) $key)) => $value]
        );

        foreach ($keys as $key) {
            $value = $normalized->get(Str::lower($key));
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function dateValue(array $row, array $keys): ?Carbon
    {
        $value = $this->nullableStringValue($row, $keys);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
