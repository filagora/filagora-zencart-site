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
