<?php

namespace Tests\Unit;

use App\Support\TrustedHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class TrustedHostsTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_nothing_is_enforced_until_hosts_are_configured(): void
    {
        config(['app.trusted_hosts' => '']);

        $this->assertSame([], TrustedHosts::patterns());
    }

    public function test_configured_hosts_become_anchored_escaped_patterns(): void
    {
        config(['app.trusted_hosts' => ' WWW.Example.test , example.test,, bad host!, *.evil.test ']);

        $this->assertSame(['^www\.example\.test$', '^example\.test$'], TrustedHosts::patterns());
    }

    public function test_only_the_listed_hosts_are_accepted_once_enforced(): void
    {
        config(['app.trusted_hosts' => 'www.example.test,example.test']);
        Request::setTrustedHosts(TrustedHosts::patterns());

        $this->assertSame('www.example.test', Request::create('https://www.example.test/x')->getHost());
        $this->assertSame('example.test', Request::create('https://EXAMPLE.test:8443/x')->getHost());

        foreach (['https://evil.example/x', 'https://www.example.test.evil.example/x', 'https://notexample.test/x'] as $url) {
            try {
                Request::create($url)->getHost();
                $this->fail("{$url} should not be trusted.");
            } catch (SuspiciousOperationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
