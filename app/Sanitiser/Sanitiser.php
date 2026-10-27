<?php

namespace App\Sanitiser;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Hash;

/**
 * Applies {@see SanitiserPolicy} to a copy of the production database.
 *
 * It rewrites whatever connection it is given and nothing guards that here: the command that
 * runs it refuses the application's own database.
 */
class Sanitiser
{
    public const EMAIL_DOMAIN = 'example.test';

    public const IP_ADDRESS = '203.0.113.1';

    public function __construct(
        private readonly Connection $db,
        private readonly string $password = 'password',
        private readonly bool $useTruncate = true,
    ) {}

    /**
     * Tables in the database that the policy does not name.
     *
     * @return list<string>
     */
    public function unclassifiedTables(): array
    {
        return array_values(array_filter(
            $this->tables(),
            fn (string $table): bool => SanitiserPolicy::treatmentFor($table) === null,
        ));
    }

    /**
     * Rewrites every table. Returns what was done, by table.
     *
     * @return array<string, string>
     */
    public function run(): array
    {
        $unclassified = $this->unclassifiedTables();

        if ($unclassified !== []) {
            throw new UnclassifiedTablesException($unclassified);
        }

        $report = [];
        $this->db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table) {
                $treatment = SanitiserPolicy::treatmentFor($table);

                match ($treatment) {
                    'purge' => $this->purge($table),
                    'scrub' => $this->scrub($table),
                    'settings' => $this->scrubSettings(),
                    default => null,
                };

                $report[$table] = (string) $treatment;
            }
        } finally {
            $this->db->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $report;
    }

    /**
     * Rows that still hold what the policy should have replaced. Empty when the copy is clean.
     *
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (SanitiserPolicy::SCRUB as $table => $definition) {
            if (! in_array($table, $this->tables(), true)) {
                continue;
            }

            foreach ($definition['columns'] as $column => $rule) {
                $kind = explode(':', $rule)[0];
                $wrapped = $this->db->getQueryGrammar()->wrap($column);

                $leftover = match ($kind) {
                    'email' => $this->db->table($table)->whereRaw("{$wrapped} NOT LIKE ?", ['%@'.self::EMAIL_DOMAIN])->count(),
                    'ip' => $this->db->table($table)->whereRaw("{$wrapped} <> ?", [self::IP_ADDRESS])->count(),
                    'password' => max(0, $this->db->table($table)->distinct()->count($column) - 1),
                    'clear', 'blank' => $this->db->table($table)->whereRaw("{$wrapped} IS NOT NULL AND {$wrapped} <> ''")->count(),
                    default => 0,
                };

                if ($leftover > 0) {
                    $violations[] = "{$table}.{$column}: {$leftover} row(s) not sanitised";
                }
            }
        }

        foreach ($this->tables() as $table) {
            if (SanitiserPolicy::treatmentFor($table) === 'purge' && $this->db->table($table)->exists()) {
                $violations[] = "{$table}: not empty";
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return array_values(array_map(
            fn (array $table): string => $table['name'],
            $this->db->getSchemaBuilder()->getTables($this->db->getDatabaseName()),
        ));
    }

    private function purge(string $table): void
    {
        $this->useTruncate ? $this->db->table($table)->truncate() : $this->db->table($table)->delete();
    }

    private function scrub(string $table): void
    {
        $definition = SanitiserPolicy::SCRUB[$table];
        $columns = $this->columnInfo($table);
        $updates = [];

        foreach ($definition['columns'] as $column => $rule) {
            if (! isset($columns[$column])) {
                continue;
            }

            $updates[$column] = $this->replacement($column, $rule, $definition['key'], $columns[$column]['nullable']);
        }

        if ($updates !== []) {
            $this->vacateIdentityColumns($table, $definition);
            $this->db->table($table)->update($updates);
        }
    }

    /**
     * Moves every column that will get a generated name out of the way first. A real member
     * may already hold "user17", which would collide with the generated name of account 17
     * partway through the update.
     *
     * @param  array{key: string, columns: array<string, string>}  $definition
     */
    private function vacateIdentityColumns(string $table, array $definition): void
    {
        $grammar = $this->db->getQueryGrammar();
        $temporary = [];

        foreach ($definition['columns'] as $column => $rule) {
            if (in_array(explode(':', $rule)[0], ['email', 'username', 'name'], true)) {
                $temporary[$column] = new Expression("CONCAT('~', ".$grammar->wrap(explode(':', $rule)[1] ?? $definition['key']).')');
            }
        }

        if ($temporary !== []) {
            $this->db->table($table)->update($temporary);
        }
    }

    private function scrubSettings(): void
    {
        $key = $this->db->getQueryGrammar()->wrap('key');

        $this->db->table('system_settings')
            ->whereRaw("LOWER({$key}) REGEXP ?", [SanitiserPolicy::SECRET_SETTING_PATTERN])
            ->update(['value' => $this->columnInfo('system_settings')['value']['nullable'] ? null : '']);
    }

    private function replacement(string $column, string $rule, string $key, bool $nullable): mixed
    {
        [$kind, $source] = array_pad(explode(':', $rule, 2), 2, $key);
        $grammar = $this->db->getQueryGrammar();
        $source = $grammar->wrap($source);
        $self = $grammar->wrap($column);

        return match ($kind) {
            'email' => new Expression("CONCAT('user', {$source}, '@".self::EMAIL_DOMAIN."')"),
            'username' => new Expression("CONCAT('user', {$source})"),
            'name' => new Expression("CONCAT('Member ', {$source})"),
            'password' => Hash::make($this->password),
            'ip' => self::IP_ADDRESS,
            'blank' => '',
            'mask' => new Expression("IF({$self} IS NULL OR {$self} = '', {$self}, 'Sanitised')"),
            default => $nullable ? null : '',
        };
    }

    /**
     * @return array<string, array{nullable: bool}>
     */
    private function columnInfo(string $table): array
    {
        $columns = [];

        foreach ($this->db->getSchemaBuilder()->getColumns($table) as $column) {
            $columns[$column['name']] = ['nullable' => (bool) $column['nullable']];
        }

        return $columns;
    }
}
