<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class RecordUserLastSeenMiddlewareTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_authenticated_request_records_last_seen_at(): void
    {
        $user = User::factory()->create(['last_seen_at' => null]);

        $this->actingAs($user)->get(route('home'))->assertOk();

        $this->assertNotNull($user->refresh()->last_seen_at);
    }

    public function test_last_seen_at_is_not_rewritten_within_the_throttle_window(): void
    {
        // Truncate to whole seconds up front: the DB column has no fractional
        // second precision, so comparing against an untruncated in-memory
        // Carbon value would spuriously fail even when nothing was rewritten.
        $recent = now()->subMinute()->startOfSecond();
        $user = User::factory()->create(['last_seen_at' => $recent]);

        $this->actingAs($user)->get(route('home'))->assertOk();

        $this->assertTrue($user->refresh()->last_seen_at->equalTo($recent));
    }

    public function test_last_seen_at_is_rewritten_once_stale(): void
    {
        $stale = now()->subHour();
        $user = User::factory()->create(['last_seen_at' => $stale]);

        $this->actingAs($user)->get(route('home'))->assertOk();

        $this->assertTrue($user->refresh()->last_seen_at->gt($stale));
    }

    public function test_guest_request_does_not_error(): void
    {
        $this->get(route('home'))->assertOk();
        $this->assertTrue(true);
    }
}
