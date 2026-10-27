<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\PluginModerationService;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class PluginStats extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    private Collection $pendingPlugins;

    private Collection $pendingReleases;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.plugin.moderate');
    }

    protected function getStats(): array
    {
        [$pendingPlugins, $pendingReleases] = app(PluginModerationService::class)->getModeratedPlugins();
        $total = $pendingPlugins->count() + $pendingReleases->count();

        return [
            Stat::make('Plugins needing Moderation', (string) $total)
                ->description(
                    sprintf(
                        '%d plugins and %d releases pending review',
                        $pendingPlugins->count(),
                        $pendingReleases->count()
                    )
                )
                ->url('/plugins')
                ->color($total > 0 ? 'danger' : 'success'),
        ];
    }
}
