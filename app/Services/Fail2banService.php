<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class Fail2banService
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     note: ?string,
     *     jail_count: int,
     *     jails: array<int, array{
     *         name: string,
     *         currently_failed: int,
     *         total_failed: int,
     *         currently_banned: int,
     *         total_banned: int,
     *         banned_ips: array<int, string>,
     *         log_files: array<int, string>,
     *         journal_matches: ?string,
     *         raw_output: string
     *     }>
     * }
     */
    public function getStatusSnapshot(): array
    {
        if (! $this->isEnabled()) {
            return [
                'enabled' => false,
                'note' => $this->note(),
                'jail_count' => 0,
                'jails' => [],
            ];
        }

        $overview = $this->runCommand('status');
        $parsedOverview = $this->parseOverview($overview);

        $jails = [];

        foreach ($parsedOverview['jails'] as $jail) {
            $jails[] = $this->parseJailStatus(
                $jail,
                $this->runCommand('jail_status', ['jail' => $jail]),
            );
        }

        return [
            'enabled' => true,
            'note' => $this->note(),
            'jail_count' => $parsedOverview['jail_count'] ?: count($jails),
            'jails' => $jails,
        ];
    }

    public function unban(string $jail, string $ip): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Fail2ban integration is disabled.');
        }

        $normalizedJail = trim($jail);
        $normalizedIp = trim($ip);

        if (! preg_match('/\A[a-zA-Z0-9._-]+\z/', $normalizedJail)) {
            throw new InvalidArgumentException('The provided jail name is invalid.');
        }

        if (filter_var($normalizedIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('The provided IP address is invalid.');
        }

        $this->runCommand('unban', [
            'jail' => $normalizedJail,
            'ip' => $normalizedIp,
        ]);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('fail2ban.enabled', false);
    }

    public function note(): ?string
    {
        $note = trim((string) $this->config->get('fail2ban.note', ''));

        return $note === '' ? null : $note;
    }

    /**
     * @return array{jail_count:int,jails:array<int,string>}
     */
    private function parseOverview(string $output): array
    {
        $jails = $this->splitValueList($this->extractBlockValue($output, 'Jail list'));
        $count = $this->extractIntValue($output, 'Number of jail');

        return [
            'jail_count' => $count > 0 ? $count : count($jails),
            'jails' => $jails,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     currently_failed: int,
     *     total_failed: int,
     *     currently_banned: int,
     *     total_banned: int,
     *     banned_ips: array<int, string>,
     *     log_files: array<int, string>,
     *     journal_matches: ?string,
     *     raw_output: string
     * }
     */
    private function parseJailStatus(string $jail, string $output): array
    {
        $journalMatches = $this->extractBlockValue($output, 'Journal matches');
        $bannedIps = $this->splitValueList($this->extractBlockValue($output, 'Banned IP list'));
        $reportedCurrentlyBanned = $this->extractIntValue($output, 'Currently banned');

        return [
            'name' => $jail,
            'currently_failed' => $this->extractIntValue($output, 'Currently failed'),
            'total_failed' => $this->extractIntValue($output, 'Total failed'),
            // Prefer the parsed active IP list when the reported count drifts.
            'currently_banned' => max($reportedCurrentlyBanned, count($bannedIps)),
            'total_banned' => $this->extractIntValue($output, 'Total banned'),
            'banned_ips' => $bannedIps,
            'log_files' => $this->splitValueList($this->extractBlockValue($output, 'File list')),
            'journal_matches' => $journalMatches !== '' ? $journalMatches : null,
            'raw_output' => $output,
        ];
    }

    private function runCommand(string $configKey, array $replacements = []): string
    {
        $command = trim((string) $this->config->get("fail2ban.commands.{$configKey}", ''));

        if ($command === '') {
            throw new RuntimeException(sprintf('The fail2ban command for [%s] is not configured.', $configKey));
        }

        foreach ($replacements as $key => $value) {
            $command = str_replace(':'.$key, (string) $value, $command);
        }

        $result = Process::timeout((int) $this->config->get('fail2ban.timeout', 10))
            ->run($command);

        if ($result->failed()) {
            $message = trim($result->errorOutput()) !== ''
                ? trim($result->errorOutput())
                : trim($result->output());

            throw new RuntimeException($message !== '' ? $message : 'The fail2ban command failed.');
        }

        return trim($result->output());
    }

    private function extractLineValue(string $output, string $label): string
    {
        if (! preg_match('/'.preg_quote($label, '/').':\s*(.*)$/mi', $output, $matches)) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function extractBlockValue(string $output, string $label): string
    {
        $lines = preg_split('/\r?\n/', $output) ?: [];
        $collecting = false;
        $values = [];

        foreach ($lines as $line) {
            if (! $collecting) {
                if (preg_match('/'.preg_quote($label, '/').':\s*(.*)$/i', $line, $matches)) {
                    $collecting = true;
                    $values[] = trim((string) ($matches[1] ?? ''));
                }

                continue;
            }

            $normalized = ltrim($line, " \t|`-");

            if ($normalized === '') {
                continue;
            }

            if ($this->startsNewStatusSection($normalized)) {
                break;
            }

            $values[] = trim($normalized);
        }

        return trim(implode(' ', array_filter($values, static fn (string $value): bool => $value !== '')));
    }

    private function extractIntValue(string $output, string $label): int
    {
        $value = $this->extractLineValue($output, $label);

        if ($value === '') {
            return 0;
        }

        return (int) preg_replace('/[^\d-]+/', '', $value);
    }

    private function startsNewStatusSection(string $line): bool
    {
        if (preg_match('/^[A-Za-z][^:]*:\s*/', $line) === 1) {
            return true;
        }

        return preg_match('/^(Status(?: for the jail)?|Filter|Actions)$/', $line) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function splitValueList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/[\s,]+/', $value) ?: [],
            static fn (string $item): bool => $item !== ''
        ));
    }
}
