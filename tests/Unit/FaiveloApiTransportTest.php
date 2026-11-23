<?php

namespace Tests\Unit;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class FaiveloApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.faivelo.key', 'fvl_live_test');
        config()->set('services.faivelo.endpoint', 'https://faivelo.test/api/v1/emails');
    }

    public function test_it_sends_via_the_faivelo_api_with_the_expected_payload(): void
    {
        Http::fake(['faivelo.test/*' => Http::response(['id' => 'em_1', 'messageId' => '<abc@faivelo.test>', 'status' => 'sent'], 200)]);

        Mail::mailer('faivelo')->to('recipient@example.com')->send(new FaiveloTestMailable);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'https://faivelo.test/api/v1/emails'
                && $request->hasHeader('Authorization', 'Bearer fvl_live_test')
                && $body['from'] === 'hello@example.com'
                && $body['fromName'] === 'Test Sender'
                && $body['to'] === ['recipient@example.com']
                && $body['subject'] === 'Faivelo transport test'
                && str_contains($body['html'], 'Hello from Faivelo')
                && ! array_key_exists('cc', $body)
                && ! array_key_exists('bcc', $body)
                && ! array_key_exists('replyTo', $body);
        });
    }

    public function test_cc_and_bcc_recipients_are_not_repeated_in_to(): void
    {
        Http::fake(['faivelo.test/*' => Http::response(['id' => 'em_2', 'status' => 'sent'], 200)]);

        Mail::mailer('faivelo')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->send(new FaiveloTestMailable);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $body['to'] === ['to@example.com']
                && $body['cc'] === ['cc@example.com']
                && $body['bcc'] === ['bcc@example.com'];
        });
    }

    public function test_a_recipient_the_api_refuses_is_logged_and_does_not_throw(): void
    {
        Log::shouldReceive('channel')->with('faivelo')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['recipients'] === ['bounced@example.com'] && $context['status'] === 422);

        Http::fake(['faivelo.test/*' => Http::response(['error' => 'Recipient is suppressed after a hard bounce'], 422)]);

        Mail::mailer('faivelo')->to('bounced@example.com')->send(new FaiveloTestMailable);
    }

    public function test_it_throws_a_transport_exception_when_the_api_rejects_the_send(): void
    {
        Http::fake(['faivelo.test/*' => Http::response(['error' => 'Sender domain is not verified'], 422)]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Request to Faivelo API failed.');

        Mail::mailer('faivelo')->to('recipient@example.com')->send(new FaiveloTestMailable);
    }

    public function test_it_refuses_more_recipients_than_the_endpoint_accepts(): void
    {
        Http::fake();

        $recipients = array_map(fn (int $i): string => "member{$i}@example.com", range(1, 51));

        try {
            Mail::mailer('faivelo')->to($recipients)->send(new FaiveloTestMailable);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('at most 50 recipients', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}

class FaiveloTestMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@example.com', 'Test Sender'),
            subject: 'Faivelo transport test',
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Hello from Faivelo</p>');
    }
}
