<?php

namespace App\Console\Commands;

use App\Support\EnvironmentKeyAudit;
use Illuminate\Console\Command;

/**
 * Reads the keys of an environment file (never the values) and says what this application does with
 * each one, so the live site's file can be carried over without a setting being silently dropped.
 */
class CheckEnvironmentFileCommand extends Command
{
    protected $signature = 'site:check-env {file : The environment file to check, for example the live site\'s shared .env}
        {--translate= : Write a copy of the file to this path with renamed keys changed and the keys nothing reads left out}';

    protected $description = 'Sort the keys of an environment file by whether this application reads them';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $keys = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $match) === 1) {
                $keys[] = $match[1];
            }
        }

        $directories = array_merge([config_path()], glob(base_path('vendor/filagora/*/config'), GLOB_ONLYDIR) ?: []);
        $audit = new EnvironmentKeyAudit($directories);
        $rows = $audit->audit(array_values(array_unique($keys)));
        $byStatus = [];

        foreach ($rows as $row) {
            $byStatus[$row['status']][] = $row;
        }

        $this->line(sprintf('%d keys in %s', count($rows), $path));

        foreach ([EnvironmentKeyAudit::RENAMED, EnvironmentKeyAudit::NO_COUNTERPART, EnvironmentKeyAudit::UNKNOWN, EnvironmentKeyAudit::PENDING, EnvironmentKeyAudit::RETIRED] as $status) {
            if (($byStatus[$status] ?? []) === []) {
                continue;
            }

            $this->newLine();
            $this->line(strtoupper($status).' ('.count($byStatus[$status]).')');
            $this->table(['Key', 'Use instead'], array_map(fn (array $row): array => [$row['key'], $row['suggestion'] ?? ''], $byStatus[$status]));
        }

        $this->newLine();
        $this->line(sprintf('%d read as they are.', count($byStatus[EnvironmentKeyAudit::READ] ?? [])));

        if (is_string($target = $this->option('translate')) && $target !== '') {
            $this->writeTranslation($lines, $rows, $target);
        }

        $needsAttention = count($byStatus[EnvironmentKeyAudit::RENAMED] ?? []) + count($byStatus[EnvironmentKeyAudit::NO_COUNTERPART] ?? []) + count($byStatus[EnvironmentKeyAudit::UNKNOWN] ?? []);

        return $needsAttention === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<array{key: string, status: string, suggestion: ?string}>  $rows
     */
    private function writeTranslation(array $lines, array $rows, string $target): void
    {
        $status = collect($rows)->keyBy('key');
        $out = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\s*)([A-Z][A-Z0-9_]*)(\s*=.*)$/', $line, $match) !== 1) {
                $out[] = $line;

                continue;
            }

            $row = $status->get($match[2]);

            if ($row === null || $row['status'] === EnvironmentKeyAudit::READ) {
                $out[] = $line;
            } elseif ($row['status'] === EnvironmentKeyAudit::RENAMED) {
                $out[] = $match[1].$row['suggestion'].$match[3];
            } else {
                $out[] = '# '.$row['status'].': '.$match[2];
            }
        }

        file_put_contents($target, implode("\n", $out)."\n");
        $this->info("Wrote {$target} (values are copied as they are; keep the file private).");
    }
}
