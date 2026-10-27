<?php

namespace App\Filament\Resources\Newsletters\Schemas;

use App\Filament\RichEditor\Plugins\InsertMediaLibraryImagePlugin;
use App\Filament\RichEditor\Plugins\InsertTrackedLinkPlugin;
use App\Models\Newsletter;
use App\Models\NewsletterBlock;
use Filagora\Cms\Models\CmsMediaAsset;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class NewsletterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(null)
            ->components([
                Wizard::make(static::steps())->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        return [
            Step::make('Details')
                ->description('Campaign name, subject, and appearance')
                ->schema(static::detailsFields())
                ->columns(2),
            Step::make('Content')
                ->description('Compose the campaign content blocks')
                ->schema([
                    Placeholder::make('content_blocks_intro')
                        ->label('')
                        ->content('Every campaign automatically starts with the shared header and ends with the shared footer (logo, eyebrow, footer copy, and unsubscribe link are managed on the Newsletter Settings page). Add blocks below for the content in between, including reusable blocks from the block library.')
                        ->columnSpanFull(),
                    static::contentBlocksField(),
                ]),
            Step::make('Review & Send')
                ->description('Preview the rendered email before sending')
                ->schema([static::previewField()]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function detailsFields(): array
    {
        return [
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->helperText('Internal campaign name.'),
            TextInput::make('subject')
                ->required()
                ->maxLength(255),
            TextInput::make('preheader')
                ->maxLength(255)
                ->helperText('Short preview text shown by many email clients.'),
            ColorPicker::make('background_color')
                ->label('Outer Background Color')
                ->required()
                ->default('#F4F1EA')
                ->hex()
                ->rule(['regex:/^#[0-9A-Fa-f]{6}$/'])
                ->helperText('Per-newsletter outer email canvas color. Defaults to the site background tone.'),
            ColorPicker::make('inner_background_color')
                ->label('Inner Background Color')
                ->required()
                ->default('#FBF8F2')
                ->hex()
                ->rule(['regex:/^#[0-9A-Fa-f]{6}$/'])
                ->helperText('Per-newsletter content card color. Defaults to the lighter site panel tone.'),
        ];
    }

    private static function contentBlocksField(): Repeater
    {
        return Repeater::make('content_blocks')
            ->label('Content Blocks')
            ->default([
                ['type' => 'text'],
            ])
            ->required()
            ->minItems(1)
            ->addActionLabel('Add content block')
            ->reorderableWithButtons()
            ->cloneable()
            ->collapsible()
            ->collapsed()
            ->itemLabel(fn (array $state): string => match ($state['type'] ?? null) {
                'reusable' => self::reusableBlockLabel($state['newsletter_block_id'] ?? null),
                'text' => trim((string) ($state['heading'] ?? '')) ?: 'Text block',
                'image' => trim((string) ($state['caption'] ?? '')) ?: 'Image block',
                'button' => trim((string) ($state['button_label'] ?? '')) ?: 'Button block',
                default => 'Content block',
            })
            ->schema([
                Select::make('type')
                    ->options([
                        'reusable' => 'Reusable block',
                        'text' => 'Text section',
                        'image' => 'Image',
                        'button' => 'Button',
                    ])
                    ->required()
                    ->live(),
                Select::make('newsletter_block_id')
                    ->label('Block')
                    ->options(fn (): array => self::reusableBlockOptions())
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('type') === 'reusable')
                    ->visible(fn (Get $get): bool => $get('type') === 'reusable')
                    ->helperText('Manage the block library from Newsletter Blocks.'),
                TextInput::make('heading')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('type') === 'text'),
                RichEditor::make('body_html')
                    ->label('Content')
                    ->extraInputAttributes([
                        'style' => 'min-height: 16rem;',
                    ])
                    ->columnSpanFull()
                    ->required(fn (Get $get): bool => $get('type') === 'text')
                    ->visible(fn (Get $get): bool => $get('type') === 'text')
                    ->helperText('Rendered inside the shared newsletter layout for this section.')
                    ->plugins([InsertMediaLibraryImagePlugin::make(), InsertTrackedLinkPlugin::make()])
                    ->enableToolbarButtons(['insertMediaLibraryImage', 'insertTrackedLink']),
                Select::make('asset_id')
                    ->label('Image Asset')
                    ->options(fn (): array => self::imageAssetOptions())
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('type') === 'image')
                    ->visible(fn (Get $get): bool => $get('type') === 'image'),
                Placeholder::make('asset_preview')
                    ->label('Selected Asset')
                    ->content(fn (Get $get): HtmlString|string => self::assetPreviewMarkup($get('asset_id')))
                    ->visible(fn (Get $get): bool => $get('type') === 'image' && self::hasSelectedAsset($get('asset_id')))
                    ->columnSpanFull(),
                TextInput::make('asset_alt')
                    ->label('Image Alt Text')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('type') === 'image'),
                Textarea::make('caption')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('type') === 'image'),
                TextInput::make('button_label')
                    ->label('Button Label')
                    ->required(fn (Get $get): bool => $get('type') === 'button')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('type') === 'button'),
                TextInput::make('button_url')
                    ->label('Button URL')
                    ->url()
                    ->required(fn (Get $get): bool => $get('type') === 'button')
                    ->visible(fn (Get $get): bool => $get('type') === 'button'),
            ])
            ->columns(2)
            ->columnSpanFull()
            ->helperText('Campaigns are authored with structured blocks. Use a reusable block to insert content from the Newsletter Blocks library.');
    }

    private static function previewField(): Placeholder
    {
        return Placeholder::make('rendered_preview')
            ->label('Rendered Email')
            ->content(fn (?Newsletter $record): HtmlString|string => self::renderedPreviewMarkup($record))
            ->columnSpanFull();
    }

    private static function renderedPreviewMarkup(?Newsletter $record): HtmlString
    {
        if (! $record instanceof Newsletter) {
            return new HtmlString('<p style="margin:0;color:#52616f;font-size:0.95rem;">Save this newsletter as a draft to see the rendered email preview.</p>');
        }

        $previewUrl = route('newsletters.preview', $record);

        return new HtmlString(<<<HTML
<div
    x-data="{
        isSavingPreview: false,
        async saveAndReloadPreview() {
            this.isSavingPreview = true;
            try {
                await \$wire.saveForPreview();
                const frame = this.\$refs.previewFrame;
                if (frame) { frame.src = frame.src; }
            } finally {
                this.isSavingPreview = false;
            }
        },
    }"
    style="display:grid;gap:0.75rem;"
>
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
        <p style="margin:0;color:#52616f;font-size:0.95rem;line-height:1.6;">
            This preview uses the rendered newsletter email template. Since edits aren't saved until you save, use the button below to save your current changes and refresh the preview together.
            <a href="{$previewUrl}" target="_blank" rel="noopener noreferrer" style="color:#8a4b19;font-weight:600;">Open full preview in a new tab</a>
        </p>
        <button
            type="button"
            x-on:click="saveAndReloadPreview()"
            x-bind:disabled="isSavingPreview"
            style="display:inline-flex;align-items:center;justify-content:center;padding:0.6rem 0.9rem;border:1px solid #d6d3d1;border-radius:999px;background:#ffffff;color:#1f2a37;font-size:0.9rem;font-weight:600;cursor:pointer;"
        >
            <span x-text="isSavingPreview ? 'Saving…' : 'Save & refresh preview'"></span>
        </button>
    </div>
    <iframe
        src="{$previewUrl}"
        title="Newsletter preview"
        x-ref="previewFrame"
        style="width:100%;min-height:780px;border:1px solid #d6d3d1;border-radius:0.85rem;background:#ffffff;"
        loading="lazy"
    ></iframe>
</div>
HTML);
    }

    /**
     * @return array<int, string>
     */
    private static function reusableBlockOptions(): array
    {
        return NewsletterBlock::query()
            ->active()
            ->where('is_system', false)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (NewsletterBlock $block): array => [$block->id => $block->name])
            ->all();
    }

    private static function reusableBlockLabel(mixed $newsletterBlockId): string
    {
        if (! is_numeric($newsletterBlockId)) {
            return 'Reusable block';
        }

        $name = NewsletterBlock::query()->whereKey((int) $newsletterBlockId)->value('name');

        return is_string($name) && $name !== '' ? $name : 'Reusable block';
    }

    /**
     * @return array<int, string>
     */
    private static function imageAssetOptions(): array
    {
        return CmsMediaAsset::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('filename')
            ->get()
            ->mapWithKeys(fn (CmsMediaAsset $asset): array => [
                $asset->id => trim($asset->filename.($asset->alt_text ? ' - '.$asset->alt_text : '')),
            ])
            ->all();
    }

    private static function hasSelectedAsset(mixed $assetId): bool
    {
        return is_numeric($assetId) && CmsMediaAsset::query()->whereKey((int) $assetId)->exists();
    }

    private static function assetPreviewMarkup(mixed $assetId): HtmlString|string
    {
        if (! is_numeric($assetId)) {
            return '';
        }

        $asset = CmsMediaAsset::query()->find((int) $assetId);

        if (! $asset instanceof CmsMediaAsset || ! $asset->isImage() || blank($asset->publicUrl())) {
            return 'Selected asset preview is unavailable.';
        }

        $url = e((string) $asset->publicUrl());
        $alt = e((string) ($asset->alt_text ?: $asset->filename));

        return new HtmlString(<<<HTML
<div style="display:flex;align-items:center;justify-content:center;min-height:120px;padding:1rem;border:1px solid #d6d3d1;border-radius:0.85rem;background:#ffffff;">
    <img src="{$url}" alt="{$alt}" style="max-width:240px;max-height:140px;width:auto;height:auto;">
</div>
HTML);
    }
}
