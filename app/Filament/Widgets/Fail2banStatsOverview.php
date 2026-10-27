<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Fail2banStatsOverview extends StatsOverviewWidget
{
    public int $jailCount = 0;

    public int $currentlyBanned = 0;

    public int $totalFailedAttempts = 0;

    /**
     * @var array<int, array{
     *     name: string,
     *     currently_failed: int,
     *     total_failed: int,
     *     currently_banned: int,
     *     total_banned: int,
     *     banned_ips: array<int, string>,
     *     log_files: array<int, string>,
     *     journal_matches: ?string,
     *     raw_output: string
     * }>
     */
    public array $jails = [];

    protected function getStats(): array
    {
        return [
            Stat::make(__('filament.pages.fail2ban.stats.jails'), (string) $this->jailCount)
                ->description(__('filament.pages.fail2ban.stats.jails_hint'))
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('gray'),
            Stat::make(__('filament.pages.fail2ban.stats.currently_banned'), (string) $this->currentlyBanned)
                ->description(__('filament.pages.fail2ban.stats.currently_banned_hint'))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($this->currentlyBanned > 0 ? 'danger' : 'success'),
            Stat::make(__('filament.pages.fail2ban.stats.total_failed'), (string) $this->totalFailedAttempts)
                ->description(__('filament.pages.fail2ban.stats.total_failed_hint'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($this->totalFailedAttempts > 0 ? 'warning' : 'success'),
            ...array_map(
                fn (array $jail): Stat => Stat::make($jail['name'], (string) $jail['currently_banned'])
                    ->description(__('filament.pages.fail2ban.stats.jail_hint', [
                        'total_banned' => $jail['total_banned'],
                        'currently_failed' => $jail['currently_failed'],
                        'total_failed' => $jail['total_failed'],
                    ]))
                    ->descriptionIcon($jail['journal_matches'] ? 'heroicon-m-command-line' : 'heroicon-m-document-text')
                    ->color($jail['currently_banned'] > 0 ? 'danger' : 'success'),
                $this->jails,
            ),
        ];
    }
}
