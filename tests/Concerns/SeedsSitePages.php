<?php

namespace Tests\Concerns;

use Database\Seeders\SitePagesSeeder;

/**
 * Gives a test the site's published CMS pages, as production has them. Use after RefreshDatabase.
 */
trait SeedsSitePages
{
    protected function setUpSeedsSitePages(): void
    {
        $this->seed(SitePagesSeeder::class);
    }
}
