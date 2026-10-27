<?php

namespace App\Filament\Pages;

use App\Jobs\RunDatabaseBackupJob;
use App\Models\User;
use BackedEnum;
use BadMethodCallException;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

class BackupsDashboard extends Page
{
    /**
     * Extra buffer added on top of the job's own configured timeout before a
     * still-unfinished QueueMonitor row is treated as abandoned rather than
     * running — accounts for the job's timeout enforcement not being
     * perfectly instantaneous.
     */
    private const ABANDONED_JOB_GRACE_SECONDS = 60;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'backups';

    protected string $view = 'filament.pages.backups-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_items.backups');
    }

    /**
     * @var array<int, array{filename: string, size: int, last_modified: int}>
     */
    public array $backups = [];

    public bool $configured = false;

    public ?string $statusError = null;

    public bool $showProgressModal = false;

    public ?string $activeBackupJobId = null;

    public string $backupProgressStatus = 'idle';

    public string $backupProgressLog = '';

    public int $backupUploadedBytes = 0;

    public bool $showLastBackupFailure = false;

    public ?string $lastBackupFailureFinishedAt = null;

    public ?string $lastBackupFailureMessage = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.system.backups.manage');
    }

    public function getTitle(): string
    {
        return __('filament.pages.backups.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.pages.backups.overview_body');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshBackups(notify: false);
        $this->reattachToRunningBackup();

        if (! $this->showProgressModal) {
            $this->detectLastFailedBackup();
        }
    }

    /**
     * Surfaces the most recent backup run's failure on page load if nothing
     * is currently running, so a run that failed while the dashboard wasn't
     * open (or after a refresh landed after it already finished) isn't
     * silently invisible — reattachToRunningBackup() only covers a run still
     * in progress, not one that already ended.
     */
    private function detectLastFailedBackup(): void
    {
        $monitor = QueueMonitor::query()
            ->where('name', RunDatabaseBackupJob::class)
            ->whereNotNull('finished_at')
            ->orderByDesc('id')
            ->first();

        if ($monitor === null || ! $monitor->failed) {
            return;
        }

        $this->showLastBackupFailure = true;
        $this->lastBackupFailureFinishedAt = $monitor->finished_at?->toDateTimeString();
        $this->lastBackupFailureMessage = $monitor->exception_message;
    }

    public function dismissLastBackupFailure(): void
    {
        $this->showLastBackupFailure = false;
    }

    /**
     * Reattach to a backup that's still running from before the page was
     * loaded/reloaded (e.g. the user refreshed mid-run) so the progress
     * modal reappears instead of silently losing track of it — the job
     * itself keeps running in the queue worker regardless of whether
     * anyone's watching, this just restores the UI's view of it.
     */
    private function reattachToRunningBackup(): void
    {
        $monitor = QueueMonitor::query()
            ->where('name', RunDatabaseBackupJob::class)
            ->whereNull('finished_at')
            ->orderByDesc('id')
            ->first();

        if ($monitor === null || $monitor->job_id === null || $this->isAbandoned($monitor)) {
            return;
        }

        $this->activeBackupJobId = $monitor->job_id;
        $this->backupProgressStatus = 'running';
        $this->backupProgressLog = (string) Cache::get(
            RunDatabaseBackupJob::progressCacheKey($monitor->job_id),
            ''
        );
        $this->backupUploadedBytes = (int) Cache::get(
            RunDatabaseBackupJob::uploadedBytesCacheKey($monitor->job_id),
            0
        );
        $this->showProgressModal = true;

        $this->dispatch('open-modal', id: 'backup-progress');
    }

    /**
     * A QueueMonitor row with no finished_at usually means "still running" —
     * but if the worker processing it was killed abruptly (SIGKILL, OOM, a
     * host/container restart) rather than hitting the job's own timeout
     * exception, finished_at never gets set at all, leaving a permanently
     * "running" zombie row that would otherwise fool the dashboard forever.
     * Once comfortably past the job's own configured timeout, it can no
     * longer be a legitimately in-progress run.
     */
    private function isAbandoned(QueueMonitor $monitor): bool
    {
        if ($monitor->started_at === null) {
            return false;
        }

        $timeoutSeconds = (int) config('backup-ops.job_timeout', 1200);

        return $monitor->started_at->addSeconds($timeoutSeconds + self::ABANDONED_JOB_GRACE_SECONDS)->isPast();
    }

    public function refreshBackups(bool $notify = true): void
    {
        $this->configured = $this->isConfigured();

        if (! $this->configured) {
            $this->backups = [];
            $this->statusError = null;

            return;
        }

        try {
            $this->backups = $this->listBackups();
            $this->statusError = null;

            if ($notify) {
                Notification::make()
                    ->title(__('filament.pages.backups.refresh_success'))
                    ->success()
                    ->send();
            }
        } catch (Throwable $throwable) {
            $this->backups = [];
            $this->statusError = $throwable->getMessage();

            Notification::make()
                ->title(__('filament.pages.backups.refresh_failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runBackup(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $this->activeBackupJobId = $this->pushBackupJobAndCaptureUuid();
        $this->backupProgressStatus = 'running';
        $this->backupProgressLog = '';
        $this->backupUploadedBytes = 0;
        $this->showProgressModal = true;

        $this->dispatch('open-modal', id: 'backup-progress');

        Notification::make()
            ->title(__('filament.pages.backups.run_started'))
            ->success()
            ->send();
    }

    /**
     * Dispatches the job and returns Laravel's own job UUID (the same value
     * Croustibat\FilamentJobsMonitor\Models\QueueMonitor::getJobId() reads
     * from the payload) so the dashboard's cache key and QueueMonitor lookups
     * agree.
     *
     * pushOn()'s own return value can't be used for this: on the redis queue
     * connection it returns a different, connection-specific ID (the raw
     * payload's 'id' field, via RedisJob::getJobId()) that does not match
     * QueueMonitor's job_id column. Queue::createPayloadUsing() is Laravel's
     * documented extension point for observing the payload as it's built, so
     * it's used here purely to read back the 'uuid' field already being
     * generated for every queued job, not to mutate it.
     */
    private function pushBackupJobAndCaptureUuid(): ?string
    {
        $uuid = null;

        try {
            Queue::createPayloadUsing(function (string $connection, string $queue, array $payload) use (&$uuid): array {
                $uuid = $payload['uuid'] ?? null;

                return [];
            });
        } catch (BadMethodCallException) {
            // Queue::fake() (used in tests) doesn't support this hook. The
            // job still gets pushed below; we just can't correlate a cache
            // key with a QueueMonitor row until a real payload is created.
        }

        try {
            Queue::connection()->pushOn('backups', new RunDatabaseBackupJob);
        } finally {
            try {
                Queue::createPayloadUsing(null);
            } catch (BadMethodCallException) {
            }
        }

        return $uuid;
    }

    public function pollBackupProgress(): void
    {
        if ($this->activeBackupJobId === null) {
            return;
        }

        $this->backupProgressLog = (string) Cache::get(
            RunDatabaseBackupJob::progressCacheKey($this->activeBackupJobId),
            ''
        );

        $this->backupUploadedBytes = (int) Cache::get(
            RunDatabaseBackupJob::uploadedBytesCacheKey($this->activeBackupJobId),
            0
        );

        $monitor = QueueMonitor::query()->where('job_id', $this->activeBackupJobId)->first();

        if ($monitor === null) {
            $this->backupProgressStatus = 'running';

            return;
        }

        if ($monitor->finished_at === null) {
            $this->backupProgressStatus = $this->isAbandoned($monitor) ? 'failed' : 'running';

            return;
        }

        if ($monitor->failed) {
            $this->backupProgressStatus = 'failed';

            return;
        }

        $wasAlreadySuccess = $this->backupProgressStatus === 'success';
        $this->backupProgressStatus = 'success';

        if (! $wasAlreadySuccess) {
            $this->refreshBackups(notify: false);
        }
    }

    public function closeProgressModal(): void
    {
        $this->showProgressModal = false;

        $this->dispatch('close-modal', id: 'backup-progress');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runBackup')
                ->label(__('filament.pages.backups.run_action'))
                ->icon('heroicon-m-play')
                ->disabled(fn (): bool => ! $this->isConfigured() || $this->backupProgressStatus === 'running')
                ->action(fn () => $this->runBackup()),
            Action::make('refreshBackups')
                ->label(__('filament.pages.backups.refresh_action'))
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshBackups()),
        ];
    }

    /**
     * @return array{
     *     disk: string,
     *     bucket: ?string,
     *     region: ?string,
     *     backup_name: string,
     *     schedule_enabled: bool,
     *     daily_at: string,
     *     clean_at: string,
     *     alert_emails: array<int, string>,
     *     retention: array{
     *         keep_all_days: int,
     *         keep_daily_days: int,
     *         keep_weekly_weeks: int,
     *         keep_monthly_months: int,
     *         keep_yearly_years: int,
     *         max_storage_mb: ?int
     *     }
     * }
     */
    public function backupSettings(): array
    {
        return [
            'disk' => 's3',
            'bucket' => config('filesystems.disks.s3.bucket'),
            'region' => config('filesystems.disks.s3.region'),
            'backup_name' => (string) config('backup.backup.name'),
            'schedule_enabled' => (bool) config('backup-ops.schedule.enabled', true),
            'daily_at' => (string) config('backup-ops.schedule.daily_at', '01:30'),
            'clean_at' => (string) config('backup-ops.schedule.clean_at', '02:45'),
            'alert_emails' => config('backup-ops.alerts.emails', []),
            'retention' => [
                'keep_all_days' => (int) config('backup.cleanup.default_strategy.keep_all_backups_for_days'),
                'keep_daily_days' => (int) config('backup.cleanup.default_strategy.keep_daily_backups_for_days'),
                'keep_weekly_weeks' => (int) config('backup.cleanup.default_strategy.keep_weekly_backups_for_weeks'),
                'keep_monthly_months' => (int) config('backup.cleanup.default_strategy.keep_monthly_backups_for_months'),
                'keep_yearly_years' => (int) config('backup.cleanup.default_strategy.keep_yearly_backups_for_years'),
                'max_storage_mb' => config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than'),
            ],
        ];
    }

    private function isConfigured(): bool
    {
        return filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.bucket'));
    }

    /**
     * @return array<int, array{filename: string, size: int, last_modified: int}>
     */
    private function listBackups(): array
    {
        $disk = Storage::disk('s3');
        $backupName = (string) config('backup.backup.name');

        $files = collect($disk->files($backupName))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->map(fn (string $path): array => [
                'filename' => basename($path),
                'size' => (int) $disk->size($path),
                'last_modified' => (int) $disk->lastModified($path),
            ])
            ->sortByDesc('last_modified')
            ->values();

        return $files->all();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('system');
    }
}
