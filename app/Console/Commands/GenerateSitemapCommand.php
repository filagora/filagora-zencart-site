<?php

namespace App\Console\Commands;

use App\Sitemap\SitemapGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the public sitemap.xml file.';

    public function handle(SitemapGenerator $generator): int
    {
        $path = Storage::disk('public')->path('sitemap.xml');

        $this->info('Generating sitemap...');

        $generator->execute((string) config('app.url'), $path, function (string $message): void {
            $this->line($message);
        });

        $this->info("Sitemap written to {$path}.");

        return self::SUCCESS;
    }
}
