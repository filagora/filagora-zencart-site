<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class SystemDetailsWidget extends Widget
{
    protected string $view = 'filament.widgets.system-details-widget';

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.system.settings.manage');
    }
}
