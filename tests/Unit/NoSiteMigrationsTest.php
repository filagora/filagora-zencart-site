<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every table belongs to a package or to the profile, which is what lets `filagora:adopt` compare
 * it with production's. A migration here would be a table nothing checks.
 */
class NoSiteMigrationsTest extends TestCase
{
    public function test_the_site_has_no_migrations_of_its_own(): void
    {
        $this->assertSame([], glob(dirname(__DIR__, 2).'/database/migrations/*.php'));
    }
}
