<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class RunnerGuardTest extends TestCase
{
    private const GUARD = 'scripts/runner/job_started_guard.sh';

    private const REPOSITORY = 'filagora/filagora-zencart-site';

    /**
     * What the runner sets for the Deploy workflow started by hand from master.
     */
    private const DEPLOY_JOB = [
        'GITHUB_REPOSITORY' => self::REPOSITORY,
        'GITHUB_EVENT_NAME' => 'workflow_dispatch',
        'GITHUB_REF' => 'refs/heads/master',
        'GITHUB_WORKFLOW_REF' => self::REPOSITORY.'/.github/workflows/deploy.yml@refs/heads/master',
    ];

    /**
     * Runs the guard as the runner does: as a child of the process that would go on to run the job.
     *
     * @param  array<string, string|false>  $env
     */
    private function runGuard(array $env): string
    {
        $guard = escapeshellarg(dirname(__DIR__, 2).'/'.self::GUARD);
        $worker = 'bash -e '.$guard.'; echo "job continues after $?"';

        $process = new Process(['bash', '-c', 'bash -c '.escapeshellarg($worker).'; echo "worker ended with $?"'], null, $env);
        $process->setTimeout(30);
        $process->run();

        return $process->getOutput();
    }

    public function test_the_manual_deploy_from_master_runs(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');

        $output = $this->runGuard(self::DEPLOY_JOB);

        $this->assertStringContainsString('job continues after 0', $output);
        $this->assertStringContainsString('worker ended with 0', $output);
    }

    /**
     * @return array<string, array{array<string, string|false>}>
     */
    public static function otherJobs(): array
    {
        $workflow = self::REPOSITORY.'/.github/workflows/';

        return [
            'pull request' => [[
                'GITHUB_EVENT_NAME' => 'pull_request',
                'GITHUB_REF' => 'refs/pull/1/merge',
                'GITHUB_WORKFLOW_REF' => $workflow.'deploy.yml@refs/pull/1/merge',
            ]],
            'pull request target' => [['GITHUB_EVENT_NAME' => 'pull_request_target']],
            'push' => [['GITHUB_EVENT_NAME' => 'push']],
            'another branch' => [[
                'GITHUB_REF' => 'refs/heads/other',
                'GITHUB_WORKFLOW_REF' => $workflow.'deploy.yml@refs/heads/other',
            ]],
            'a tag named master' => [['GITHUB_REF' => 'refs/tags/master']],
            'another workflow' => [['GITHUB_WORKFLOW_REF' => $workflow.'ci.yml@refs/heads/master']],
            'the workflow of another ref' => [['GITHUB_WORKFLOW_REF' => $workflow.'deploy.yml@refs/heads/other']],
            'another repository' => [['GITHUB_REPOSITORY' => 'someone/filagora-zencart-site']],
            'no event' => [['GITHUB_EVENT_NAME' => false]],
            'nothing known about the job' => [array_fill_keys(array_keys(self::DEPLOY_JOB), false)],
        ];
    }

    /**
     * A failing hook is only one failed step to the runner, so anything else must stop the worker itself.
     *
     * @param  array<string, string|false>  $changes
     */
    #[DataProvider('otherJobs')]
    public function test_any_other_job_has_its_worker_killed(array $changes): void
    {
        $output = $this->runGuard(array_merge(self::DEPLOY_JOB, $changes));

        $this->assertStringNotContainsString('job continues', $output);
        $this->assertStringContainsString('worker ended with 137', $output);
    }

    public function test_only_the_manual_deploy_workflow_asks_for_the_deploy_runner(): void
    {
        $workflows = glob(dirname(__DIR__, 2).'/.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [];
        $using = [];

        foreach ($workflows as $path) {
            if (preg_match('/self-hosted|filagora-production/', (string) file_get_contents($path)) === 1) {
                $using[] = basename($path);
            }
        }

        $this->assertSame(['deploy.yml'], $using);

        $deploy = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');

        $this->assertSame(['workflow_dispatch'], array_keys($deploy['on']));
    }
}
