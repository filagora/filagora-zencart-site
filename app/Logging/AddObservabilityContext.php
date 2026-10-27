<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class AddObservabilityContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            return $record->with(extra: array_merge($record->extra, [
                'environment' => (string) config('app.env'),
                'service' => (string) config('observability.service'),
                'release_id' => (string) config('observability.release.release_id'),
                'deployment_id' => (string) config('observability.release.deployment_id'),
                'commit_sha' => (string) config('observability.release.commit_sha'),
            ]));
        });
    }
}
