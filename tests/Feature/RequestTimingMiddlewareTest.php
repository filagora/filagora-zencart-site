<?php

namespace Tests\Feature;

use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestTimingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_timing_logs_metrics_and_sets_headers_for_matching_paths(): void
    {
        config()->set('app.request_timing.enabled', true);
        config()->set('app.request_timing.channel', 'stack');
        config()->set('app.request_timing.paths', ['forums']);

        $group = ForumGroup::query()->create([
            'name' => 'Timing Group',
            'slug' => 'timing-group',
            'is_active' => true,
        ]);

        Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Timing Forum',
            'slug' => 'timing-forum',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::PUBLIC,
            'is_read_only' => false,
            'show_when_empty' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Log::partialMock()->shouldIgnoreMissing();
        Log::shouldReceive('channel')->once()->with('stack')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'request.timing'
                    && $context['method'] === 'GET'
                    && ! array_key_exists('path', $context)
                    && ! array_key_exists('query', $context)
                    && $context['route_name'] === 'forums.index'
                    && $context['status'] === 200
                    && $context['status_class'] === '2xx'
                    && $context['db_query_count'] >= 1
                    && is_numeric($context['duration_ms'])
                    && is_numeric($context['db_query_time_ms'])
                    && is_numeric($context['peak_memory_mb']);
            });

        $response = $this->get('/forums?sample=1');

        $response->assertOk();
        $response->assertHeader('X-Request-Duration-Ms');
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('X-Db-Query-Count'));
        $response->assertHeader('X-Db-Query-Time-Ms');
        $response->assertHeader('Server-Timing');
    }

    public function test_request_timing_skips_non_matching_paths(): void
    {
        config()->set('app.request_timing.enabled', true);
        config()->set('app.request_timing.channel', 'stack');
        config()->set('app.request_timing.paths', ['forums']);

        Log::partialMock()->shouldIgnoreMissing();
        Log::shouldReceive('channel')->never();
        Log::shouldReceive('info')->never();

        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeaderMissing('X-Request-Duration-Ms');
        $response->assertHeaderMissing('X-Db-Query-Count');
        $response->assertHeaderMissing('Server-Timing');
    }
}
