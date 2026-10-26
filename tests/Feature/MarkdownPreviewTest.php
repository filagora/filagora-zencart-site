<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_preview_markdown_with_application_extensions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('markdown.preview'), [
            'body' => '**Bold** [color=red]Red[/color]',
        ])->assertOk()
            ->assertJsonPath('html', "<p><strong>Bold</strong> <span style=\"color:red;\">Red</span></p>\n");
    }

    public function test_preview_escapes_html_rejects_unsafe_links_and_validates_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('markdown.preview'), [
            'body' => '<script>alert(1)</script> [unsafe](javascript:alert(2))',
        ])->assertOk();

        $html = (string) $response->json('html');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);

        $this->actingAs($user)->postJson(route('markdown.preview'), [
            'body' => str_repeat('a', 20001),
        ])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_preview_requires_verified_authentication(): void
    {
        $this->postJson(route('markdown.preview'), ['body' => '**Bold**'])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson(route('markdown.preview'), ['body' => '**Bold**'])
            ->assertForbidden();
    }
}
