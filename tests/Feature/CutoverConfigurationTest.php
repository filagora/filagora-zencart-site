<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Settings the live site depends on that a fresh Laravel install does not have. Each one is read by
 * the production environment file, so a missing key would be ignored silently on the day of the switch.
 */
class CutoverConfigurationTest extends TestCase
{
    public function test_trusted_proxies_are_read_from_the_environment_file_variable(): void
    {
        $this->assertArrayHasKey('proxies', config('trustedproxy'));
    }

    public function test_the_client_address_and_scheme_come_from_a_trusted_proxy_and_only_from_one(): void
    {
        $seenBy = function (mixed $proxies): string {
            config(['trustedproxy.proxies' => $proxies]);
            $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7', 'HTTP_X_FORWARDED_PROTO' => 'https']);

            return (new TrustProxies)->handle($request, fn (Request $request) => response($request->ip().'|'.($request->isSecure() ? 'https' : 'http')))->getContent();
        };

        $this->assertSame('192.0.2.10|http', $seenBy(null));
        $this->assertSame('198.51.100.7|https', $seenBy('*'));
    }

    public function test_sessions_and_rate_limiter_counters_have_their_own_redis_databases(): void
    {
        $this->assertSame('2', (string) config('database.redis.session.database'));
        $this->assertSame('3', (string) config('database.redis.limiter.database'));
        $this->assertSame('limiter', config('cache.stores.limiter.connection'));
        $this->assertSame('redis', config('cache.stores.limiter.driver'));
    }

    public function test_the_limiter_store_falls_back_to_the_default_when_unset_or_blank(): void
    {
        $this->assertNull(config('cache.limiter'));
    }

    public function test_a_blank_time_zone_in_the_environment_file_is_not_sent_to_the_database(): void
    {
        $this->assertNull(config('database.connections.mariadb.timezone'));
    }

    public function test_a_web_statement_is_bounded_and_the_database_session_zone_can_be_set(): void
    {
        $this->assertSame(15, config('database.max_statement_time'));
        $this->assertArrayHasKey('timezone', config('database.connections.mariadb'));
    }

    public function test_the_version_check_cache_time_keeps_its_production_variable(): void
    {
        $this->assertSame(900, config('filagora-zencart.versioncheck.cache_ttl_seconds'));
        $this->assertTrue(config('filagora-zencart.versioncheck.routes'));
    }

    public function test_the_ops_pages_have_their_configuration(): void
    {
        $this->assertFalse(config('fail2ban.enabled'));
        $this->assertArrayHasKey('commands', config('fail2ban'));
        $this->assertFalse(config('nftables.enabled'));
        $this->assertArrayHasKey('ruleset', config('nftables.commands'));
        $this->assertSame('', config('app.trusted_hosts'));
    }
}
