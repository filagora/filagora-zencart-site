<?php

namespace App\Services;

use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Support\Models;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Brings the old Mailchimp list over: one row of a Mailchimp CSV export at a time. Specific to
 * this site's history, so it lives here rather than in the newsletters package, which it uses for
 * the subscriber rows and their rules.
 */
class NewsletterMailchimpImporter
{
    public function __construct(private readonly Subscribers $subscribers) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function importMailchimpRow(array $row, bool $dryRun = false): string
    {
        $email = $this->stringValue($row, ['email address', 'email', 'Email Address', 'EMAIL']);
        $normalizedEmail = $this->subscribers->normalizeEmail($email);

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

        $existingSubscriber = $this->subscribers->forEmail($normalizedEmail);

        if ($existingSubscriber?->suppression_hash !== null && $existingSubscriber->status?->isSuppressed()) {
            return 'suppressed';
        }

        if ($dryRun) {
            return $existingSubscriber instanceof NewsletterSubscriber ? 'updated' : 'imported';
        }

        $subscriber = $existingSubscriber ?? new (Models::class('subscriber'));

        $subscriber->fill($attributes);
        $subscriber->save();

        return $subscriber->wasRecentlyCreated ? 'imported' : 'updated';
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
