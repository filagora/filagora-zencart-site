<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends one message through Faivelo's transactional API (`POST /emails`).
 *
 * Transactional mail only: the endpoint takes at most 50 recipients and has no per-send
 * tagging, so newsletter campaigns stay on their own provider.
 */
class FaiveloApiTransport extends AbstractTransport
{
    private const MAX_RECIPIENTS = 50;

    public function __construct(protected string $key, protected string $endpoint)
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransportException
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $sender = $envelope->getSender();
        $copied = array_merge($this->addresses($email->getCc()), $this->addresses($email->getBcc()));

        $to = array_values(array_filter(
            $this->addresses($envelope->getRecipients()),
            static fn (string $address): bool => ! in_array($address, $copied, true),
        ));

        if (count($to) + count($copied) > self::MAX_RECIPIENTS) {
            throw new TransportException(sprintf('Faivelo accepts at most %d recipients per message.', self::MAX_RECIPIENTS));
        }

        $payload = array_filter([
            'from' => $sender->getAddress(),
            'fromName' => $sender->getName(),
            'to' => $to,
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => ($email->getReplyTo()[0] ?? null)?->getAddress(),
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'attachments' => $this->attachments($email),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $response = Http::withToken($this->key)->asJson()->timeout(10)->post($this->endpoint, $payload);

        if (! $response->successful()) {
            $reason = $response->json('error.message') ?? $response->json('error') ?? $response->body();

            throw new TransportException(
                sprintf('Request to Faivelo API failed. Reason: %s.', is_string($reason) ? $reason : $response->body()),
                $response->status(),
            );
        }

        $messageId = $response->json('messageId');

        if (is_string($messageId) && $messageId !== '') {
            $message->setMessageId($messageId);
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(static fn (Address $address): string => $address->getAddress(), $addresses));
    }

    /**
     * @return array<int, array{filename: string, content: string, contentType: string}>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $attachments[] = [
                'filename' => (string) ($headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment'),
                'content' => base64_encode($attachment->getBody()),
                'contentType' => (string) ($headers->get('Content-Type')?->getBody() ?? 'application/octet-stream'),
            ];
        }

        return $attachments;
    }

    public function __toString(): string
    {
        return 'faivelo';
    }
}
