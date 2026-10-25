<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Durable per-day tally of verification emails sent, one row per calendar day.
 *
 * The daily ceiling this backs is a cost control on the mail provider's send
 * quota, so it must not be resettable by an unrelated operation. Held in the
 * cache store it was: every deploy runs `php artisan optimize:clear`, which
 * returned the day's count to zero and let the cap be exceeded arbitrarily on a
 * day with several deploys.
 */
class RegistrationEmailSendCounter extends Model
{
    protected $fillable = [
        'send_date',
        'sent_count',
    ];

    /**
     * `send_date` is intentionally left uncast. A date cast would write
     * `Y-m-d H:i:s` into the DATE column, which MySQL rejects under strict mode
     * and SQLite stores verbatim, breaking equality against the plain `Y-m-d`
     * keys this table is looked up by.
     */
    protected function casts(): array
    {
        return [
            'sent_count' => 'integer',
        ];
    }

    public static function sentCountFor(string $date): int
    {
        return (int) static::query()
            ->where('send_date', $date)
            ->value('sent_count');
    }

    /**
     * Record one sent verification email against the given day.
     *
     * The increment is done in SQL rather than read-modify-write so concurrent
     * registrations cannot lose counts, and the unique index on `send_date`
     * settles the race to create the day's first row.
     */
    public static function recordSendOn(string $date): void
    {
        if (static::incrementSentCount($date) > 0) {
            return;
        }

        try {
            static::query()->create([
                'send_date' => $date,
                'sent_count' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            static::incrementSentCount($date);
        }
    }

    private static function incrementSentCount(string $date): int
    {
        return static::query()
            ->where('send_date', $date)
            ->increment('sent_count');
    }
}
