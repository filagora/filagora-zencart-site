<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The old site refused migrate:fresh, db:wipe and the like in production unless
 * ALLOW_DESTRUCTIVE_COMMANDS_IN_PRODUCTION was set; the production .env carries that key.
 */
class ProductionDestructiveCommandGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FreshCommand::prohibit(false);

        parent::tearDown();
    }

    public function test_destructive_commands_are_prohibited_in_production_by_default(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.allow_destructive_commands_in_production', false);

        $this->bootProvider();

        $this->assertTrue($this->freshCommandIsProhibited());
    }

    public function test_the_production_override_lets_destructive_commands_run(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.allow_destructive_commands_in_production', true);

        $this->bootProvider();

        $this->assertFalse($this->freshCommandIsProhibited());
    }

    public function test_destructive_commands_are_not_prohibited_outside_production(): void
    {
        $this->app['env'] = 'local';
        config()->set('app.allow_destructive_commands_in_production', false);

        $this->bootProvider();

        $this->assertFalse($this->freshCommandIsProhibited());
    }

    private function bootProvider(): void
    {
        (new AppServiceProvider($this->app))->boot();
    }

    private function freshCommandIsProhibited(): bool
    {
        $command = $this->app->make(FreshCommand::class);
        $method = new ReflectionMethod($command, 'isProhibited');
        $method->setAccessible(true);

        return $method->invoke($command, true);
    }
}
