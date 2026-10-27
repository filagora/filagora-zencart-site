<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\NewsletterBrandingService;
use BackedEnum;
use Filagora\Cms\Models\CmsMediaAsset;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class NewsletterSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'newsletter-settings';

    protected string $view = 'filament.pages.newsletter-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.newsletter_settings');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.newsletter.manage');
    }

    public function getTitle(): string
    {
        return 'Newsletter Settings';
    }

    public function mount(NewsletterBrandingService $brandingService): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($brandingService->formData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Branding')
                    ->description('Shared header, logo, and footer content used by all newsletter campaigns.')
                    ->schema([
                        TextInput::make('brand_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('header_eyebrow')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Small shared label shown above the newsletter subject.'),
                        Select::make('logo_asset_id')
                            ->label('Logo Asset')
                            ->options(fn (): array => app(NewsletterBrandingService::class)->imageAssetOptions())
                            ->searchable()
                            ->preload()
                            ->placeholder('No shared logo'),
                        Placeholder::make('logo_asset_preview')
                            ->label('Selected Logo')
                            ->content(fn (Get $get): HtmlString|string => $this->logoAssetPreviewMarkup($get('logo_asset_id')))
                            ->visible(fn (Get $get): bool => is_numeric($get('logo_asset_id')))
                            ->columnSpanFull(),
                        TextInput::make('logo_alt')
                            ->maxLength(255)
                            ->helperText('Alt text used when a shared logo is configured.'),
                        Textarea::make('footer_text')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('manage_link_label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Text used for the shared unsubscribe/manage link.'),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(NewsletterBrandingService $brandingService): void
    {
        $state = $this->form->getState();
        $brandingService->save(is_array($state) ? $state : []);

        Notification::make()
            ->title('Newsletter settings saved')
            ->success()
            ->send();
    }

    private function logoAssetPreviewMarkup(mixed $assetId): HtmlString|string
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
    <img src="{$url}" alt="{$alt}" style="max-width:220px;max-height:96px;width:auto;height:auto;">
</div>
HTML);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
