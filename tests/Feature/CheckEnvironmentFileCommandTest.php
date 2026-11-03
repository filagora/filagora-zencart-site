<?php

namespace Tests\Feature;

use App\Support\EnvironmentKeyAudit;
use Tests\TestCase;

/**
 * The live site's environment file is carried over key by key; this is what tells a key that is
 * still read from one that would be dropped without a word.
 */
class CheckEnvironmentFileCommandTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'env-audit-');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->file.'.out');

        parent::tearDown();
    }

    public function test_it_sorts_keys_by_what_the_application_does_with_them(): void
    {
        $audit = new EnvironmentKeyAudit([config_path(), base_path('vendor/laravel/framework/config')]);
        $byKey = collect($audit->audit([
            'APP_KEY',
            'DB_MAX_STATEMENT_TIME',
            'TRUSTED_PROXIES',
            'PRIVACY_EXPORTS_DISK',
            'PLUGIN_SECURITY_SCAN_CLAMAV_ENABLED',
            'SCOUT_DRIVER',
            'STRIPE_SECRET',
            'AI_CHAT_ENABLED',
            'PLUGIN_SECURITY_SCAN_LLM_API_KEY',
            'SOMETHING_ODD',
            'BCRYPT_ROUNDS',
            'DO_NOT_REDEPLOY',
        ]))->keyBy('key');

        $this->assertSame(EnvironmentKeyAudit::READ, $byKey['APP_KEY']['status']);
        $this->assertSame(EnvironmentKeyAudit::READ, $byKey['DB_MAX_STATEMENT_TIME']['status']);
        $this->assertSame(EnvironmentKeyAudit::READ, $byKey['TRUSTED_PROXIES']['status']);
        $this->assertSame(EnvironmentKeyAudit::READ, $byKey['BCRYPT_ROUNDS']['status']);
        $this->assertSame(EnvironmentKeyAudit::READ, $byKey['DO_NOT_REDEPLOY']['status']);
        $this->assertSame(EnvironmentKeyAudit::RETIRED, $byKey['SCOUT_DRIVER']['status']);
        $this->assertSame(EnvironmentKeyAudit::RETIRED, $byKey['STRIPE_SECRET']['status']);
        $this->assertSame(EnvironmentKeyAudit::PENDING, $byKey['AI_CHAT_ENABLED']['status']);
        $this->assertSame(EnvironmentKeyAudit::UNKNOWN, $byKey['SOMETHING_ODD']['status']);
        $this->assertSame(EnvironmentKeyAudit::NO_COUNTERPART, $byKey['PLUGIN_SECURITY_SCAN_LLM_API_KEY']['status']);
    }

    public function test_it_suggests_the_name_a_package_reads_now(): void
    {
        $directories = array_merge([config_path()], glob(base_path('vendor/filagora/*/config'), GLOB_ONLYDIR) ?: []);
        $byKey = collect((new EnvironmentKeyAudit($directories))->audit(['PRIVACY_EXPORTS_DISK', 'PLUGIN_SECURITY_SCAN_CLAMAV_ENABLED', 'PLUGIN_RELEASE_ARCHIVE_KEEP_MONTHS']))->keyBy('key');

        $this->assertSame('FILAGORA_COMPLIANCE_EXPORTS_DISK', $byKey['PRIVACY_EXPORTS_DISK']['suggestion']);
        $this->assertSame('FILAGORA_PLUGINS_SECURITY_CLAMAV_ENABLED', $byKey['PLUGIN_SECURITY_SCAN_CLAMAV_ENABLED']['suggestion']);
        $this->assertSame('FILAGORA_PLUGINS_ARCHIVE_KEEP_MONTHS', $byKey['PLUGIN_RELEASE_ARCHIVE_KEEP_MONTHS']['suggestion']);
    }

    public function test_the_command_reports_keys_and_fails_while_any_need_attention(): void
    {
        file_put_contents($this->file, "APP_KEY=base64:secretvalue\nPRIVACY_EXPORTS_DISK=local\nSCOUT_DRIVER=meilisearch\n");

        $this->artisan('site:check-env', ['file' => $this->file])
            ->expectsOutputToContain('RENAMED (1)')
            ->expectsOutputToContain('FILAGORA_COMPLIANCE_EXPORTS_DISK')
            ->doesntExpectOutputToContain('secretvalue')
            ->assertFailed();
    }

    public function test_it_succeeds_when_every_key_is_read_or_set_aside_on_purpose(): void
    {
        file_put_contents($this->file, "APP_KEY=base64:x\nSCOUT_DRIVER=meilisearch\nAI_CHAT_ENABLED=true\n");

        $this->artisan('site:check-env', ['file' => $this->file])->assertSuccessful();
    }

    public function test_translate_writes_renamed_keys_and_comments_out_the_rest(): void
    {
        file_put_contents($this->file, "APP_NAME=Zen Cart\nPRIVACY_EXPORTS_DISK=local\nSCOUT_DRIVER=meilisearch\n");

        $this->artisan('site:check-env', ['file' => $this->file, '--translate' => $this->file.'.out'])->assertFailed();

        $this->assertSame("APP_NAME=Zen Cart\nFILAGORA_COMPLIANCE_EXPORTS_DISK=local\n# retired: SCOUT_DRIVER\n", file_get_contents($this->file.'.out'));
    }
}
