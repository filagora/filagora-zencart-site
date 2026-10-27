<?php

namespace Tests\Feature;

use App\Notifications\SearchIndexHealthCheckFailedNotification;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class MailTestBannerTest extends TestCase
{
    private const MESSAGE = 'This is a test email from our new test site.';

    public function test_mail_banner_is_rendered_when_enabled(): void
    {
        config()->set('mail.test_banner.enabled', true);
        config()->set('mail.test_banner.message', self::MESSAGE);

        [$html, $text] = $this->renderMail();

        $this->assertStringContainsString('Test email:', $html);
        $this->assertStringContainsString(self::MESSAGE, $html);
        $this->assertStringContainsString('TEST EMAIL: '.self::MESSAGE, $text);
    }

    public function test_mail_banner_is_not_rendered_when_disabled(): void
    {
        config()->set('mail.test_banner.enabled', false);
        config()->set('mail.test_banner.message', self::MESSAGE);

        [$html, $text] = $this->renderMail();

        $this->assertStringNotContainsString('Test email:', $html);
        $this->assertStringNotContainsString(self::MESSAGE, $html);
        $this->assertStringNotContainsString('TEST EMAIL:', $text);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function renderMail(): array
    {
        $mail = (new SearchIndexHealthCheckFailedNotification('testing', 'https://example.test'))->toMail((object) []);

        return [
            $mail->render()->toHtml(),
            app(Markdown::class)->renderText($mail->markdown, $mail->data())->toHtml(),
        ];
    }
}
