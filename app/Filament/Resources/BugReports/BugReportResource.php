<?php

namespace App\Filament\Resources\BugReports;

use App\BugReportStatus;
use App\Filament\Resources\BugReports\Pages\EditBugReport;
use App\Filament\Resources\BugReports\Pages\ListBugReports;
use App\Filament\Resources\BugReports\Schemas\BugReportForm;
use App\Filament\Resources\BugReports\Tables\BugReportsTable;
use App\Models\BugReport;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Http\Request;
use UnitEnum;

class BugReportResource extends Resource
{
    protected static ?string $model = BugReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.bug_reports');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.bug_reports.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.bug_reports.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getOpenReportCount();

        return $count > 0 ? static::formatNavigationBadgeCount($count) : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $count = static::getOpenReportCount();

        if ($count === 0) {
            return null;
        }

        return trans_choice('filament.resources.bug_reports.badge_tooltip', $count, ['count' => $count]);
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return BugReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BugReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBugReports::route('/'),
            'edit' => EditBugReport::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function getOpenReportCount(): int
    {
        return static::rememberRequestMetric('open_report_count', static fn (): int => BugReport::query()
            ->where('status', BugReportStatus::REPORTED)
            ->count());
    }

    protected static function formatNavigationBadgeCount(int $count): string
    {
        return $count > 99 ? '99+' : (string) $count;
    }

    private static function rememberRequestMetric(string $suffix, callable $resolver): int
    {
        $request = app()->bound('request') ? app(Request::class) : null;

        if (! $request instanceof Request) {
            return (int) $resolver();
        }

        $key = sprintf('filament.navigation_metric.%s.%s', static::class, $suffix);

        if ($request->attributes->has($key)) {
            return (int) $request->attributes->get($key);
        }

        $value = (int) $resolver();
        $request->attributes->set($key, $value);

        return $value;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('queues');
    }
}
