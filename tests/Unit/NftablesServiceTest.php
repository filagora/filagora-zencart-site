<?php

namespace Tests\Unit;

use App\Services\NftablesService;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Tests\TestCase;

class NftablesServiceTest extends TestCase
{
    public function test_it_returns_disabled_snapshot_when_integration_is_off(): void
    {
        config()->set('nftables.enabled', false);

        Process::fake();

        $snapshot = app(NftablesService::class)->getStatusSnapshot();

        $this->assertFalse($snapshot['enabled']);
        $this->assertSame(0, $snapshot['table_count']);
        $this->assertSame([], $snapshot['tables']);
        $this->assertSame(0, $snapshot['chain_count']);
        $this->assertSame([], $snapshot['chains']);
        $this->assertSame(0, $snapshot['rule_count']);
        $this->assertSame([], $snapshot['sets']);
    }

    public function test_it_parses_sets_and_elements_from_nftables_json_output(): void
    {
        config()->set('nftables.enabled', true);
        config()->set('nftables.commands.ruleset', 'nft --json list ruleset');

        Process::fake([
            'nft --json list ruleset' => Process::result(output: <<<'JSON'
                {
                  "nftables": [
                    { "metainfo": { "version": "1.0.9" } },
                    { "table": { "family": "inet", "name": "filter" } },
                    { "chain": { "family": "inet", "table": "filter", "name": "input" } },
                    { "rule": { "family": "inet", "table": "filter", "chain": "input", "expr": [] } },
                    { "set": { "family": "inet", "table": "filter", "name": "blocked_ipv4", "type": "ipv4_addr", "flags": ["timeout"], "elem": ["203.0.113.10", "203.0.113.11"] } },
                    { "set": { "family": "inet", "table": "filter", "name": "blocked_ipv6", "type": "ipv6_addr", "elem": ["2001:db8::10"] } }
                  ]
                }
                JSON),
        ]);

        $snapshot = app(NftablesService::class)->getStatusSnapshot();

        $this->assertTrue($snapshot['enabled']);
        $this->assertSame(1, $snapshot['table_count']);
        $this->assertSame(['inet/filter'], $snapshot['tables']);
        $this->assertSame(1, $snapshot['chain_count']);
        $this->assertSame(['inet/filter/input'], $snapshot['chains']);
        $this->assertSame(1, $snapshot['rule_count']);
        $this->assertSame(2, $snapshot['set_count']);
        $this->assertCount(2, $snapshot['sets']);
        $this->assertSame('blocked_ipv4', $snapshot['sets'][0]['name']);
        $this->assertSame(['timeout'], $snapshot['sets'][0]['flags']);
        $this->assertSame('203.0.113.10', $snapshot['sets'][0]['elements'][0]['value']);
        $this->assertTrue($snapshot['sets'][0]['elements'][0]['removable']);
    }

    public function test_it_merges_top_level_element_entries_into_existing_sets(): void
    {
        config()->set('nftables.enabled', true);
        config()->set('nftables.commands.ruleset', 'nft --json list ruleset');

        Process::fake([
            'nft --json list ruleset' => Process::result(output: <<<'JSON'
                {
                  "nftables": [
                    { "table": { "family": "inet", "name": "filter" } },
                    { "set": { "family": "inet", "table": "filter", "name": "blocked_ipv4", "type": "ipv4_addr" } },
                    { "element": { "family": "inet", "table": "filter", "name": "blocked_ipv4", "elem": ["203.0.113.10", "203.0.113.11"] } }
                  ]
                }
                JSON),
        ]);

        $snapshot = app(NftablesService::class)->getStatusSnapshot();

        $this->assertSame(2, $snapshot['sets'][0]['element_count']);
        $this->assertSame(['203.0.113.10', '203.0.113.11'], array_column($snapshot['sets'][0]['elements'], 'value'));
    }

    public function test_it_rejects_invalid_remove_input(): void
    {
        config()->set('nftables.enabled', true);

        $service = app(NftablesService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->removeElement('inet', 'filter', 'blocked_ipv4', '203.0.113.10; rm -rf /');
    }

    public function test_it_runs_the_remove_command_for_valid_input(): void
    {
        config()->set('nftables.enabled', true);
        config()->set('nftables.commands.remove_element', 'nft delete element :family :table :set { :element }');

        Process::fake([
            'nft delete element inet filter blocked_ipv4 { 203.0.113.10 }' => Process::result(output: ''),
        ]);

        app(NftablesService::class)->removeElement('inet', 'filter', 'blocked_ipv4', '203.0.113.10');

        $this->assertTrue(true);
    }

    public function test_local_mock_script_supports_ruleset_and_remove_element_commands(): void
    {
        $stateFile = sys_get_temp_dir().'/nftables-mock-'.uniqid('', true).'.json';
        $script = base_path('scripts/dev/nftables-local-mock.sh');
        $prefix = 'NFTABLES_MOCK_STATE_FILE='.escapeshellarg($stateFile).' /bin/bash '.escapeshellarg($script);

        config()->set('nftables.enabled', true);
        config()->set('nftables.commands.ruleset', $prefix.' ruleset');
        config()->set('nftables.commands.remove_element', $prefix.' remove-element :family :table :set :element');

        try {
            $initialSnapshot = app(NftablesService::class)->getStatusSnapshot();

            $this->assertSame(2, $initialSnapshot['set_count']);
            $this->assertSame(2, $initialSnapshot['sets'][0]['element_count']);
            $this->assertContains('203.0.113.10', array_column($initialSnapshot['sets'][0]['elements'], 'value'));

            app(NftablesService::class)->removeElement('inet', 'filter', 'blocked_ipv4', '203.0.113.10');

            $updatedSnapshot = app(NftablesService::class)->getStatusSnapshot();

            $this->assertSame(1, $updatedSnapshot['sets'][0]['element_count']);
            $this->assertNotContains('203.0.113.10', array_column($updatedSnapshot['sets'][0]['elements'], 'value'));
        } finally {
            @unlink($stateFile);
        }
    }
}
