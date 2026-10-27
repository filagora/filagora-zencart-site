<?php

namespace App\Filament\Resources\Newsletters;

use App\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Resources\Newsletters\Pages\EditNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Filament\Resources\Newsletters\Pages\ViewNewsletter;
use App\Filament\Resources\Newsletters\RelationManagers\NewsletterLinkClicksRelationManager;
use App\Filament\Resources\Newsletters\RelationManagers\NewsletterSendsRelationManager;
use App\Filament\Resources\Newsletters\Schemas\NewsletterForm;
use App\Filament\Resources\Newsletters\Schemas\NewsletterInfolist;
use App\Filament\Resources\Newsletters\Tables\NewslettersTable;
use App\Models\Newsletter;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.newsletters');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewslettersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NewsletterSendsRelationManager::class,
            NewsletterLinkClicksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletters::route('/'),
            'create' => CreateNewsletter::route('/create'),
            'view' => ViewNewsletter::route('/{record}'),
            'edit' => EditNewsletter::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
