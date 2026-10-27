<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class NftablesService
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     note: ?string,
     *     table_count: int,
     *     tables: array<int, string>,
     *     chain_count: int,
     *     chains: array<int, string>,
     *     rule_count: int,
     *     set_count: int,
     *     sets: array<int, array{
     *         key: string,
     *         family: string,
     *         table: string,
     *         name: string,
     *         type: ?string,
     *         flags: array<int, string>,
     *         element_count: int,
     *         elements: array<int, array{
     *             key: string,
     *             value: string,
     *             removable: bool
     *         }>
     *     }>
     * }
     */
    public function getStatusSnapshot(): array
    {
        if (! $this->isEnabled()) {
            return [
                'enabled' => false,
                'note' => $this->note(),
                'table_count' => 0,
                'tables' => [],
                'chain_count' => 0,
                'chains' => [],
                'rule_count' => 0,
                'set_count' => 0,
                'sets' => [],
            ];
        }

        return $this->parseRuleset($this->runCommand('ruleset'));
    }

    public function removeElement(string $family, string $table, string $set, string $element): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Nftables integration is disabled.');
        }

        $normalizedFamily = trim($family);
        $normalizedTable = trim($table);
        $normalizedSet = trim($set);
        $normalizedElement = trim($element);

        if (! $this->isSafeIdentifier($normalizedFamily)) {
            throw new InvalidArgumentException('The provided nftables family is invalid.');
        }

        if (! $this->isSafeIdentifier($normalizedTable)) {
            throw new InvalidArgumentException('The provided nftables table is invalid.');
        }

        if (! $this->isSafeIdentifier($normalizedSet)) {
            throw new InvalidArgumentException('The provided nftables set name is invalid.');
        }

        if (! $this->isSafeElementValue($normalizedElement)) {
            throw new InvalidArgumentException('The provided nftables element value is invalid.');
        }

        $this->runCommand('remove_element', [
            'family' => $normalizedFamily,
            'table' => $normalizedTable,
            'set' => $normalizedSet,
            'element' => $normalizedElement,
        ]);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('nftables.enabled', false);
    }

    public function note(): ?string
    {
        $note = trim((string) $this->config->get('nftables.note', ''));

        return $note === '' ? null : $note;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     note: ?string,
     *     table_count: int,
     *     tables: array<int, string>,
     *     chain_count: int,
     *     chains: array<int, string>,
     *     rule_count: int,
     *     set_count: int,
     *     sets: array<int, array{
     *         key: string,
     *         family: string,
     *         table: string,
     *         name: string,
     *         type: ?string,
     *         flags: array<int, string>,
     *         element_count: int,
     *         elements: array<int, array{
     *             key: string,
     *             value: string,
     *             removable: bool
     *         }>
     *     }>
     * }
     */
    private function parseRuleset(string $json): array
    {
        try {
            /** @var array{nftables?: array<int, array<string, mixed>>} $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The nftables ruleset command did not return valid JSON.', previous: $exception);
        }

        $entries = $decoded['nftables'] ?? [];
        $tableCount = 0;
        $tables = [];
        $chainCount = 0;
        $chains = [];
        $ruleCount = 0;
        $sets = [];

        foreach ($entries as $entry) {
            if (isset($entry['table']) && is_array($entry['table'])) {
                $tableCount++;
                $tables[] = $this->formatTableIdentifier($entry['table']);

                continue;
            }

            if (isset($entry['chain']) && is_array($entry['chain'])) {
                $chainCount++;
                $chains[] = $this->formatChainIdentifier($entry['chain']);

                continue;
            }

            if (isset($entry['rule']) && is_array($entry['rule'])) {
                $ruleCount++;

                continue;
            }

            if (isset($entry['set']) && is_array($entry['set'])) {
                $set = $this->normalizeSet($entry['set']);
                $sets[$set['key']] = $set;

                continue;
            }

            if (isset($entry['element']) && is_array($entry['element'])) {
                $this->mergeSetElements($sets, $entry['element']);
            }
        }

        $normalizedSets = array_values($sets);

        usort($normalizedSets, function (array $left, array $right): int {
            return [$left['family'], $left['table'], $left['name']]
                <=> [$right['family'], $right['table'], $right['name']];
        });

        $tables = array_values(array_unique(array_filter($tables)));
        sort($tables);

        $chains = array_values(array_unique(array_filter($chains)));
        sort($chains);

        return [
            'enabled' => true,
            'note' => $this->note(),
            'table_count' => $tableCount,
            'tables' => $tables,
            'chain_count' => $chainCount,
            'chains' => $chains,
            'rule_count' => $ruleCount,
            'set_count' => count($normalizedSets),
            'sets' => $normalizedSets,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     key: string,
     *     family: string,
     *     table: string,
     *     name: string,
     *     type: ?string,
     *     flags: array<int, string>,
     *     element_count: int,
     *     elements: array<int, array{
     *         key: string,
     *         value: string,
     *         removable: bool
     *     }>
     * }
     */
    private function normalizeSet(array $payload): array
    {
        $family = trim((string) ($payload['family'] ?? ''));
        $table = trim((string) ($payload['table'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $elements = $this->normalizeElements($payload['elem'] ?? [], $family, $table, $name);

        return [
            'key' => $this->setKey($family, $table, $name),
            'family' => $family,
            'table' => $table,
            'name' => $name,
            'type' => isset($payload['type']) ? trim((string) $payload['type']) : null,
            'flags' => array_values(array_map(
                static fn (mixed $flag): string => trim((string) $flag),
                is_array($payload['flags'] ?? null) ? $payload['flags'] : []
            )),
            'element_count' => count($elements),
            'elements' => $elements,
        ];
    }

    /**
     * @param  array<string, array{
     *     key: string,
     *     family: string,
     *     table: string,
     *     name: string,
     *     type: ?string,
     *     flags: array<int, string>,
     *     element_count: int,
     *     elements: array<int, array{
     *         key: string,
     *         value: string,
     *         removable: bool
     *     }>
     * }>  $sets
     * @param  array<string, mixed>  $payload
     */
    private function mergeSetElements(array &$sets, array $payload): void
    {
        $family = trim((string) ($payload['family'] ?? ''));
        $table = trim((string) ($payload['table'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $key = $this->setKey($family, $table, $name);
        $elements = $this->normalizeElements($payload['elem'] ?? [], $family, $table, $name);

        if (! isset($sets[$key])) {
            $sets[$key] = [
                'key' => $key,
                'family' => $family,
                'table' => $table,
                'name' => $name,
                'type' => null,
                'flags' => [],
                'element_count' => 0,
                'elements' => [],
            ];
        }

        $merged = collect([...$sets[$key]['elements'], ...$elements])
            ->unique('key')
            ->values()
            ->all();

        $sets[$key]['elements'] = $merged;
        $sets[$key]['element_count'] = count($merged);
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     value: string,
     *     removable: bool
     * }>
     */
    private function normalizeElements(mixed $elements, string $family, string $table, string $name): array
    {
        if (! is_array($elements)) {
            return [];
        }

        $normalized = [];

        foreach ($elements as $index => $element) {
            $formatted = $this->formatElement($element);

            if ($formatted['value'] === '') {
                continue;
            }

            $normalized[] = [
                'key' => sprintf('%s:%s:%s', $this->setKey($family, $table, $name), $index, $formatted['value']),
                'value' => $formatted['value'],
                'removable' => $formatted['removable'],
            ];
        }

        return $normalized;
    }

    /**
     * @return array{value:string,removable:bool}
     */
    private function formatElement(mixed $element): array
    {
        if (is_string($element) || is_int($element) || is_float($element)) {
            $value = trim((string) $element);

            return [
                'value' => $value,
                'removable' => $value !== '' && $this->isSafeElementValue($value),
            ];
        }

        if (! is_array($element)) {
            return ['value' => '', 'removable' => false];
        }

        if (array_key_exists('elem', $element)) {
            return $this->formatElement($element['elem']);
        }

        if (array_key_exists('prefix', $element)) {
            return $this->formatElement($element['prefix']);
        }

        if (array_is_list($element)) {
            $parts = [];
            $removable = true;

            foreach ($element as $part) {
                $formatted = $this->formatElement($part);

                if ($formatted['value'] === '') {
                    continue;
                }

                $parts[] = $formatted['value'];
                $removable = $removable && $formatted['removable'];
            }

            $value = implode(' . ', $parts);

            return [
                'value' => $value,
                'removable' => $value !== '' && $removable && $this->isSafeElementValue($value),
            ];
        }

        try {
            return [
                'value' => json_encode($element, JSON_THROW_ON_ERROR),
                'removable' => false,
            ];
        } catch (JsonException) {
            return ['value' => '', 'removable' => false];
        }
    }

    private function runCommand(string $configKey, array $replacements = []): string
    {
        $command = trim((string) $this->config->get("nftables.commands.{$configKey}", ''));

        if ($command === '') {
            throw new RuntimeException(sprintf('The nftables command for [%s] is not configured.', $configKey));
        }

        foreach ($replacements as $key => $value) {
            $command = str_replace(':'.$key, (string) $value, $command);
        }

        $result = Process::timeout((int) $this->config->get('nftables.timeout', 10))
            ->run($command);

        if ($result->failed()) {
            $message = trim($result->errorOutput()) !== ''
                ? trim($result->errorOutput())
                : trim($result->output());

            throw new RuntimeException($message !== '' ? $message : 'The nftables command failed.');
        }

        return trim($result->output());
    }

    private function setKey(string $family, string $table, string $name): string
    {
        return sprintf('%s:%s:%s', $family, $table, $name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatTableIdentifier(array $payload): string
    {
        $family = trim((string) ($payload['family'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        return trim($family.'/'.$name, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatChainIdentifier(array $payload): string
    {
        $family = trim((string) ($payload['family'] ?? ''));
        $table = trim((string) ($payload['table'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        return trim($family.'/'.$table.'/'.$name, '/');
    }

    private function isSafeIdentifier(string $value): bool
    {
        return preg_match('/\A[a-zA-Z0-9_.:-]+\z/', $value) === 1;
    }

    private function isSafeElementValue(string $value): bool
    {
        return preg_match('/\A[a-zA-Z0-9:._\/-]+(?:\s+\.\s+[a-zA-Z0-9:._\/-]+)*\z/', $value) === 1;
    }
}
