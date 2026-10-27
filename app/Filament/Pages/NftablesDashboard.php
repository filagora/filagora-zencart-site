<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NftablesStatsOverview;
use App\Models\User;
use App\Services\NftablesService;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

class NftablesDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'nftables';

    protected string $view = 'filament.pages.nftables-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.nftables');
    }

    /**
     * @var array{
     *     enabled: bool,
     *     note: ?string,
     *     table_count: int,
     *     tables: array<int, string>,
     *     chain_count: int,
     *     chains: array<int, string>,
     *     rule_count: int,
     *     set_count: int,
     *     sets: array<int, array{
     *         key: string,
     *         family: string,
     *         table: string,
     *         name: string,
     *         type: ?string,
     *         flags: array<int, string>,
     *         element_count: int,
     *         elements: array<int, array{
     *             key: string,
     *             value: string,
     *             removable: bool
     *         }>
     *     }>
     * }
     */
    public array $status = [
        'enabled' => false,
        'note' => null,
        'table_count' => 0,
        'tables' => [],
        'chain_count' => 0,
        'chains' => [],
        'rule_count' => 0,
        'set_count' => 0,
        'sets' => [],
    ];

    public ?string $statusError = null;

    public ?string $activeTableFilter = null;

    public ?string $activeSetFilter = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.system.nftables.view');
    }

    public function getTitle(): string
    {
        return __('filament.pages.nftables.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.pages.nftables.overview_body');
    }

    public function mount(NftablesService $nftablesService): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountInteractsWithTable();
        $this->refreshStatus($nftablesService, notify: false);
    }

    public function refreshStatus(?NftablesService $nftablesService = null, bool $notify = true): void
    {
        $service = $nftablesService ?? app(NftablesService::class);

        try {
            $this->status = $service->getStatusSnapshot();
            $this->statusError = null;
            $this->syncSetElementsTablePage();

            if ($notify) {
                Notification::make()
                    ->title(__('filament.pages.nftables.refresh_success'))
                    ->success()
                    ->send();
            }
        } catch (Throwable $throwable) {
            $this->statusError = $throwable->getMessage();
            $this->status = [
                'enabled' => $service->isEnabled(),
                'note' => $service->note(),
                'table_count' => 0,
                'tables' => [],
                'chain_count' => 0,
                'chains' => [],
                'rule_count' => 0,
                'set_count' => 0,
                'sets' => [],
            ];

            Notification::make()
                ->title(__('filament.pages.nftables.refresh_failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeSetElement(string $family, string $table, string $set, string $element): void
    {
        $service = app(NftablesService::class);

        try {
            $service->removeElement($family, $table, $set, $element);

            Notification::make()
                ->title(__('filament.pages.nftables.remove_success'))
                ->body(__('filament.pages.nftables.remove_success_body', [
                    'element' => $element,
                    'set' => $set,
                    'table' => $table,
                    'family' => $family,
                ]))
                ->success()
                ->send();

            $this->refreshStatus($service, notify: false);
        } catch (Throwable $throwable) {
            Notification::make()
                ->title(__('filament.pages.nftables.remove_failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function applyTableFilter(string $identifier): void
    {
        $this->activeTableFilter = trim($identifier) !== '' ? trim($identifier) : null;
        $this->setPage(1);
    }

    public function applySetFilter(string $key): void
    {
        $this->activeSetFilter = trim($key) !== '' ? trim($key) : null;
        $this->setPage(1);
    }

    public function clearQuickFilters(): void
    {
        $this->activeTableFilter = null;
        $this->activeSetFilter = null;
        $this->setPage(1);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            NftablesStatsOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStatus')
                ->label(__('filament.pages.nftables.refresh_action'))
                ->icon('heroicon-m-arrow-path')
                ->action(function (): void {
                    $this->refreshStatus();
                }),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    public function getWidgetData(): array
    {
        return [
            'tableCount' => (int) ($this->status['table_count'] ?? 0),
            'tables' => $this->status['tables'] ?? [],
            'chainCount' => (int) ($this->status['chain_count'] ?? 0),
            'chains' => $this->status['chains'] ?? [],
            'ruleCount' => (int) ($this->status['rule_count'] ?? 0),
            'setCount' => (int) ($this->status['set_count'] ?? 0),
            'sets' => $this->status['sets'] ?? [],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament.pages.nftables.elements_heading'))
            ->description(__('filament.pages.nftables.elements_table_body'))
            ->records(fn (): LengthAwarePaginator => $this->getSetElementTableRecords())
            ->columns([
                TextColumn::make('family')
                    ->label(__('filament.pages.nftables.family_label'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('table')
                    ->label(__('filament.pages.nftables.table_label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('set')
                    ->label(__('filament.pages.nftables.set_label'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('filament.pages.nftables.type_label'))
                    ->toggleable()
                    ->searchable()
                    ->placeholder(__('filament.pages.nftables.unknown_type')),
                TextColumn::make('element')
                    ->label(__('filament.pages.nftables.element_label'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),
            ])
            ->recordActions([
                TableAction::make('removeElement')
                    ->label(__('filament.pages.nftables.remove_action'))
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->visible(fn (array $record): bool => (bool) $record['removable'])
                    ->action(function (array $record): void {
                        $this->removeSetElement(
                            (string) $record['family'],
                            (string) $record['table'],
                            (string) $record['set'],
                            (string) $record['element'],
                        );
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading(__('filament.pages.nftables.no_set_elements'))
            ->emptyStateDescription(__('filament.pages.nftables.elements_table_empty_body'))
            ->striped();
    }

    private function getSetElementTableRecords(): LengthAwarePaginator
    {
        $recordsPerPage = (int) ($this->getTableRecordsPerPage() ?: 10);
        $page = max(1, (int) $this->getTablePage());
        $search = trim((string) $this->getTableSearch());
        [$sortColumn, $sortDirection] = [$this->getTableSortColumn(), $this->getTableSortDirection()];

        $records = $this->filteredSetElementRecords();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $records = $records->filter(
                fn (array $record): bool => str_contains(mb_strtolower($record['family']), $needle)
                    || str_contains(mb_strtolower($record['table']), $needle)
                    || str_contains(mb_strtolower($record['set']), $needle)
                    || str_contains(mb_strtolower($record['type']), $needle)
                    || str_contains(mb_strtolower($record['element']), $needle)
            )->values();
        }

        if (in_array($sortColumn, ['family', 'table', 'set', 'type', 'element'], true)) {
            $descending = $sortDirection === 'desc';

            $records = $records->sort(
                fn (array $left, array $right): int => strnatcasecmp((string) $left[$sortColumn], (string) $right[$sortColumn]) * ($descending ? -1 : 1)
            )->values();
        }

        $total = $records->count();
        $lastPage = max(1, (int) ceil($total / max(1, $recordsPerPage)));
        $page = min($page, $lastPage);

        if ($page !== (int) $this->getTablePage()) {
            $this->setPage($page);
        }

        return new LengthAwarePaginator(
            items: $records->forPage($page, $recordsPerPage)->values(),
            total: $total,
            perPage: $recordsPerPage,
            currentPage: $page,
        );
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     family: string,
     *     table: string,
     *     table_key: string,
     *     set: string,
     *     set_key: string,
     *     type: string,
     *     element: string,
     *     removable: bool
     * }>
     */
    private function setElementRecords(): Collection
    {
        return collect($this->status['sets'] ?? [])
            ->flatMap(function (array $set): array {
                return collect($set['elements'] ?? [])
                    ->map(fn (array $element): array => [
                        'key' => (string) $element['key'],
                        'family' => (string) $set['family'],
                        'table' => (string) $set['table'],
                        'table_key' => sprintf('%s/%s', (string) $set['family'], (string) $set['table']),
                        'set' => (string) $set['name'],
                        'set_key' => (string) $set['key'],
                        'type' => (string) ($set['type'] ?? ''),
                        'element' => (string) $element['value'],
                        'removable' => (bool) $element['removable'],
                    ])
                    ->all();
            })
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     family: string,
     *     table: string,
     *     set: string,
     *     set_key: string,
     *     table_key: string,
     *     type: string,
     *     element: string,
     *     removable: bool
     * }>
     */
    private function filteredSetElementRecords(): Collection
    {
        $records = $this->setElementRecords();

        if ($this->activeTableFilter !== null) {
            $records = $records
                ->where('table_key', $this->activeTableFilter)
                ->values();
        }

        if ($this->activeSetFilter !== null) {
            $records = $records
                ->where('set_key', $this->activeSetFilter)
                ->values();
        }

        return $records;
    }

    private function syncSetElementsTablePage(): void
    {
        if (! isset($this->table)) {
            return;
        }

        $recordsPerPage = (int) ($this->getTableRecordsPerPage() ?: 10);
        $lastPage = max(1, (int) ceil($this->filteredSetElementRecords()->count() / max(1, $recordsPerPage)));
        $currentPage = max(1, (int) $this->getTablePage());

        if ($currentPage > $lastPage) {
            $this->setPage($lastPage);
        }
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('system');
    }
}
