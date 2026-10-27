<?php

namespace App\Filament\RichEditor\Plugins;

use App\Models\CmsMediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class InsertMediaLibraryImagePlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<never>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<never>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('insertMediaLibraryImage')
                ->label('Insert from Media Library')
                ->icon(Heroicon::OutlinedPhoto)
                ->action(),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('insertMediaLibraryImage')
                ->label('Insert from Media Library')
                ->modalHeading('Insert Image from Media Library')
                ->modalWidth(Width::Large)
                ->schema([
                    Select::make('asset_id')
                        ->label('Image')
                        ->options(fn (): array => self::imageAssetOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Placeholder::make('asset_preview')
                        ->label('Preview')
                        ->content(fn (Get $get): HtmlString|string => self::assetPreviewMarkup($get('asset_id')))
                        ->visible(fn (Get $get): bool => filled($get('asset_id')))
                        ->columnSpanFull(),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $asset = CmsMediaAsset::query()->find($data['asset_id']);

                    if (! $asset instanceof CmsMediaAsset || blank($asset->publicUrl())) {
                        return;
                    }

                    $component->runCommands(
                        [
                            EditorCommand::make('insertContent', arguments: [[
                                'type' => 'image',
                                'attrs' => [
                                    'alt' => $asset->alt_text,
                                    'id' => 'media-'.$asset->id,
                                    'src' => $asset->publicUrl(),
                                ],
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
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
