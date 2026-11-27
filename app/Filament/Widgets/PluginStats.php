<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Plugins\PluginReviewQueue;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PluginStats extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.plugin.moderate');
    }

    protected function getStats(): array
    {
        $counts = app(PluginReviewQueue::class)->counts();
        $total = $counts['plugins'] + $counts['releases'];

        return [
            Stat::make('Plugins needing Moderation', (string) $total)
                ->description(__('plugin.moderation.queue.pending_review', PluginReviewQueue::countLabels($counts)))
                ->url(route('plugins.index'))
                ->color($total > 0 ? 'danger' : 'success'),
        ];
    }
}
