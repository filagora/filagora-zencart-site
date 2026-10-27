<?php

namespace App\Models;

use App\WebhookProvider;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Durable record of a webhook delivery that has already been accepted.
 *
 * This deliberately does not live in the cache store: `php artisan optimize:clear`
 * runs on every deploy and, with `CACHE_STORE=redis`, issues a FLUSHDB. A webhook
 * redelivered around a deploy would then no longer be recognised as a duplicate
 * and would be processed twice. Both Mailgun and Mailtrap retry on non-2xx
 * responses and on timeouts, so that window is realistic rather than theoretical.
 *
 * A row blocks reprocessing for as long as it exists. `expires_at` marks when the
 * row becomes eligible for pruning (`webhooks:prune-events`), not when it stops
 * de-duplicating — an unpruned expired row simply keeps rejecting a redelivery,
 * which is the safe direction to err in.
 */
class WebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'event_key',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => WebhookProvider::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Claim a provider event key, returning true only the first time it is seen.
     *
     * The unique index on (provider, event_key) is what makes this atomic: two
     * concurrent deliveries of the same event race on the insert, and exactly one
     * of them wins.
     */
    public static function claim(WebhookProvider $provider, string $eventKey, CarbonInterface $expiresAt): bool
    {
        try {
            static::query()->create([
                'provider' => $provider,
                'event_key' => $eventKey,
                'expires_at' => $expiresAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
