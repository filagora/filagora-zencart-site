<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * A provider refusing to mail one recipient (suppressed after a bounce, unknown mailbox) is an
 * outcome of that address, not a fault in the site, so it is logged beside the provider's webhook
 * events instead of thrown into the exception tracker and the request that sent the mail. Any other
 * refusal (bad token, unverified sender, malformed payload) still throws.
 */
trait HandlesRecipientRejections
{
    /** What a provider's reason says when it is the recipient that was refused. */
    private const RECIPIENT_REJECTION_PATTERN = '/suppress|bounc|undeliverable|mailbox (?:does not exist|unavailable)|no such user|recipient (?:is )?(?:blocked|rejected|invalid)|blocked recipient/i';

    protected function isRecipientRejection(int $status, string $reason): bool
    {
        return $status >= 400 && $status < 500
            && ! in_array($status, [401, 403, 408, 429], true)
            && preg_match(self::RECIPIENT_REJECTION_PATTERN, $reason) === 1;
    }

    /**
     * @param  array<int, Address|string>  $recipients
     */
    protected function logRecipientRejection(string $channel, array $recipients, int $status, string $reason): void
    {
        Log::channel($channel)->warning('Provider refused the recipient; mail not sent.', [
            'recipients' => array_map(static fn (Address|string $recipient): string => $recipient instanceof Address ? $recipient->getAddress() : $recipient, array_values($recipients)),
            'status' => $status,
            'reason' => $reason,
        ]);
    }
}
