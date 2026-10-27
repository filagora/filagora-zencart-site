<?php

namespace App\Console\Commands;

use App\Support\PluginsIndexCache;
use Filagora\Plugins\Support\Models;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WarmPluginsCacheCommand extends Command
{
    protected $signature = 'plugins:warm-cache
        {--flush : Reset plugin index cache version before warming}
        {--max-pages=1 : Number of pages per plugin type to warm (minimum 1)}';

    protected $description = 'Warm cache entries used by the /plugins library endpoint.';

    public function handle(Kernel $kernel): int
    {
        if ((bool) $this->option('flush')) {
            PluginsIndexCache::resetVersion();
            $this->line('Reset plugins index cache version to 1.');
        }

        $maxPages = max(1, (int) $this->option('max-pages'));
        $urls = ['/plugins'];

        $activeTypes = Models::class('type')::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['slug']);

        foreach ($activeTypes as $type) {
            $slug = trim((string) ($type->slug ?? ''));
            if ($slug === '') {
                continue;
            }

            for ($page = 1; $page <= $maxPages; $page++) {
                $urls[] = $page === 1
                    ? '/plugins/type/'.$slug
                    : '/plugins/type/'.$slug.'?page='.$page;
            }
        }

        $frontendMaintenanceWasEnabled = (bool) config('app.frontend_maintenance.enabled', false);
        config()->set('app.frontend_maintenance.enabled', false);

        try {
            foreach ($urls as $url) {
                $request = Request::create($url, 'GET');
                $request->attributes->set('bypass_frontend_maintenance', true);
                $response = $kernel->handle($request);
                $kernel->terminate($request, $response);

                if ($response->getStatusCode() >= Response::HTTP_BAD_REQUEST) {
                    $this->error("Failed to warm URL {$url}. HTTP status: ".$response->getStatusCode());

                    return self::FAILURE;
                }
            }
        } finally {
            config()->set('app.frontend_maintenance.enabled', $frontendMaintenanceWasEnabled);
        }

        $this->info('Plugins cache warmed.');
        $this->line('Cache version: '.PluginsIndexCache::currentVersion());
        $this->line('URLs warmed: '.number_format(count($urls)));

        return self::SUCCESS;
    }
}
