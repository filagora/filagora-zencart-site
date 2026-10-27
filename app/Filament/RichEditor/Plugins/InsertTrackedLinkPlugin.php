<?php

namespace App\Filament\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class InsertTrackedLinkPlugin implements RichContentPlugin
{
    public const CLASS_MARKER = 'nl-tracked-link';

    public const ID_MARKER_PREFIX = 'nl-tracked-link-id--';

    public const STYLE_MARKER_PREFIX = 'nl-tracked-link--';

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
            RichEditorTool::make('insertTrackedLink')
                ->label('Insert Tracked Link')
                ->icon(Heroicon::OutlinedCursorArrowRipple)
                ->action(),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('insertTrackedLink')
                ->label('Insert Tracked Link')
                ->modalHeading('Insert Tracked Link')
                ->modalWidth(Width::Large)
                ->schema([
                    TextInput::make('label')
                        ->label('Link text')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('url')
                        ->label('Destination URL')
                        ->url()
                        ->required()
                        ->maxLength(2048),
                    Select::make('style')
                        ->label('Appearance')
                        ->options([
                            'button' => 'Button',
                            'link' => 'Plain link',
                        ])
                        ->default('button')
                        ->required(),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $class = implode(' ', [
                        self::CLASS_MARKER,
                        self::STYLE_MARKER_PREFIX.$data['style'],
                        self::ID_MARKER_PREFIX.(string) Str::uuid(),
                    ]);

                    $component->runCommands(
                        [
                            EditorCommand::make('insertContent', arguments: [[
                                'type' => 'text',
                                'text' => $data['label'],
                                'marks' => [[
                                    'type' => 'link',
                                    'attrs' => [
                                        'href' => $data['url'],
                                        'class' => $class,
                                    ],
                                ]],
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }
}
