<?php

namespace App\Filament\Resources\NewsletterBlocks;

use App\Filament\Resources\NewsletterBlocks\Pages\CreateNewsletterBlock;
use App\Filament\Resources\NewsletterBlocks\Pages\EditNewsletterBlock;
use App\Filament\Resources\NewsletterBlocks\Pages\ListNewsletterBlocks;
use App\Filament\Resources\NewsletterBlocks\Pages\ViewNewsletterBlock;
use App\Filament\Resources\NewsletterBlocks\Schemas\NewsletterBlockForm;
use App\Filament\Resources\NewsletterBlocks\Schemas\NewsletterBlockInfolist;
use App\Filament\Resources\NewsletterBlocks\Tables\NewsletterBlocksTable;
use App\Models\NewsletterBlock;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NewsletterBlockResource extends Resource
{
    protected static ?string $model = NewsletterBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.newsletter_blocks');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterBlockForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterBlockInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterBlocks::route('/'),
            'create' => CreateNewsletterBlock::route('/create'),
            'view' => ViewNewsletterBlock::route('/{record}'),
            'edit' => EditNewsletterBlock::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
