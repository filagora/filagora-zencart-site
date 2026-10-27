<?php

namespace App\Console\Commands;

use App\Sanitiser\Sanitiser;
use App\Sanitiser\UnclassifiedTablesException;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class SanitiseDatabaseCommand extends Command
{
    protected $signature = 'data:sanitise
        {database : The scratch database holding a restored copy of production}
        {--check : Only report tables the policy does not name; change nothing}
        {--password=password : The password every account gets}
        {--yes : Skip the confirmation}';

    protected $description = 'Replaces personal data in a scratch copy of production so it can leave the server.';

    public function handle(): int
    {
        $database = (string) $this->argument('database');
        $configured = (string) config('database.connections.'.config('database.default').'.database');

        if ($database === $configured) {
            $this->error("Refusing to touch [{$database}]: it is the database this application is configured to use. Restore the backup into a separate scratch database and name that one.");

            return self::FAILURE;
        }

        $sanitiser = new Sanitiser($this->connectionTo($database), (string) $this->option('password'));
        $unclassified = $sanitiser->unclassifiedTables();

        if ($unclassified !== []) {
            $this->error('The policy (app/Sanitiser/SanitiserPolicy.php) does not name these tables:');
            $this->line('  '.implode("\n  ", $unclassified));

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('Every table in ['.$database.'] is covered by the policy.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm("This rewrites [{$database}] irreversibly. Is it a scratch copy?")) {
            return self::FAILURE;
        }

        try {
            $report = $sanitiser->run();
        } catch (UnclassifiedTablesException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $violations = $sanitiser->violations();

        if ($violations !== []) {
            $this->error('The copy is not clean; do not export it:');
            $this->line('  '.implode("\n  ", $violations));

            return self::FAILURE;
        }

        $counts = array_count_values($report);
        $this->info(sprintf(
            'Sanitised [%s]: %d tables kept, %d scrubbed, %d purged. Every account now has the password [%s] and an address at example.test.',
            $database,
            $counts['keep'] ?? 0,
            ($counts['scrub'] ?? 0) + ($counts['settings'] ?? 0),
            $counts['purge'] ?? 0,
            (string) $this->option('password'),
        ));

        return self::SUCCESS;
    }

    private function connectionTo(string $database): Connection
    {
        $name = config('database.default');
        config(['database.connections.sanitise' => [...config("database.connections.{$name}"), 'database' => $database]]);
        DB::purge('sanitise');

        return DB::connection('sanitise');
    }
}
