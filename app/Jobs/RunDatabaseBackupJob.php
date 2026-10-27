<?php

namespace App\Jobs;

use Aws\CommandInterface;
use Aws\Middleware;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RunDatabaseBackupJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    private const UPLOAD_PROGRESS_MIDDLEWARE_NAME = 'backup-upload-progress';

    private const UPLOAD_PROGRESS_LOG_INTERVAL_SECONDS = 5.0;

    public int $timeout;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('backups');

        $this->timeout = (int) config('backup-ops.job_timeout', 1200);
    }

    /**
     * Progress lines are captured live via spatie/laravel-backup's own
     * logger hook and written to cache (not tailed from a log file), so
     * the Livewire poller works correctly regardless of log file/config
     * caching state or whether the web and queue processes share a
     * filesystem (they may not, on a multi-server deployment) — Redis is
     * already the shared backbone for both.
     */
    public function handle(): void
    {
        $uuid = $this->job?->uuid();
        $cacheKey = $uuid !== null ? self::progressCacheKey($uuid) : null;
        $uploadedBytesCacheKey = $uuid !== null ? self::uploadedBytesCacheKey($uuid) : null;

        if ($cacheKey !== null) {
            Cache::put($cacheKey, '', now()->addHours(2));

            backupLogger()->onMessage(function (string $level, string $message) use ($cacheKey): void {
                $line = Cache::get($cacheKey, '')."[{$level}] {$message}\n";
                Cache::put($cacheKey, $line, now()->addHours(2));
            });
        }

        $removeUploadProgressMiddleware = $this->instrumentS3UploadProgress($uploadedBytesCacheKey);

        try {
            $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

            if ($exitCode !== 0) {
                $output = trim(Artisan::output());

                throw new RuntimeException(
                    $output !== ''
                        ? "backup:run exited with code {$exitCode}: {$output}"
                        : "backup:run exited with code {$exitCode}."
                );
            }
        } finally {
            if ($removeUploadProgressMiddleware !== null) {
                $removeUploadProgressMiddleware();
            }

            backupLogger()->clearListeners();
        }
    }

    // Both cache keys are built from the job's payload uuid() — matching
    // Croustibat\FilamentJobsMonitor\Models\QueueMonitor::getJobId(), which
    // reads payload()['uuid'] rather than getJobId() (a separate,
    // connection-specific ID — e.g. RedisJob's getJobId() returns the
    // Redis-only payload 'id' field, a different value from 'uuid'). Using
    // the same value QueueMonitor stores is what lets the dashboard
    // correlate its cache keys with a QueueMonitor row after a page reload.
    public static function progressCacheKey(string $jobId): string
    {
        return 'backup-progress:'.$jobId;
    }

    public static function uploadedBytesCacheKey(string $jobId): string
    {
        return 'backup-uploaded-bytes:'.$jobId;
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $value, $units[$unitIndex]);
    }

    /**
     * spatie/laravel-backup gives zero visibility during the actual S3
     * upload — BackupDestination::write() is a single blocking
     * writeStream() call with no progress hook of its own. This reaches
     * into the 's3' disk's own AWS SDK client (Illuminate\Filesystem\
     * AwsS3V3Adapter::getClient() returns the exact same Aws\S3\S3Client
     * instance spatie's write ends up using) and taps its request
     * lifecycle instead, routing byte counts through backupLogger() so
     * they flow into the same cache-based progress pipeline above.
     *
     * This can't be wired via config('filesystems.disks.s3') — a raw
     * Closure there can't survive `php artisan config:cache` — so it's
     * attached to the resolved client at runtime instead, and removed
     * again afterwards so it doesn't linger on the client instance for
     * whatever else this same long-lived queue worker processes next.
     *
     * Returns null (nothing to remove) if the 's3' disk isn't actually
     * backed by S3 (e.g. Storage::fake('s3') in tests).
     */
    private function instrumentS3UploadProgress(?string $uploadedBytesCacheKey): ?Closure
    {
        $disk = Storage::disk('s3');

        if (! $disk instanceof AwsS3V3Adapter) {
            return null;
        }

        $client = $disk->getClient();
        $context = $this->newUploadProgressContext($uploadedBytesCacheKey);

        // Deliberately a regular closure, not an arrow function: arrow
        // functions capture outer variables by value only, and that value
        // is re-copied fresh on every invocation — mutations made via the
        // by-reference $context parameter of attachUploadProgressReporter()
        // would silently vanish between commands, which is exactly why the
        // running total never advanced past the first part's byte count.
        // `use (&$context)` here is what makes the mutation actually stick
        // across every part this upload issues.
        $middleware = Middleware::mapCommand(
            function (CommandInterface $command) use (&$context): CommandInterface {
                return $this->attachUploadProgressReporter($command, $context);
            }
        );

        $client->getHandlerList()->appendBuild($middleware, self::UPLOAD_PROGRESS_MIDDLEWARE_NAME);

        // Removed by instance rather than by the name string: HandlerList's
        // own removeByName() only prunes the step list, it doesn't clear the
        // internal `named` index, so hasMiddleware() would keep reporting
        // true even after removal. Removing by instance clears both.
        return function () use ($client, $middleware): void {
            $client->getHandlerList()->remove($middleware);
        };
    }

    /**
     * @return array{lastLoggedAt: array<int|string, float>, countedParts: array<int|string, bool>, completedBytes: int, cacheKey: ?string}
     */
    private function newUploadProgressContext(?string $uploadedBytesCacheKey): array
    {
        return [
            'lastLoggedAt' => [],
            'countedParts' => [],
            'completedBytes' => 0,
            'cacheKey' => $uploadedBytesCacheKey,
        ];
    }

    /**
     * @param  array{lastLoggedAt: array<int|string, float>, countedParts: array<int|string, bool>, completedBytes: int, cacheKey: ?string}  $context
     *                                                                                                                                                 Shared by reference across every command this upload issues: `lastLoggedAt`
     *                                                                                                                                                 throttles per part (keyed by part number, or 0 for a single-shot PutObject),
     *                                                                                                                                                 `completedBytes`/`countedParts` accumulate a running total across parts.
     */
    private function attachUploadProgressReporter(CommandInterface $command, array &$context): CommandInterface
    {
        if (! in_array($command->getName(), ['PutObject', 'UploadPart'], true)) {
            return $command;
        }

        $partNumber = $command['PartNumber'] ?? null;

        $command['@http']['progress'] = function (
            int $downloadTotal,
            int $downloadedBytes,
            int $uploadTotal,
            int $uploadedBytes
        ) use (&$context, $partNumber): void {
            $this->reportUploadProgress($uploadTotal, $uploadedBytes, $partNumber, $context);
        };

        return $command;
    }

    /**
     * @param  array{lastLoggedAt: array<int|string, float>, countedParts: array<int|string, bool>, completedBytes: int, cacheKey: ?string}  $context
     */
    private function reportUploadProgress(int $uploadTotal, int $uploadedBytes, ?int $partNumber, array &$context): void
    {
        if ($uploadTotal <= 0) {
            return;
        }

        $key = $partNumber ?? 0;
        $isDone = $uploadedBytes >= $uploadTotal;
        $alreadyCounted = isset($context['countedParts'][$key]);

        // Guzzle/cURL can keep firing the progress callback for a part
        // after it's already fully uploaded (e.g. while waiting on the
        // response) — once a part has been counted as done, ignore any
        // further "done" ticks for it entirely rather than re-logging the
        // same 100% line forever.
        if ($isDone && $alreadyCounted) {
            return;
        }

        $now = microtime(true);

        if (! $isDone && $now - ($context['lastLoggedAt'][$key] ?? 0.0) < self::UPLOAD_PROGRESS_LOG_INTERVAL_SECONDS) {
            return;
        }

        $context['lastLoggedAt'][$key] = $now;

        // A part only counts toward the running total once, the first time
        // it's seen fully uploaded.
        if ($isDone && ! $alreadyCounted) {
            $context['countedParts'][$key] = true;
            $context['completedBytes'] += $uploadTotal;
        }

        $cumulativeBytes = $context['completedBytes'] + ($isDone ? 0 : $uploadedBytes);

        if ($context['cacheKey'] !== null) {
            Cache::put($context['cacheKey'], $cumulativeBytes, now()->addHours(2));
        }

        $label = $partNumber !== null ? "part {$partNumber}" : 'file';
        $percent = (int) round(($uploadedBytes / $uploadTotal) * 100);

        backupLogger()->info(sprintf(
            'Uploading %s to s3: %s of %s (%d%%) — %s transferred so far',
            $label,
            self::formatBytes($uploadedBytes),
            self::formatBytes($uploadTotal),
            $percent,
            self::formatBytes($cumulativeBytes)
        ));
    }
}
