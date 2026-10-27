<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class MailtrapApiTransport extends AbstractTransport
{
    public function __construct(protected string $token, protected string $endpoint)
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

        $payload = array_filter([
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'cc' => $this->formatAddresses($email->getCc()),
            'bcc' => $this->formatAddresses($email->getBcc()),
            'reply_to' => $email->getReplyTo() === [] ? null : $this->formatAddress($email->getReplyTo()[0]),
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'attachments' => $this->formatAttachments($email),
            'custom_variables' => $this->formatCustomVariables($email),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $response = Http::withToken($this->token)->asJson()->timeout(10)->post($this->endpoint, $payload);

        $result = $response->json();

        if (! $response->successful() || ! ($result['success'] ?? false)) {
            $errors = implode(', ', $result['errors'] ?? [$response->body()]);

            throw new TransportException(
                sprintf('Request to Mailtrap API failed. Reason: %s.', $errors),
                $response->status()
            );
        }

        $messageId = $result['message_ids'][0] ?? null;

        if (is_string($messageId)) {
            $message->setMessageId($messageId);
        }
    }

    /**
     * Get the recipients without CC or BCC.
     *
     * @return array<int, Address>
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        return array_filter($envelope->getRecipients(), function (Address $address) use ($email) {
            return in_array($address, array_merge($email->getCc(), $email->getBcc()), true) === false;
        });
    }

    /**
     * @return array{email: string, name?: string}
     */
    protected function formatAddress(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    protected function formatAddresses(array $addresses): array
    {
        return array_values(array_map($this->formatAddress(...), $addresses));
    }

    /**
     * @return array<int, array{content: string, filename: string, type?: string, disposition?: string, content_id?: string}>
     */
    protected function formatAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?? '';
            $disposition = $headers->getHeaderBody('Content-Disposition') === 'inline' ? 'inline' : 'attachment';

            $item = array_filter([
                'content' => base64_encode($attachment->getBody()),
                'filename' => $filename,
                'type' => $headers->get('Content-Type')?->getBody(),
                'disposition' => $disposition,
                'content_id' => $disposition === 'inline' && $attachment->hasContentId() ? $attachment->getContentId() : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $attachments[] = $item;
        }

        return $attachments;
    }

    /**
     * Translates the app's X-Mailtrap-Variables JSON header into Mailtrap's
     * `custom_variables` payload field, which is echoed back on webhook events.
     * Mailtrap requires custom_variables values to be strings.
     *
     * @return array<string, string>
     */
    protected function formatCustomVariables(Email $email): array
    {
        $header = $email->getHeaders()->get('X-Mailtrap-Variables');

        if ($header === null) {
            return [];
        }

        $decoded = json_decode($header->getBodyAsString(), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_map(static fn (mixed $value): string => (string) $value, $decoded);
    }

    public function __toString(): string
    {
        return 'mailtrap';
    }
}
