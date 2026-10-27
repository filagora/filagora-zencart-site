<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupsDashboard;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\User;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class BackupsDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_resource_requires_backups_entitlement(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');

        $this->actingAs($user)
            ->get('/dashboard/backups')
            ->assertForbidden();
    }

    public function test_regular_user_is_redirected_from_backups_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/backups')
            ->assertRedirect(route('home'));
    }

    public function test_staff_with_entitlement_sees_not_configured_state(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('filesystems.disks.s3.key', null);
        config()->set('filesystems.disks.s3.bucket', null);

        $this->actingAs($user)
            ->get('/dashboard/backups')
            ->assertOk()
            ->assertSee('S3 storage is not configured.');
    }

    public function test_dashboard_surfaces_current_backup_settings(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('filesystems.disks.s3.bucket', 'settings-test-bucket');
        config()->set('filesystems.disks.s3.region', 'eu-west-1');
        config()->set('backup-ops.schedule.enabled', true);
        config()->set('backup-ops.schedule.daily_at', '01:30');
        config()->set('backup-ops.alerts.emails', ['ops@example.test']);

        $this->actingAs($user)
            ->get('/dashboard/backups')
            ->assertOk()
            ->assertSee('settings-test-bucket')
            ->assertSee('eu-west-1')
            ->assertSee('01:30')
            ->assertSee('ops@example.test');
    }

    public function test_dashboard_lists_existing_backups_when_configured(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('filesystems.disks.s3.key', 'test-key');
        config()->set('filesystems.disks.s3.bucket', 'test-bucket');
        Storage::fake('s3');

        $backupName = (string) config('backup.backup.name');
        Storage::disk('s3')->put($backupName.'/2026-07-20-01-30-00.zip', 'fake-backup-contents');

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSee('2026-07-20-01-30-00.zip');
    }

    public function test_mount_reattaches_to_a_still_running_backup(): void
    {
        Cache::put(
            RunDatabaseBackupJob::progressCacheKey('reattach-job-id'),
            "[info] Starting backup...\n[info] Dumping database db...\n"
        );
        Cache::put(RunDatabaseBackupJob::uploadedBytesCacheKey('reattach-job-id'), 10_485_760);

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'reattach-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('activeBackupJobId', 'reattach-job-id')
            ->assertSet('backupProgressStatus', 'running')
            ->assertSet('showProgressModal', true)
            ->assertSet('backupProgressLog', "[info] Starting backup...\n[info] Dumping database db...\n")
            ->assertSet('backupUploadedBytes', 10_485_760)
            ->assertDispatched('open-modal', id: 'backup-progress');
    }

    public function test_mount_does_not_reattach_to_a_finished_backup(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'finished-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'failed' => false,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('activeBackupJobId', null)
            ->assertSet('backupProgressStatus', 'idle')
            ->assertSet('showProgressModal', false);
    }

    public function test_mount_does_not_reattach_to_an_abandoned_backup(): void
    {
        // A worker killed abruptly (SIGKILL, OOM, host/container restart)
        // never gets the chance to set finished_at, leaving a permanent
        // "running" zombie row well past the job's own configured timeout.
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('backup-ops.job_timeout', 1200);

        QueueMonitor::query()->create([
            'job_id' => 'abandoned-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subSeconds(1200 + 61),
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('activeBackupJobId', null)
            ->assertSet('backupProgressStatus', 'idle')
            ->assertSet('showProgressModal', false);
    }

    public function test_poll_backup_progress_treats_an_abandoned_job_as_failed(): void
    {
        config()->set('backup-ops.job_timeout', 1200);

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'abandoned-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subSeconds(1200 + 61),
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->set('activeBackupJobId', 'abandoned-job-id')
            ->call('pollBackupProgress')
            ->assertSet('backupProgressStatus', 'failed');
    }

    public function test_mount_surfaces_the_last_failed_backup_when_nothing_is_running(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'failed-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinutes(21),
            'finished_at' => now()->subMinute(),
            'failed' => true,
            'exception_message' => 'RunDatabaseBackupJob has timed out.',
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('showLastBackupFailure', true)
            ->assertSet('lastBackupFailureMessage', 'RunDatabaseBackupJob has timed out.')
            ->assertSet('showProgressModal', false);
    }

    public function test_mount_does_not_surface_a_successful_last_backup(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'succeeded-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'failed' => false,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('showLastBackupFailure', false);
    }

    public function test_mount_does_not_surface_last_failure_while_a_backup_is_running(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'older-failed-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(10),
            'failed' => true,
            'exception_message' => 'RunDatabaseBackupJob has timed out.',
        ]);

        QueueMonitor::query()->create([
            'job_id' => 'currently-running-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('showProgressModal', true)
            ->assertSet('showLastBackupFailure', false);
    }

    public function test_dismiss_last_backup_failure_hides_the_banner(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'failed-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinutes(21),
            'finished_at' => now()->subMinute(),
            'failed' => true,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->assertSet('showLastBackupFailure', true)
            ->call('dismissLastBackupFailure')
            ->assertSet('showLastBackupFailure', false);
    }

    public function test_run_backup_dispatches_job_to_the_backups_queue_and_opens_progress_modal(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('filesystems.disks.s3.key', 'test-key');
        config()->set('filesystems.disks.s3.bucket', 'test-bucket');

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->call('runBackup')
            ->assertSet('backupProgressStatus', 'running')
            ->assertSet('showProgressModal', true)
            ->assertNotified(__('filament.pages.backups.run_started'))
            ->assertDispatched('open-modal', id: 'backup-progress');

        Queue::assertPushedOn('backups', RunDatabaseBackupJob::class);
    }

    public function test_run_backup_does_nothing_when_not_configured(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('filesystems.disks.s3.key', null);
        config()->set('filesystems.disks.s3.bucket', null);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->call('runBackup')
            ->assertSet('backupProgressStatus', 'idle');

        Queue::assertNothingPushed();
    }

    public function test_poll_backup_progress_reflects_running_job_and_cached_log(): void
    {
        Cache::put(
            RunDatabaseBackupJob::progressCacheKey('test-job-id'),
            "[info] Starting backup...\n[info] Dumping database db...\n"
        );
        Cache::put(RunDatabaseBackupJob::uploadedBytesCacheKey('test-job-id'), 5_242_880);

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'test-job-id',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now(),
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->set('activeBackupJobId', 'test-job-id')
            ->call('pollBackupProgress')
            ->assertSet('backupProgressStatus', 'running')
            ->assertSet('backupProgressLog', "[info] Starting backup...\n[info] Dumping database db...\n")
            ->assertSet('backupUploadedBytes', 5_242_880);
    }

    public function test_poll_backup_progress_reflects_failed_job(): void
    {
        Cache::put(
            RunDatabaseBackupJob::progressCacheKey('test-job-id-failed'),
            "[info] Starting backup...\n[error] Backup failed because: disk not reachable.\n"
        );

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        QueueMonitor::query()->create([
            'job_id' => 'test-job-id-failed',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'failed' => true,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->set('activeBackupJobId', 'test-job-id-failed')
            ->call('pollBackupProgress')
            ->assertSet('backupProgressStatus', 'failed');
    }

    public function test_poll_backup_progress_refreshes_backup_list_on_success(): void
    {
        Cache::put(
            RunDatabaseBackupJob::progressCacheKey('test-job-id-success'),
            "[info] Starting backup...\n[info] Copying zip to disk named s3...\n"
        );

        $user = User::factory()->create();
        $this->grant($user, 'operator.dashboard.access');
        $this->grant($user, 'operator.system.backups.manage');

        config()->set('filesystems.disks.s3.key', 'test-key');
        config()->set('filesystems.disks.s3.bucket', 'test-bucket');
        Storage::fake('s3');

        $backupName = (string) config('backup.backup.name');
        Storage::disk('s3')->put($backupName.'/2026-07-21-01-30-00.zip', 'fake-backup-contents');

        QueueMonitor::query()->create([
            'job_id' => 'test-job-id-success',
            'name' => RunDatabaseBackupJob::class,
            'queue' => 'backups',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'failed' => false,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));

        Livewire::test(BackupsDashboard::class)
            ->set('activeBackupJobId', 'test-job-id-success')
            ->call('pollBackupProgress')
            ->assertSet('backupProgressStatus', 'success')
            ->assertSet('backups.0.filename', '2026-07-21-01-30-00.zip');
    }

    private function grant(User $user, string $entitlement): void
    {
        UserEntitlementGrant::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => $entitlement,
            'grant_type' => 'manual',
            'starts_at' => now()->subMinute(),
        ]);
    }
}
