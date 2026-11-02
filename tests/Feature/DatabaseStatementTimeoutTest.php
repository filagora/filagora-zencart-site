<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Covers the session-scoped MariaDB statement bound applied by
 * AppServiceProvider::configureDatabaseStatementTimeout().
 *
 * PHP's max_execution_time cannot see time blocked on a database socket, which
 * is how the FPM pool saturated on 28 Aug 2026. This bound is what covers that
 * case, so the console exemption and the driver guard both need holding down:
 * migrations legitimately run longer than any web request should.
 */
class DatabaseStatementTimeoutTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setRunningInConsole(true);

        parent::tearDown();
    }

    public function test_it_defaults_to_fifteen_seconds(): void
    {
        $this->assertSame(15, (int) config('database.max_statement_time'));
    }

    public function test_it_bounds_statement_time_on_a_web_request(): void
    {
        $connection = $this->mysqlConnection();
        $connection->shouldReceive('statement')
            ->once()
            ->with('SET SESSION max_statement_time = 15');

        $this->bootTimeoutListener(runningInConsole: false, seconds: 15);

        Event::dispatch(new ConnectionEstablished($connection));
    }

    public function test_it_uses_the_configured_number_of_seconds(): void
    {
        $connection = $this->mysqlConnection();
        $connection->shouldReceive('statement')
            ->once()
            ->with('SET SESSION max_statement_time = 5');

        $this->bootTimeoutListener(runningInConsole: false, seconds: 5);

        Event::dispatch(new ConnectionEstablished($connection));
    }

    public function test_it_exempts_the_console_so_migrations_are_not_aborted(): void
    {
        $connection = $this->mysqlConnection();
        $connection->shouldNotReceive('statement');

        $this->bootTimeoutListener(runningInConsole: true, seconds: 15);

        Event::dispatch(new ConnectionEstablished($connection));
    }

    public function test_it_is_disabled_when_set_to_zero(): void
    {
        $connection = $this->mysqlConnection();
        $connection->shouldNotReceive('statement');

        $this->bootTimeoutListener(runningInConsole: false, seconds: 0);

        Event::dispatch(new ConnectionEstablished($connection));
    }

    public function test_it_leaves_non_mysql_drivers_alone(): void
    {
        $connection = $this->connectionWithDriver('sqlite');
        $connection->shouldNotReceive('statement');

        $this->bootTimeoutListener(runningInConsole: false, seconds: 15);

        Event::dispatch(new ConnectionEstablished($connection));
    }

    /**
     * Register the listener the way AppServiceProvider::boot() would, with the
     * console flag and configured bound both under the test's control.
     */
    private function bootTimeoutListener(bool $runningInConsole, int $seconds): void
    {
        config(['database.max_statement_time' => $seconds]);
        $this->setRunningInConsole($runningInConsole);

        $provider = new AppServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'configureDatabaseStatementTimeout');
        $method->invoke($provider);
    }

    private function setRunningInConsole(bool $runningInConsole): void
    {
        (new ReflectionProperty($this->app, 'isRunningInConsole'))
            ->setValue($this->app, $runningInConsole);
    }

    private function mysqlConnection(): Connection&MockInterface
    {
        return $this->connectionWithDriver('mysql');
    }

    private function connectionWithDriver(string $driver): Connection&MockInterface
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getName')->andReturn($driver);
        $connection->shouldReceive('getDriverName')->andReturn($driver);

        return $connection;
    }
}
