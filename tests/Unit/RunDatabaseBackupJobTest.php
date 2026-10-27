<?php

namespace Tests\Unit;

use App\Jobs\RunDatabaseBackupJob;
use Aws\Command;
use Aws\HandlerList;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Spatie\Backup\Support\BackupLogger;
use Tests\TestCase;

class RunDatabaseBackupJobTest extends TestCase
{
    public function test_it_is_queued_on_the_backups_queue(): void
    {
        $job = new RunDatabaseBackupJob;

        $this->assertSame('backups', $job->queue);
    }

    public function test_timeout_is_read_from_config(): void
    {
        config()->set('backup-ops.job_timeout', 2400);

        $job = new RunDatabaseBackupJob;

        $this->assertSame(2400, $job->timeout);
    }

    public function test_handle_succeeds_when_backup_run_exits_zero(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturn(0);

        (new RunDatabaseBackupJob)->handle();

        $this->addToAssertionCount(1);
    }

    public function test_handle_throws_when_backup_run_exits_nonzero(): void
    {
        // spatie/laravel-backup's backup:run catches its own exceptions and
        // returns a non-zero exit code rather than throwing, so the job must
        // check the exit code itself to be correctly marked as failed.
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturn(1);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Backup failed because: disk not reachable.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup failed because: disk not reachable.');

        (new RunDatabaseBackupJob)->handle();
    }

    public function test_handle_captures_backup_logger_messages_into_cache(): void
    {
        // Progress is captured live via spatie/laravel-backup's own logger
        // hook into cache, not tailed from a log file, so it works
        // regardless of log config/file state and doesn't require the web
        // and queue processes to share a filesystem.
        //
        // The cache key is keyed on the job's payload uuid() (matching
        // Croustibat\FilamentJobsMonitor\Models\QueueMonitor::getJobId()),
        // not getJobId() (a connection-specific ID that doesn't match it on
        // the redis queue driver), so the fake job here must carry a real
        // 'uuid' in its payload rather than FakeJob's default empty body.
        $jobId = 'test-uuid-123';
        $job = new RunDatabaseBackupJob;
        $job->job = $this->fakeJobWithUuid($jobId);

        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturnUsing(function (): int {
                backupLogger()->info('Starting backup...');
                backupLogger()->info('Dumping database db...');

                return 0;
            });

        $job->handle();

        $log = Cache::get(RunDatabaseBackupJob::progressCacheKey($jobId));

        $this->assertStringContainsString('Starting backup...', $log);
        $this->assertStringContainsString('Dumping database db...', $log);
    }

