<?php

namespace Tests\Unit;

use App\Services\Fail2banService;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Tests\TestCase;

class Fail2banServiceTest extends TestCase
{
    public function test_it_returns_disabled_snapshot_when_integration_is_off(): void
    {
        config()->set('fail2ban.enabled', false);

        Process::fake();

        $snapshot = app(Fail2banService::class)->getStatusSnapshot();

        $this->assertFalse($snapshot['enabled']);
        $this->assertSame(0, $snapshot['jail_count']);
        $this->assertSame([], $snapshot['jails']);
    }

    public function test_it_parses_jails_and_banned_ips_from_fail2ban_output(): void
    {
        config()->set('fail2ban.enabled', true);
        config()->set('fail2ban.commands.status', 'fail2ban-client status');
        config()->set('fail2ban.commands.jail_status', 'fail2ban-client status :jail');

        Process::fake([
            'fail2ban-client status' => Process::result(output: <<<'OUT'
                Status
                |- Number of jail:      2
                `- Jail list:   sshd, nginx-http-auth
                OUT),
            'fail2ban-client status sshd' => Process::result(output: <<<'OUT'
                Status for the jail: sshd
                |- Filter
                |  |- Currently failed:  2
                |  |- Total failed:      10
                |  `- Journal matches:   _SYSTEMD_UNIT=ssh.service + _COMM=sshd
                `- Actions
                   |- Currently banned:  1
                   |- Total banned:      5
                   `- Banned IP list:    203.0.113.10
                OUT),
            'fail2ban-client status nginx-http-auth' => Process::result(output: <<<'OUT'
                Status for the jail: nginx-http-auth
                |- Filter
                |  |- Currently failed:  0
                |  |- Total failed:      3
                |  `- File list:         /var/log/nginx/error.log
                `- Actions
                   |- Currently banned:  2
                   |- Total banned:      4
                   `- Banned IP list:    198.51.100.20 2001:db8::10
                OUT),
        ]);

        $snapshot = app(Fail2banService::class)->getStatusSnapshot();

        $this->assertTrue($snapshot['enabled']);
        $this->assertSame(2, $snapshot['jail_count']);
        $this->assertCount(2, $snapshot['jails']);
        $this->assertSame('sshd', $snapshot['jails'][0]['name']);
        $this->assertSame(1, $snapshot['jails'][0]['currently_banned']);
        $this->assertSame(['203.0.113.10'], $snapshot['jails'][0]['banned_ips']);
        $this->assertSame('_SYSTEMD_UNIT=ssh.service + _COMM=sshd', $snapshot['jails'][0]['journal_matches']);
        $this->assertSame(['198.51.100.20', '2001:db8::10'], $snapshot['jails'][1]['banned_ips']);
    }

    public function test_it_parses_wrapped_fail2ban_lists(): void
    {
        config()->set('fail2ban.enabled', true);
        config()->set('fail2ban.commands.status', 'fail2ban-client status');
        config()->set('fail2ban.commands.jail_status', 'fail2ban-client status :jail');

        Process::fake([
            'fail2ban-client status' => Process::result(output: <<<'OUT'
                Status
                |- Number of jail:      3
                `- Jail list:   sshd,
                               nginx-http-auth,
                               postfix-sasl
                OUT),
            'fail2ban-client status sshd' => Process::result(output: <<<'OUT'
                Status for the jail: sshd
                |- Filter
                |  |- Currently failed:  2
                |  |- Total failed:      10
                |  `- File list:         /var/log/auth.log
                |                         /var/log/auth.log.1
                `- Actions
                   |- Currently banned:  2
                   |- Total banned:      5
                   `- Banned IP list:    203.0.113.10
                                         2001:db8::10
                OUT),
            'fail2ban-client status nginx-http-auth' => Process::result(output: <<<'OUT'
                Status for the jail: nginx-http-auth
                |- Filter
                |  |- Currently failed:  0
                |  |- Total failed:      3
                |  `- File list:         /var/log/nginx/error.log
                `- Actions
                   |- Currently banned:  0
                   |- Total banned:      4
                   `- Banned IP list:
                OUT),
            'fail2ban-client status postfix-sasl' => Process::result(output: <<<'OUT'
                Status for the jail: postfix-sasl
                |- Filter
                |  |- Currently failed:  1
                |  |- Total failed:      2
                |  `- File list:         /var/log/mail.log
                `- Actions
                   |- Currently banned:  0
                   |- Total banned:      1
                   `- Banned IP list:
                OUT),
        ]);

        $snapshot = app(Fail2banService::class)->getStatusSnapshot();

        $this->assertSame(3, $snapshot['jail_count']);
        $this->assertSame(['sshd', 'nginx-http-auth', 'postfix-sasl'], array_column($snapshot['jails'], 'name'));
        $this->assertSame(['203.0.113.10', '2001:db8::10'], $snapshot['jails'][0]['banned_ips']);
        $this->assertSame(['/var/log/auth.log', '/var/log/auth.log.1'], $snapshot['jails'][0]['log_files']);
        $this->assertNull($snapshot['jails'][0]['journal_matches']);
    }

    public function test_it_normalizes_currently_banned_to_match_parsed_ip_list(): void
    {
        config()->set('fail2ban.enabled', true);
        config()->set('fail2ban.commands.status', 'fail2ban-client status');
        config()->set('fail2ban.commands.jail_status', 'fail2ban-client status :jail');

        Process::fake([
            'fail2ban-client status' => Process::result(output: <<<'OUT'
                Status
                |- Number of jail:      1
                `- Jail list:   sshd
                OUT),
            'fail2ban-client status sshd' => Process::result(output: <<<'OUT'
                Status for the jail: sshd
                |- Filter
                |  |- Currently failed:  0
                |  |- Total failed:      11
                |  `- Journal matches:   _SYSTEMD_UNIT=ssh.service + _COMM=sshd
                `- Actions
                   |- Currently banned:  1
                   |- Total banned:      11
                   `- Banned IP list:    47.251.165.65
                                         43.160.214.230
                OUT),
        ]);

        $snapshot = app(Fail2banService::class)->getStatusSnapshot();

        $this->assertSame(2, $snapshot['jails'][0]['currently_banned']);
        $this->assertSame(['47.251.165.65', '43.160.214.230'], $snapshot['jails'][0]['banned_ips']);
    }

    public function test_it_rejects_invalid_unban_input(): void
    {
        config()->set('fail2ban.enabled', true);

        $service = app(Fail2banService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->unban('bad jail name', '203.0.113.10');
    }

    public function test_it_runs_the_unban_command_for_valid_input(): void
    {
        config()->set('fail2ban.enabled', true);
        config()->set('fail2ban.commands.unban', 'fail2ban-client set :jail unbanip :ip');

        Process::fake([
            'fail2ban-client set sshd unbanip 203.0.113.10' => Process::result(output: '1'),
        ]);

        app(Fail2banService::class)->unban('sshd', '203.0.113.10');

        $this->assertTrue(true);
    }

    public function test_local_mock_script_supports_status_and_unban_commands(): void
    {
        $stateFile = sys_get_temp_dir().'/fail2ban-mock-'.uniqid('', true).'.json';
        $script = base_path('scripts/dev/fail2ban-local-mock.sh');
        $prefix = 'FAIL2BAN_MOCK_STATE_FILE='.escapeshellarg($stateFile).' /bin/bash '.escapeshellarg($script);

        config()->set('fail2ban.enabled', true);
        config()->set('fail2ban.commands.status', $prefix.' status');
        config()->set('fail2ban.commands.jail_status', $prefix.' jail-status :jail');
        config()->set('fail2ban.commands.unban', $prefix.' unban :jail :ip');

        try {
            $initialSnapshot = app(Fail2banService::class)->getStatusSnapshot();

            $this->assertSame(2, $initialSnapshot['jail_count']);
            $this->assertSame(12, $initialSnapshot['jails'][0]['currently_banned']);
            $this->assertContains('203.0.113.10', $initialSnapshot['jails'][0]['banned_ips']);

            app(Fail2banService::class)->unban('sshd', '203.0.113.10');

            $updatedSnapshot = app(Fail2banService::class)->getStatusSnapshot();

            $this->assertSame(11, $updatedSnapshot['jails'][0]['currently_banned']);
            $this->assertNotContains('203.0.113.10', $updatedSnapshot['jails'][0]['banned_ips']);
        } finally {
            @unlink($stateFile);
        }
    }
}
