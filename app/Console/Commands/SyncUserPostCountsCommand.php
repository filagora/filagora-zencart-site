<?php

namespace App\Console\Commands;

use App\Services\UserPostCountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserPostCountsCommand extends Command
{
    protected $signature = 'users:sync-post-counts
        {--user-id=* : Limit sync to one or more user IDs}
        {--chunk=500 : Users recomputed per batch}';

    protected $description = 'Rebuild users.forum_posts_count (the thread-page author badge counter) from posts.';

    public function __construct(private readonly UserPostCountService $userPostCountService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $requestedUserIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, (array) $this->option('user-id')),
            static fn (int $id): bool => $id > 0
        )));

        if ($requestedUserIds !== []) {
            $this->userPostCountService->syncForUserIds($requestedUserIds);
            $this->info('Rebuilt post counts for '.number_format(count($requestedUserIds)).' user(s).');

            return self::SUCCESS;
        }

        $total = (int) DB::table('users')->count();

        if ($total === 0) {
            $this->warn('No users found to sync.');

            return self::SUCCESS;
        }

        $this->line('Rebuilding post counts for '.number_format($total).' user(s)...');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = $this->userPostCountService->syncAll(
            (int) $this->option('chunk'),
            static fn (int $count): mixed => $bar->advance($count)
        );

        $bar->finish();
        $this->newLine(2);
        $this->info('Rebuilt post counts for '.number_format($processed).' user(s).');

        return self::SUCCESS;
    }
}
