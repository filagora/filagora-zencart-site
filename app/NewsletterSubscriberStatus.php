<?php

namespace App;

enum NewsletterSubscriberStatus: string
{
    case Pending = 'pending';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';

    public function isSendable(): bool
    {
        return $this === self::Subscribed;
    }

    public function isSuppressed(): bool
    {
        return in_array($this, [self::Unsubscribed, self::Bounced, self::Complained], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Subscribed => 'Subscribed',
            self::Unsubscribed => 'Unsubscribed',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Subscribed => 'success',
            self::Pending => 'warning',
            self::Unsubscribed => 'gray',
            self::Bounced, self::Complained => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