    public function test_handle_clears_the_logger_listener_after_running(): void
    {
        // Without this, listeners registered by a job would accumulate on
        // the shared BackupLogger singleton across every job a long-lived
        // queue worker processes.
        $job = new RunDatabaseBackupJob;
        $job->job = $this->fakeJobWithUuid('test-uuid-456');

        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturn(0);

        $job->handle();

        $reflection = new ReflectionProperty(BackupLogger::class, 'listeners');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->getValue(backupLogger()));
    }

    public function test_handle_ignores_a_job_with_no_recoverable_uuid(): void
    {
        // getJobId() (e.g. RedisJob's Redis-specific payload 'id' field) is
        // deliberately NOT used for the cache key, since it doesn't match
        // QueueMonitor.job_id. If uuid() can't be resolved at all, progress
        // simply isn't cached rather than being cached under the wrong key.
        $job = new RunDatabaseBackupJob;
        $job->job = new FakeJob;

        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturnUsing(function (): int {
                backupLogger()->info('Starting backup...');

                return 0;
            });

        $job->handle();

        $this->assertNull(Cache::get(RunDatabaseBackupJob::progressCacheKey('')));
    }

    public function test_handle_removes_upload_progress_middleware_after_running(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturn(0);

        (new RunDatabaseBackupJob)->handle();

        $handlerList = Storage::disk('s3')->getClient()->getHandlerList();

        $this->assertFalse($handlerList->hasMiddleware('backup-upload-progress'));
    }

    public function test_instrument_s3_upload_progress_returns_null_when_disk_is_faked(): void
    {
        Storage::fake('s3');

        $result = $this->callPrivateMethod(new RunDatabaseBackupJob, 'instrumentS3UploadProgress', [null]);

        $this->assertNull($result);
    }

    public function test_attach_upload_progress_reporter_only_instruments_relevant_commands(): void
    {
        $job = new RunDatabaseBackupJob;
        $context = $this->newUploadProgressContext();

        $uploadPartCommand = new Command('UploadPart', ['PartNumber' => 3]);
        $result = $this->callPrivateMethod($job, 'attachUploadProgressReporter', [$uploadPartCommand, &$context]);

        $this->assertIsCallable($result['@http']['progress'] ?? null);

        $irrelevantCommand = new Command('ListObjectsV2', []);
        $result = $this->callPrivateMethod($job, 'attachUploadProgressReporter', [$irrelevantCommand, &$context]);

        $this->assertArrayNotHasKey('progress', $result['@http']);
    }

    public function test_report_upload_progress_logs_a_line_and_throttles_repeated_calls(): void
    {
        $job = new RunDatabaseBackupJob;
        $context = $this->newUploadProgressContext();
        $messages = [];

        backupLogger()->onMessage(function (string $level, string $message) use (&$messages): void {
            $messages[] = $message;
        });

        try {
            $this->callPrivateMethod($job, 'reportUploadProgress', [1_048_576, 524_288, 2, &$context]);
            $this->callPrivateMethod($job, 'reportUploadProgress', [1_048_576, 600_000, 2, &$context]);

            $this->assertCount(1, $messages);
            $this->assertStringContainsString('part 2', $messages[0]);
            $this->assertStringContainsString('50%', $messages[0]);

            $this->callPrivateMethod($job, 'reportUploadProgress', [1_048_576, 1_048_576, 2, &$context]);

            $this->assertCount(2, $messages, 'A completed part should log even inside the throttle window.');
            $this->assertStringContainsString('100%', $messages[1]);
        } finally {
            backupLogger()->clearListeners();
        }
    }

    public function test_report_upload_progress_accumulates_a_running_total_across_parts(): void
    {
        $job = new RunDatabaseBackupJob;
        $context = $this->newUploadProgressContext();
        $messages = [];

        backupLogger()->onMessage(function (string $level, string $message) use (&$messages): void {
            $messages[] = $message;
        });

        try {
            // Part 1 finishes fully (counts once toward the running total).
            $this->callPrivateMethod($job, 'reportUploadProgress', [1_000_000, 1_000_000, 1, &$context]);
            $this->assertSame(1_000_000, $context['completedBytes']);

            // Guzzle/cURL can keep firing the progress callback for a part
            // after it's already fully uploaded — it must not be
            // double-counted, nor logged again.
            $this->callPrivateMethod($job, 'reportUploadProgress', [1_000_000, 1_000_000, 1, &$context]);
            $this->assertSame(1_000_000, $context['completedBytes']);
            $this->assertCount(1, $messages, 'A repeat 100% tick for an already-counted part must not log again.');

            // A second, still in-flight part contributes its partial bytes
            // on top of the first part's completed total.
            $this->callPrivateMethod($job, 'reportUploadProgress', [2_000_000, 500_000, 2, &$context]);
            $this->assertSame(1_000_000, $context['completedBytes']);
        } finally {
            backupLogger()->clearListeners();
        }
    }

    public function test_report_upload_progress_writes_the_running_total_to_cache(): void
    {
        $job = new RunDatabaseBackupJob;
        $cacheKey = 'test-uploaded-bytes-key';
        $context = $this->newUploadProgressContext($cacheKey);

        try {
            $this->callPrivateMethod($job, 'reportUploadProgress', [1_000_000, 1_000_000, 1, &$context]);
            $this->assertSame(1_000_000, Cache::get($cacheKey));

            $this->callPrivateMethod($job, 'reportUploadProgress', [2_000_000, 500_000, 2, &$context]);
            $this->assertSame(1_500_000, Cache::get($cacheKey));
        } finally {
            backupLogger()->clearListeners();
        }
    }

    public function test_instrument_s3_upload_progress_accumulates_across_separate_commands(): void
    {
        // Regression test for a real bug: the middleware was originally
        // wired with an arrow function, which captures outer variables by
        // value only — each invocation silently reset to the original
        // snapshot, so the running total never advanced past the first
        // part's byte count no matter how many parts actually completed.
        // This exercises the real middleware attached to the real S3
        // client's HandlerList, invoking it for two separate commands the
        // way the AWS SDK would for two multipart upload parts.
        $cacheKey = 'test-integration-uploaded-bytes';
        $job = new RunDatabaseBackupJob;

        $removeMiddleware = $this->callPrivateMethod($job, 'instrumentS3UploadProgress', [$cacheKey]);
        $this->assertNotNull($removeMiddleware);

        try {
            $handlerList = Storage::disk('s3')->getClient()->getHandlerList();

            $reflection = new ReflectionProperty(HandlerList::class, 'steps');
            $reflection->setAccessible(true);
            $buildStep = $reflection->getValue($handlerList)[HandlerList::BUILD] ?? [];

            $registered = array_values(array_filter(
                $buildStep,
                fn (array $tuple): bool => $tuple[1] === 'backup-upload-progress'
            ));

            $this->assertCount(1, $registered, 'Expected the upload progress middleware to be registered on the build step.');

            $middleware = $registered[0][0];
            $wrapped = $middleware(fn (Command $command): Command => $command);

            $commandOne = new Command('UploadPart', ['PartNumber' => 1]);
            $resultOne = $wrapped($commandOne);
            ($resultOne['@http']['progress'])(0, 0, 1_000_000, 1_000_000);

            $commandTwo = new Command('UploadPart', ['PartNumber' => 2]);
            $resultTwo = $wrapped($commandTwo);
            ($resultTwo['@http']['progress'])(0, 0, 500_000, 500_000);

            $this->assertSame(1_500_000, Cache::get($cacheKey));
        } finally {
            $removeMiddleware();
            backupLogger()->clearListeners();
        }
    }

    public function test_format_bytes_formats_human_readable_sizes(): void
    {
        $this->assertSame('512.0 B', RunDatabaseBackupJob::formatBytes(512));
        $this->assertSame('1.5 KB', RunDatabaseBackupJob::formatBytes(1536));
        $this->assertSame('1.5 GB', RunDatabaseBackupJob::formatBytes(1_610_612_736));
    }

    /**
     * @return array{lastLoggedAt: array<int|string, float>, countedParts: array<int|string, bool>, completedBytes: int, cacheKey: ?string}
     */
    private function newUploadProgressContext(?string $cacheKey = null): array
    {
        return $this->callPrivateMethod(new RunDatabaseBackupJob, 'newUploadProgressContext', [$cacheKey]);
    }

    private function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    private function fakeJobWithUuid(string $uuid): JobContract
    {
        return new class($uuid) extends FakeJob
        {
            public function __construct(private readonly string $uuid) {}

            public function getRawBody(): string
            {
                return json_encode(['uuid' => $this->uuid]);
            }
        };
    }
}
