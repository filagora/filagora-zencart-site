<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class MessagesScheduleTest extends TestCase
{
    public function test_trashed_conversations_are_purged_daily_and_the_bell_is_pruned(): void
    {
        $events = collect(app(Schedule::class)->events());

        $purge = $events->first(fn ($event): bool => str_contains((string) $event->command, 'filagora:messages:purge-trashed'));
        $prune = $events->first(fn ($event): bool => str_contains((string) $event->command, 'notifications:prune'));

        $this->assertNotNull($purge);
        $this->assertSame('20 2 * * *', $purge->expression);
        $this->assertTrue($purge->withoutOverlapping);
        $this->assertNotNull($prune);
        $this->assertSame('40 2 * * *', $prune->expression);
    }
}
