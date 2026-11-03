<?php

namespace App\Support;

/**
 * Sorts the keys of an environment file (the live site's, say) by what the running application does
 * with them: read as they are, read under a new name, no longer read, or kept for work not yet done.
 * The keys the application reads are found by scanning the `env('KEY'` calls in its own config and in
 * the Filagora packages' config, so the audit cannot drift from the code.
 */
class EnvironmentKeyAudit
{
    public const READ = 'read';

    public const RENAMED = 'renamed';

    public const NO_COUNTERPART = 'no counterpart';

    public const RETIRED = 'retired';

    public const PENDING = 'pending port';

    public const UNKNOWN = 'unknown';

    /**
     * Keys lara-forum read that a Filagora package reads under another name.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'PRIVACY_EXPORTS_DISK' => 'FILAGORA_COMPLIANCE_EXPORTS_DISK',
        'PRIVACY_EXPORT_DOWNLOAD_LINK_TTL_MINUTES' => 'FILAGORA_COMPLIANCE_EXPORT_LINK_MINUTES',
        'PRIVACY_RETENTION_SCHEDULE_DAILY_AT' => 'FILAGORA_COMPLIANCE_SCHEDULE_AT',
        'PRIVACY_RETENTION_SCHEDULE_ENABLED' => 'FILAGORA_COMPLIANCE_SCHEDULE_ENABLED',
        'PLUGIN_UPLOAD_MAX_ENTRIES' => 'FILAGORA_PLUGINS_ARCHIVE_MAX_ENTRIES',
        'PLUGIN_UPLOAD_MAX_UNCOMPRESSED_BYTES' => 'FILAGORA_PLUGINS_ARCHIVE_MAX_UNCOMPRESSED_BYTES',
        'PLUGIN_LINT_PHP_BINARY' => 'FILAGORA_PLUGINS_SECURITY_PHP_BINARY',
        'PLUGIN_RELEASE_ARCHIVE_KEEP_MONTHS' => 'FILAGORA_PLUGINS_ARCHIVE_KEEP_MONTHS',
        'PLUGIN_RELEASE_ARCHIVE_KEEP_PER_ZC_MAJOR' => 'FILAGORA_PLUGINS_ARCHIVE_KEEP_PER_LINE',
        'PLUGIN_SECURITY_SCAN_SWEEP_BATCH_SIZE' => 'FILAGORA_PLUGINS_SECURITY_SWEEP_BATCH_SIZE',
        'PLUGIN_SECURITY_SCAN_STATIC_PHP_ENABLED' => 'FILAGORA_PLUGINS_SECURITY_STATIC_PHP_ENABLED',
        'PLUGIN_SECURITY_SCAN_AUTO_CLEAR_WHEN_CLEAN' => 'FILAGORA_PLUGINS_SECURITY_AUTO_CLEAR_WHEN_CLEAN',
        'PLUGIN_SECURITY_SCAN_DISPATCH_ON_UPLOAD' => 'FILAGORA_PLUGINS_SECURITY_DISPATCH_ON_UPLOAD',
        'PLUGIN_SECURITY_SCAN_KNOWN_BAD_SHA256' => 'FILAGORA_PLUGINS_SECURITY_KNOWN_BAD_SHA256',
        'PLUGIN_SECURITY_SCAN_QUARANTINE_THRESHOLD' => 'FILAGORA_PLUGINS_SECURITY_QUARANTINE_THRESHOLD',
        'PLUGIN_SECURITY_SCAN_VERSION' => 'FILAGORA_PLUGINS_SECURITY_VERSION',
    ];

    /**
     * Prefixes whose keys are renamed by swapping the prefix, when the renamed key is read.
     *
     * @var array<string, string>
     */
    private const PREFIX_RENAMES = [
        'PLUGIN_SECURITY_SCAN_' => 'FILAGORA_PLUGINS_SECURITY_',
        'PLUGIN_RELEASE_ARCHIVE_' => 'FILAGORA_PLUGINS_ARCHIVE_',
    ];

    /**
     * The search engines, developer tools and the Stripe billing lara-forum had that the new stack does not
     * (the site takes payments through PayPal only).
     *
     * @var list<string>
     */
    private const RETIRED_PREFIXES = ['SCOUT_', 'ALGOLIA_', 'TYPESENSE_', 'BOOST_', 'API_DEBUG_', 'STRIPE_'];

    /**
     * Work still to come over from lara-forum (the AI features) or deliberately left there.
     *
     * @var list<string>
     */
    private const PENDING_PREFIXES = ['AI_', 'OPENAI_', 'ANTHROPIC_', 'VOYAGE_', 'DIGITALOCEAN_', 'SITE_CODE_', 'ZENCART_CODE_', 'ZENCART_DOCS_', 'VBULLETIN_'];

    /**
     * Keys read somewhere other than a config file: by the deploy script, or at asset build time.
     *
     * @var list<string>
     */
    private const READ_ELSEWHERE = ['DO_NOT_REDEPLOY', 'VITE_APP_NAME'];

    /**
     * @param  list<string>  $configDirectories  directories of config files to scan for env() calls
     */
    public function __construct(private readonly array $configDirectories) {}

    /**
     * @return list<string>
     */
    public function keysRead(): array
    {
        $keys = [];

        foreach ($this->configDirectories as $directory) {
            foreach (glob(rtrim($directory, '/').'/*.php') ?: [] as $file) {
                if (preg_match_all("/env\\(\\s*['\"]([A-Z0-9_]+)['\"]/", (string) file_get_contents($file), $matches)) {
                    array_push($keys, ...$matches[1]);
                }
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @param  list<string>  $keys  the keys of the environment file
     * @return list<array{key: string, status: string, suggestion: ?string}>
     */
    public function audit(array $keys): array
    {
        $read = array_flip($this->keysRead());
        $rows = [];

        foreach ($keys as $key) {
            $rows[] = ['key' => $key] + $this->classify($key, $read);
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $read
     * @return array{status: string, suggestion: ?string}
     */
    private function classify(string $key, array $read): array
    {
        if (isset($read[$key]) || in_array($key, self::READ_ELSEWHERE, true)) {
            return ['status' => self::READ, 'suggestion' => null];
        }

        if (isset(self::RENAMES[$key]) && isset($read[self::RENAMES[$key]])) {
            return ['status' => self::RENAMED, 'suggestion' => self::RENAMES[$key]];
        }

        foreach (self::PREFIX_RENAMES as $from => $to) {
            if (str_starts_with($key, $from) && isset($read[$to.substr($key, strlen($from))])) {
                return ['status' => self::RENAMED, 'suggestion' => $to.substr($key, strlen($from))];
            }
        }

        foreach (self::RETIRED_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return ['status' => self::RETIRED, 'suggestion' => null];
            }
        }

        foreach (self::PENDING_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return ['status' => self::PENDING, 'suggestion' => null];
            }
        }

        foreach (['PLUGIN_SECURITY_SCAN_', 'PLUGIN_RELEASE_ARCHIVE_', 'PLUGIN_UPLOAD_', 'PLUGIN_LINT_', 'PRIVACY_', 'PRIVATE_MESSAGES_', 'SEARCH_HEALTH_', 'PLUGINS_', 'FORUMS_'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return ['status' => self::NO_COUNTERPART, 'suggestion' => null];
            }
        }

        return ['status' => self::UNKNOWN, 'suggestion' => null];
    }
}
