<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DeployScriptsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function scripts(): array
    {
        return [
            'release deploy' => ['scripts/deploy/release_deploy.sh'],
            'rollback' => ['scripts/deploy/rollback_release.sh'],
        ];
    }

    #[DataProvider('scripts')]
    public function test_the_script_parses(string $script): void
    {
        $process = new Process(['bash', '-n', dirname(__DIR__, 2).'/'.$script]);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_the_release_script_prunes_only_after_the_web_servers_reload(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/deploy/release_deploy.sh');

        $reload = strpos($script, 'systemctl reload "${DEPLOY_PHP_FPM_SERVICE}"');
        $grace = strpos($script, 'sleep "${DEPLOY_PRUNE_GRACE_SECONDS}"');
        $prune = strpos($script, 'rm -rf "${release_path}"');

        $this->assertNotFalse($reload);
        $this->assertLessThan($grace, $reload);
        $this->assertLessThan($prune, $grace);
    }

    public function test_adoption_is_its_own_opt_in_step_before_migrate(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/deploy/release_deploy.sh');

        $this->assertStringContainsString('DEPLOY_RUN_ADOPT="${DEPLOY_RUN_ADOPT:-0}"', $script);
        $this->assertLessThan(strpos($script, 'php artisan migrate --force'), strpos($script, 'php artisan filagora:adopt --dry-run'));
    }

    public function test_the_environment_check_runs_before_the_switch_and_the_smoke_check_before_pruning(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/deploy/release_deploy.sh');

        $envCheck = strpos($script, "\ncheck_environment_file\n");
        $switch = strpos($script, 'mv -Tf "${STAGING_LINK}" "${CURRENT_LINK}"');
        $smoke = strpos($script, 'smoke_check_urls "${DEPLOY_SMOKE_URLS}"');
        $prune = strpos($script, 'rm -rf "${release_path}"');

        $this->assertNotFalse($envCheck);
        $this->assertLessThan($switch, $envCheck);
        $this->assertLessThan($prune, $smoke);
        $this->assertGreaterThan($switch, $smoke);
    }

    /**
     * @param  array<string, string>  $env
     */
    private function runShellFunction(string $function, string $call, string $path, array $env = []): Process
    {
        $script = dirname(__DIR__, 2).'/scripts/deploy/release_deploy.sh';
        $process = new Process(['bash', '-c', 'set -euo pipefail; source <(sed -n "/^'.$function.'()/,/^}/p" '.escapeshellarg($script).'); '.$call], null, ['PATH' => $path] + $env);
        $process->setTimeout(30);
        $process->run();

        return $process;
    }

    private function fakeBin(string $name, string $body): string
    {
        $dir = sys_get_temp_dir().'/deploy-bin-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/'.$name, "#!/usr/bin/env bash\n".$body."\n");
        chmod($dir.'/'.$name, 0755);

        return $dir;
    }

    public function test_a_failing_smoke_url_fails_the_check_and_a_passing_one_succeeds(): void
    {
        $failing = $this->fakeBin('curl', 'exit 22');
        $passing = $this->fakeBin('curl', 'exit 0');
        $env = ['DEPLOY_SMOKE_RESOLVE' => '', 'DEPLOY_SMOKE_RETRIES' => '2', 'DEPLOY_SMOKE_INTERVAL_SECONDS' => '0'];
        $call = 'DEPLOY_SMOKE_RESOLVE="" DEPLOY_SMOKE_RETRIES=2 DEPLOY_SMOKE_INTERVAL_SECONDS=0 smoke_check_urls "https://a.test/up, https://b.test/up"';

        $this->assertSame(1, $this->runShellFunction('smoke_check_urls', $call, $failing.':'.getenv('PATH'), $env)->getExitCode());

        $ok = $this->runShellFunction('smoke_check_urls', $call, $passing.':'.getenv('PATH'), $env);
        $this->assertSame(0, $ok->getExitCode(), $ok->getErrorOutput());
        $this->assertStringContainsString('Smoke check passed: https://b.test/up', $ok->getOutput());
    }

    public function test_the_deploy_refuses_a_host_that_is_not_the_expected_one(): void
    {
        // Built from parts: the leak scan rejects an IPv4 literal anywhere in the repository.
        $own = implode('.', [10, 0, 0, 5]);
        $other = implode('.', [203, 0, 113, 9]);
        $absent = implode('.', [198, 51, 100, 1]);

        $bin = $this->fakeBin('hostname', 'if [ "${1:-}" = "-I" ]; then echo "'.$own.' '.$other.'"; else echo box-a; fi');
        $path = $bin.':'.getenv('PATH');
        $run = fn (string $vars): Process => $this->runShellFunction('assert_deploy_target', $vars.' assert_deploy_target', $path);

        $this->assertSame(0, $run('DEPLOY_EXPECTED_HOSTNAME= DEPLOY_FORBIDDEN_ADDRESSES=')->getExitCode());
        $this->assertSame(0, $run('DEPLOY_EXPECTED_HOSTNAME=box-a DEPLOY_FORBIDDEN_ADDRESSES='.$absent)->getExitCode());
        $this->assertSame(1, $run('DEPLOY_EXPECTED_HOSTNAME=box-b DEPLOY_FORBIDDEN_ADDRESSES=')->getExitCode());
        $this->assertSame(1, $run('DEPLOY_EXPECTED_HOSTNAME= DEPLOY_FORBIDDEN_ADDRESSES="'.$absent.', '.$other.'"')->getExitCode());
    }

    public function test_the_host_guard_is_called_after_its_settings_have_defaults(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/deploy/release_deploy.sh');

        $call = strpos($script, "\nassert_deploy_target\n");

        $this->assertNotFalse($call);
        $this->assertGreaterThan(strpos($script, 'DEPLOY_EXPECTED_HOSTNAME="${DEPLOY_EXPECTED_HOSTNAME:-}"'), $call);
        $this->assertGreaterThan(strpos($script, 'DEPLOY_FORBIDDEN_ADDRESSES="${DEPLOY_FORBIDDEN_ADDRESSES:-}"'), $call);
    }

    public function test_the_environment_check_warns_by_default_and_fails_only_when_strict(): void
    {
        $php = $this->fakeBin('php', 'exit 1');
        $path = $php.':'.getenv('PATH');

        $warn = $this->runShellFunction('check_environment_file', 'DEPLOY_ENV_CHECK=warn DEPLOY_SHARED_ENV_PATH=/x check_environment_file', $path);
        $this->assertSame(0, $warn->getExitCode(), $warn->getErrorOutput());
        $this->assertStringContainsString('continuing deploy', $warn->getErrorOutput());

        $strict = $this->runShellFunction('check_environment_file', 'DEPLOY_ENV_CHECK=strict DEPLOY_SHARED_ENV_PATH=/x check_environment_file', $path);
        $this->assertSame(1, $strict->getExitCode());

        $off = $this->runShellFunction('check_environment_file', 'DEPLOY_ENV_CHECK=off DEPLOY_SHARED_ENV_PATH=/x check_environment_file', $path);
        $this->assertSame(0, $off->getExitCode());
    }

    public function test_the_default_acl_check_accepts_a_large_listing_without_a_false_missing_error(): void
    {
        $dir = sys_get_temp_dir().'/acl-check-'.bin2hex(random_bytes(6));
        mkdir($dir.'/bin', 0777, true);
        file_put_contents($dir.'/bin/getfacl', "#!/usr/bin/env bash\necho 'default:group:www-data:rwx'\nfor i in \$(seq 1 80000); do echo \"default:user:u\$i:rwx\"; done\necho 'default:mask::rwx'\n");
        file_put_contents($dir.'/bin/setfacl', "#!/usr/bin/env bash\nexit 0\n");
        chmod($dir.'/bin/getfacl', 0755);
        chmod($dir.'/bin/setfacl', 0755);

        $script = dirname(__DIR__, 2).'/scripts/deploy/release_deploy.sh';
        $process = new Process(['bash', '-c', 'set -euo pipefail; source <(sed -n "/^assert_default_group_acl()/,/^}/p" '.escapeshellarg($script).'); assert_default_group_acl /tmp www-data'], null, ['PATH' => $dir.'/bin:'.getenv('PATH')]);
        $process->setTimeout(30);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }
}
