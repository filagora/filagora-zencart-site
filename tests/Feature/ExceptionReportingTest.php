<?php

namespace Tests\Feature;

use Honeybadger\Contracts\Reporter;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Unhandled exceptions reach Honeybadger, except the Livewire rejections fuzzers provoke on the login
 * page, which are not actionable and would drown the real reports.
 */
class ExceptionReportingTest extends TestCase
{
    public function test_an_exception_is_sent_to_honeybadger(): void
    {
        $reporter = Mockery::mock(Reporter::class);
        $reporter->shouldReceive('notify')->once()->withArgs(fn (RuntimeException $e): bool => $e->getMessage() === 'boom');
        $this->app->instance('honeybadger', $reporter);

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('boom'));
    }

    public function test_a_rejected_livewire_property_update_is_not_reported(): void
    {
        $reporter = Mockery::mock(Reporter::class);
        $reporter->shouldReceive('notify')->never();
        $this->app->instance('honeybadger', $reporter);

        $this->app->make(ExceptionHandler::class)->report(new CannotUpdateLockedPropertyException('name'));
    }
}
