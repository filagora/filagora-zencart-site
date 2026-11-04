<?php

namespace Tests\Unit;

use App\Support\MailText;
use Illuminate\Mail\Markdown;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MailTextTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function hostileText(): array
    {
        return [
            'a link' => ['[Verify your account](https://evil.test)', '<a '],
            'an image' => ['![](https://evil.test/pixel.gif)', '<img'],
            'a reference link' => ["[click][1]\n\n[1]: https://evil.test", '<a '],
            'an autolink' => ['<https://evil.test>', '<a '],
            'raw html' => ['<img src=x onerror=alert(1)>', '<img'],
            'a heading' => ['# Urgent', '<h1'],
            'emphasis' => ['**bold**', '<strong'],
            'a list' => ['- item', '<li'],
            'a numbered list' => ['1. item', '<ol'],
            'inline code' => ['`code`', '<code'],
        ];
    }

    #[DataProvider('hostileText')]
    public function test_markdown_in_the_text_does_nothing(string $text, string $forbidden): void
    {
        $html = (string) Markdown::parse((string) MailText::plain($text));

        $this->assertStringNotContainsString($forbidden, $html);
    }

    public function test_the_reader_sees_the_text_as_written(): void
    {
        $text = "Tom & Jerry's [v1.2] costs \$5 - really!";

        $this->assertStringContainsString(
            $text,
            html_entity_decode(strip_tags((string) Markdown::parse((string) MailText::plain($text))), ENT_QUOTES),
        );
        $this->assertSame($text, html_entity_decode((string) MailText::plain($text), ENT_QUOTES));
    }

    public function test_every_value_in_a_notification_mail_goes_through_it(): void
    {
        foreach (glob(resource_path('views/emails/notifications/*.blade.php')) as $template) {
            preg_match_all('/\{\{(.*?)\}\}/', (string) file_get_contents($template), $matches);

            foreach ($matches[1] as $value) {
                if (str_contains($value, '$') && ! str_contains($value, "\$item['url']")) {
                    $this->assertStringContainsString('MailText::plain(', $value, basename($template));
                }
            }
        }
    }
}
