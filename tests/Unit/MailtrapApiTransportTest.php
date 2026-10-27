<?php

namespace Tests\Unit;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MailtrapApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mailtrap.token', 'test-mailtrap-token');
        config()->set('services.mailtrap.endpoint', 'https://send.api.mailtrap.io/api/send');
    }

    public function test_it_sends_via_the_mailtrap_api_with_the_expected_payload(): void
    {
        Http::fake([
            'send.api.mailtrap.io/*' => Http::response([
                'success' => true,
                'message_ids' => ['abc-123'],
            ], 200),
        ]);

        Mail::mailer('mailtrap')->to('recipient@example.com')->send(new MailtrapTestMailable);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'https://send.api.mailtrap.io/api/send'
                && $request->hasHeader('Authorization', 'Bearer test-mailtrap-token')
                && ($body['from']['email'] ?? null) === 'hello@example.com'
                && ($body['to'][0]['email'] ?? null) === 'recipient@example.com'
                && ($body['subject'] ?? null) === 'Mailtrap transport test'
                && str_contains((string) ($body['html'] ?? ''), 'Hello from Mailtrap')
                && ! array_key_exists('cc', $body)
                && ! array_key_exists('bcc', $body);
        });
    }

    public function test_it_throws_a_transport_exception_when_the_api_reports_failure(): void
    {
        Http::fake([
            'send.api.mailtrap.io/*' => Http::response([
                'success' => false,
                'errors' => ['\'to\' is not a valid list of email addresses'],
            ], 400),
        ]);

        $this->expectException(TransportException::class);

        Mail::mailer('mailtrap')->to('recipient@example.com')->send(new MailtrapTestMailable);
    }

    public function test_it_translates_the_mailtrap_variables_header_into_custom_variables(): void
    {
        Http::fake([
            'send.api.mailtrap.io/*' => Http::response([
                'success' => true,
                'message_ids' => ['abc-123'],
            ], 200),
        ]);

        Mail::mailer('mailtrap')->to('recipient@example.com')->send(new MailtrapTestMailableWithVariables);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return ($body['custom_variables']['newsletter_send_id'] ?? null) === '42';
        });
    }
}

class MailtrapTestMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@example.com', 'Test Sender'),
            subject: 'Mailtrap transport test',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hello from Mailtrap</p>',
        );
    }
}

class MailtrapTestMailableWithVariables extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@example.com', 'Test Sender'),
            subject: 'Mailtrap transport test with variables',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hello from Mailtrap</p>',
        );
    }

    public function build(): static
    {
        $this->withSymfonyMessage(function ($message): void {
            $message->getHeaders()->addTextHeader('X-Mailtrap-Variables', json_encode([
                'newsletter_send_id' => 42,
            ], JSON_THROW_ON_ERROR));
        });

        return $this;
    }
}
