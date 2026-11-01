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
}
