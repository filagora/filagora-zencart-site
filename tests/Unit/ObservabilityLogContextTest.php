<?php

namespace Tests\Unit;

use App\Logging\AddObservabilityContext;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class ObservabilityLogContextTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_it_adds_static_observability_fields_to_log_records(): void
    {
        config()->set('app.env', 'testing');
        config()->set('observability.service', 'lara-forum');
        config()->set('observability.release.release_id', 'release-42');
        config()->set('observability.release.deployment_id', 'deployment-43');
        config()->set('observability.release.commit_sha', str_repeat('a', 40));

        $handler = new TestHandler;
        $logger = new IlluminateLogger(new MonologLogger('observability-test', [$handler]));

        (new AddObservabilityContext)($logger);
        $logger->info('test-message');

        $record = $handler->getRecords()[0];

        $this->assertSame('testing', $record->extra['environment']);
        $this->assertSame('lara-forum', $record->extra['service']);
        $this->assertSame('release-42', $record->extra['release_id']);
        $this->assertSame('deployment-43', $record->extra['deployment_id']);
        $this->assertSame(str_repeat('a', 40), $record->extra['commit_sha']);
    }
}
