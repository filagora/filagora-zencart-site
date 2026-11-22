<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\StaffAlertRecipients as Recipients;
use App\Notifications\StaffAlerts;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Who each staff alert would go to, so an administrator can check the list before switching staff
 * alerts to individuals (the setting that needs `operator.system.settings.manage`, as does this page).
 */
class StaffAlertRecipients extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'staff-alert-recipients';

    protected string $view = 'filament.pages.staff-alert-recipients';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.staff_alert_recipients');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('system');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.system.settings.manage');
    }

    public function getTitle(): string
    {
        return __('filament.pages.staff_alert_recipients.title');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'toIndividuals' => app(StaffAlerts::class)->toIndividuals(),
            'categories' => app(Recipients::class)->report(),
        ];
    }
}
