<?php

namespace Tests\Unit;

use App\Filament\Resources\Newsletters\Schemas\NewsletterForm;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class NewsletterFormTest extends TestCase
{
    #[Test]
    public function text_content_blocks_use_the_rich_editor_field(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("RichEditor::make('body_html')", $source);
        $this->assertStringContainsString('->extraInputAttributes([', $source);
        $this->assertStringContainsString("'style' => 'min-height: 16rem;'", $source);
        $this->assertStringNotContainsString("Textarea::make('body_html')", $source);
    }

    #[Test]
    public function form_is_organized_into_wizard_steps(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('Wizard::make(static::steps())', $source);
        $this->assertStringContainsString("Step::make('Details')", $source);
        $this->assertStringContainsString("Step::make('Content')", $source);
        $this->assertStringContainsString("Step::make('Review & Send')", $source);
    }

    #[Test]
    public function form_no_longer_exposes_disabled_status_or_scheduling_fields(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringNotContainsString("Select::make('status')", $source);
        $this->assertStringNotContainsString("DateTimePicker::make('scheduled_at')", $source);
    }

    #[Test]
    public function content_and_preview_fields_span_the_full_form_width(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("->helperText('Campaigns are authored with structured blocks. Use a reusable block to insert content from the Newsletter Blocks library.')", $source);
        $this->assertStringContainsString('private static function contentBlocksField(): Repeater', $source);
        $this->assertStringContainsString('private static function previewField(): Placeholder', $source);
    }

    #[Test]
    public function content_blocks_support_reusable_library_references_instead_of_manual_header_footer_rows(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("'reusable' => 'Reusable block'", $source);
        $this->assertStringContainsString("Select::make('newsletter_block_id')", $source);
        $this->assertStringNotContainsString("'shared_header' => 'Shared header'", $source);
        $this->assertStringNotContainsString("'shared_footer' => 'Shared footer'", $source);
    }

    #[Test]
    public function campaign_form_includes_a_per_newsletter_background_color_field(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("ColorPicker::make('background_color')", $source);
        $this->assertStringContainsString("->default('#F4F1EA')", $source);
        $this->assertStringContainsString('->hex()', $source);
        $this->assertStringContainsString('site background tone', $source);
    }

    #[Test]
    public function campaign_form_includes_a_per_newsletter_inner_background_color_field(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("ColorPicker::make('inner_background_color')", $source);
        $this->assertStringContainsString("->default('#FBF8F2')", $source);
        $this->assertStringContainsString('lighter site panel tone', $source);
    }

    #[Test]
    public function rendered_preview_markup_includes_a_save_and_refresh_button(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('Save & refresh preview', $source);
        $this->assertStringContainsString('saveAndReloadPreview()', $source);
        $this->assertStringContainsString('await \$wire.saveForPreview()', $source);
        $this->assertStringContainsString('x-ref="previewFrame"', $source);
    }

    #[Test]
    public function text_block_rich_editor_supports_inserting_images_from_the_media_library(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('InsertMediaLibraryImagePlugin::make()', $source);
        $this->assertStringContainsString("'insertMediaLibraryImage'", $source);
    }

    #[Test]
    public function text_block_rich_editor_supports_inserting_tracked_links(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('InsertTrackedLinkPlugin::make()', $source);
        $this->assertStringContainsString("'insertTrackedLink'", $source);
    }

    #[Test]
    public function rendered_preview_markup_prompts_to_save_a_draft_when_no_record_exists(): void
    {
        $reflection = new ReflectionClass(NewsletterForm::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('Save this newsletter as a draft to see the rendered email preview.', $source);
    }
}
