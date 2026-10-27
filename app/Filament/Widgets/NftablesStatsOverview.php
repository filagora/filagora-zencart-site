<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NftablesStatsOverview extends StatsOverviewWidget
{
    public int $tableCount = 0;

    /**
     * @var array<int, string>
     */
    public array $tables = [];

    public int $chainCount = 0;

    /**
     * @var array<int, string>
     */
    public array $chains = [];

    public int $ruleCount = 0;

    public int $setCount = 0;

    /**
     * @var array<int, array{
     *     key: string,
     *     family: string,
     *     table: string,
     *     name: string,
     *     type: ?string,
     *     flags: array<int, string>,
     *     element_count: int,
     *     elements: array<int, array{
     *         key: string,
     *         value: string,
     *         removable: bool
     *     }>
     * }>
     */
    public array $sets = [];

    protected function getStats(): array
    {
        return [
            Stat::make(__('filament.pages.nftables.stats.tables'), (string) $this->tableCount)
                ->description($this->describeWithSummary(
                    __('filament.pages.nftables.stats.tables_hint'),
                    __('filament.pages.nftables.stats.tables_summary', [
                        'items' => $this->formatSummaryItems($this->tables),
                    ]),
                    $this->tables,
                ))
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('gray'),
            Stat::make(__('filament.pages.nftables.stats.chains'), (string) $this->chainCount)
                ->description($this->describeWithSummary(
                    __('filament.pages.nftables.stats.chains_hint'),
                    __('filament.pages.nftables.stats.chains_summary', [
                        'items' => $this->formatSummaryItems($this->chains),
                    ]),
                    $this->chains,
                ))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),
            Stat::make(__('filament.pages.nftables.stats.rules'), (string) $this->ruleCount)
                ->description(__('filament.pages.nftables.stats.rules_hint'))
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('gray'),
            Stat::make(__('filament.pages.nftables.stats.sets'), (string) $this->setCount)
                ->description($this->describeWithSummary(
                    __('filament.pages.nftables.stats.sets_hint'),
                    __('filament.pages.nftables.stats.sets_summary', [
                        'items' => $this->formatSummaryItems(array_column($this->sets, 'name')),
                    ]),
                    $this->sets,
                ))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($this->setCount > 0 ? 'warning' : 'success'),
            ...array_map(
                fn (array $set): Stat => Stat::make($set['name'], (string) $set['element_count'])
                    ->description(__('filament.pages.nftables.stats.set_hint', [
                        'family' => $set['family'],
                        'table' => $set['table'],
                        'type' => $set['type'] ?: __('filament.pages.nftables.unknown_type'),
                    ]))
                    ->descriptionIcon('heroicon-m-globe-alt')
                    ->color($set['element_count'] > 0 ? 'danger' : 'success'),
                $this->sets,
            ),
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function describeWithSummary(string $hint, string $summary, array $items): string
    {
        if ($items === []) {
            return $hint;
        }

        return $hint.' '.$summary;
    }

    /**
     * @param  array<int, string>  $items
     */
    private function formatSummaryItems(array $items): string
    {
        $items = array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $items,
        )));

        $visibleItems = array_slice($items, 0, 3);
        $summary = implode(', ', $visibleItems);
        $remaining = count($items) - count($visibleItems);

        if ($remaining <= 0) {
            return $summary;
        }

        return __('filament.pages.nftables.stats.summary_more', [
            'items' => $summary,
            'count' => $remaining,
        ]);
    }
}
