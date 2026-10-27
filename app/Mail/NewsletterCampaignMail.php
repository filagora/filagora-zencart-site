<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Services\NewsletterBrandingService;
use App\Services\NewsletterContentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Newsletter $newsletter,
        public readonly string $unsubscribeUrl,
        public readonly bool $preview = false,
        public readonly ?int $newsletterSendId = null,
        public readonly ?string $deliveryProvider = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->preview ? '[Preview] ' : '').$this->newsletter->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $branding = app(NewsletterBrandingService::class)->settings();
        $context = [
            'branding' => $branding,
            'newsletter' => $this->newsletter,
            'unsubscribe_url' => $this->unsubscribeUrl,
            'newsletter_send_id' => $this->newsletterSendId,
        ];

        return new Content(
            view: 'emails.newsletter.campaign',
            text: 'emails.newsletter.campaign-text',
            with: [
                'branding' => $branding,
                'renderedBodyHtml' => app(NewsletterContentRenderer::class)->renderHtml($this->newsletter, $context),
                'renderedBodyText' => app(NewsletterContentRenderer::class)->renderText($this->newsletter, $context),
            ],
        );
    }

    public function build(): static
    {
        if ($this->newsletterSendId !== null && $this->deliveryProvider === 'mailgun') {
            $this
                ->metadata('newsletter_send_id', (string) $this->newsletterSendId)
                ->withSymfonyMessage(function ($message): void {
                    $message->getHeaders()->addTextHeader('X-Mailgun-Variables', json_encode([
                        'newsletter_send_id' => $this->newsletterSendId,
                    ], JSON_THROW_ON_ERROR));
                });
        }

        if ($this->newsletterSendId !== null && $this->deliveryProvider === 'mailtrap') {
            $this->withSymfonyMessage(function ($message): void {
                $message->getHeaders()->addTextHeader('X-Mailtrap-Variables', json_encode([
                    'newsletter_send_id' => $this->newsletterSendId,
                ], JSON_THROW_ON_ERROR));
            });
        }

        return $this;
    }
}
