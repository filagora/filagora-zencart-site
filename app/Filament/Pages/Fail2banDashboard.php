<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Fail2banStatsOverview;
use App\Models\User;
use App\Services\Fail2banService;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\Services\AuthorizationService;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

class Fail2banDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'fail2ban';

    protected string $view = 'filament.pages.fail2ban-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.fail2ban');
    }

    /**
     * @var array{
     *     enabled: bool,
     *     note: ?string,
     *     jail_count: int,
     *     jails: array<int, array{
     *         name: string,
     *         currently_failed: int,
     *         total_failed: int,
     *         currently_banned: int,
     *         total_banned: int,
     *         banned_ips: array<int, string>,
     *         log_files: array<int, string>,
     *         journal_matches: ?string,
     *         raw_output: string
     *     }>
     * }
     */
    public array $status = [
        'enabled' => false,
        'note' => null,
        'jail_count' => 0,
        'jails' => [],
    ];

    public ?string $statusError = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.system.fail2ban.view');
    }

    public function getTitle(): string
    {
        return __('filament.pages.fail2ban.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.pages.fail2ban.overview_body');
    }

    public function mount(Fail2banService $fail2banService): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountInteractsWithTable();
        $this->refreshStatus($fail2banService, notify: false);
    }

    public function refreshStatus(?Fail2banService $fail2banService = null, bool $notify = true): void
    {
        $service = $fail2banService ?? app(Fail2banService::class);

        try {
            $this->status = $service->getStatusSnapshot();
            $this->statusError = null;
            $this->syncActiveBansTablePage();

            if ($notify) {
                Notification::make()
                    ->title(__('filament.pages.fail2ban.refresh_success'))
                    ->success()
                    ->send();
            }
        } catch (Throwable $throwable) {
            $this->statusError = $throwable->getMessage();
            $this->status = [
                'enabled' => $service->isEnabled(),
                'note' => $service->note(),
                'jail_count' => 0,
                'jails' => [],
            ];

            Notification::make()
                ->title(__('filament.pages.fail2ban.refresh_failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function unbanSelectedJailIp(string $jail, string $ip): void
    {
        $service = app(Fail2banService::class);

        try {
            $service->unban($jail, $ip);

            Notification::make()
                ->title(__('filament.pages.fail2ban.unban_success'))
                ->body(__('filament.pages.fail2ban.unban_success_body', [
                    'ip' => $ip,
                    'jail' => $jail,
                ]))
                ->success()
                ->send();

            $this->refreshStatus($service, notify: false);
        } catch (Throwable $throwable) {
            Notification::make()
                ->title(__('filament.pages.fail2ban.unban_failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function totalCurrentlyBanned(): int
    {
        return array_sum(array_map(
            static fn (array $jail): int => (int) Arr::get($jail, 'currently_banned', 0),
            $this->status['jails']
        ));
    }

    public function totalFailedAttempts(): int
    {
        return array_sum(array_map(
            static fn (array $jail): int => (int) Arr::get($jail, 'total_failed', 0),
            $this->status['jails']
        ));
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Fail2banStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    public function getWidgetData(): array
    {
        return [
            'jailCount' => (int) ($this->status['jail_count'] ?? 0),
            'currentlyBanned' => $this->totalCurrentlyBanned(),
            'totalFailedAttempts' => $this->totalFailedAttempts(),
            'jails' => $this->status['jails'] ?? [],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament.pages.fail2ban.active_bans_heading'))
            ->description(__('filament.pages.fail2ban.active_bans_table_body'))
            ->records(fn (): LengthAwarePaginator => $this->getActiveBansTableRecords())
            ->columns([
                TextColumn::make('jail')
                    ->label(__('filament.pages.fail2ban.unban_jail_label'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ip')
                    ->label(__('filament.pages.fail2ban.unban_ip_label'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('source')
                    ->label(__('filament.pages.fail2ban.source_label'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source_detail')
                    ->label(__('filament.pages.fail2ban.source_detail_label'))
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (array $record): string => $record['source_detail']),
            ])
            ->recordActions([
                TableAction::make('useInUnbanForm')
                    ->label(__('filament.pages.fail2ban.unban_action'))
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        $this->unbanSelectedJailIp((string) $record['jail'], (string) $record['ip']);
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading(__('filament.pages.fail2ban.no_active_bans'))
            ->emptyStateDescription(__('filament.pages.fail2ban.active_bans_table_empty_body'))
            ->striped();
    }

    private function getActiveBansTableRecords(): LengthAwarePaginator
    {
        $recordsPerPage = (int) ($this->getTableRecordsPerPage() ?: 10);
        $page = max(1, (int) $this->getTablePage());
        $search = trim((string) $this->getTableSearch());
        [$sortColumn, $sortDirection] = [$this->getTableSortColumn(), $this->getTableSortDirection()];

        $records = $this->activeBanRecords();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $records = $records->filter(
                fn (array $record): bool => str_contains(mb_strtolower($record['ip']), $needle)
                    || str_contains(mb_strtolower($record['jail']), $needle)
                    || str_contains(mb_strtolower($record['source']), $needle)
                    || str_contains(mb_strtolower($record['source_detail']), $needle)
            )->values();
        }

        if (in_array($sortColumn, ['ip', 'jail', 'source'], true)) {
            $descending = $sortDirection === 'desc';

            $records = $records->sort(
                fn (array $left, array $right): int => is_numeric($left[$sortColumn]) && is_numeric($right[$sortColumn])
                    ? (((int) $left[$sortColumn] <=> (int) $right[$sortColumn]) * ($descending ? -1 : 1))
                    : (strnatcasecmp((string) $left[$sortColumn], (string) $right[$sortColumn]) * ($descending ? -1 : 1))
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
     *     key:string,
     *     jail:string,
     *     ip:string,
     *     source:string,
     *     source_detail:string
     * }>
     */
    private function activeBanRecords(): Collection
    {
        return collect($this->status['jails'])
            ->flatMap(function (array $jail): array {
                $source = filled($jail['journal_matches'] ?? null)
                    ? __('filament.pages.fail2ban.source_journal')
                    : __('filament.pages.fail2ban.source_log_file');
                $sourceDetail = filled($jail['journal_matches'] ?? null)
                    ? (string) $jail['journal_matches']
                    : (string) collect($jail['log_files'] ?? [])->first();

                return collect($jail['banned_ips'])
                    ->values()
                    ->map(fn (string $ip, int $index): array => [
                        'key' => "{$jail['name']}:{$index}:{$ip}",
                        'jail' => (string) $jail['name'],
                        'ip' => $ip,
                        'source' => $source,
                        'source_detail' => $sourceDetail,
                    ])
                    ->all();
            })
            ->values();
    }

    private function syncActiveBansTablePage(): void
    {
        if (! isset($this->table)) {
            return;
        }

        $recordsPerPage = (int) ($this->getTableRecordsPerPage() ?: 10);
        $lastPage = max(1, (int) ceil($this->activeBanRecords()->count() / max(1, $recordsPerPage)));
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
