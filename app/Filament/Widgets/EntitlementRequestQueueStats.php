<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filagora\Entitlements\Filament\Resources\EntitlementRequests\EntitlementRequestResource;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EntitlementRequestQueueStats extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.entitlements.view')
            && EntitlementRequestResource::getPendingRequestCount() > 0;
    }

    protected function getStats(): array
    {
        $pendingCount = EntitlementRequestResource::getPendingRequestCount();

        return [
            Stat::make(__('dashboard.entitlement_requests.title'), (string) $pendingCount)
                ->description(trans_choice('dashboard.entitlement_requests.pending_description', $pendingCount, ['count' => $pendingCount]))
                ->url(EntitlementRequestResource::getUrl('index'))
                ->color('warning'),
        ];
    }
}
