<?php

namespace App\Sitemap;

use Carbon\CarbonInterface;
use Filagora\Cms\Contracts\PageUrlResolver;
use Filagora\Cms\Support\Models as CmsModels;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Support\Models as ForumModels;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Support\Models as PluginModels;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

/**
 * Writes `sitemap.xml`: the published CMS pages, the core public routes, the active plugin types
 * and plugins, and the public forums. A URL appears once, the first time it is added, so a page
 * that is also a core route keeps its real last-modified date.
 */
class SitemapGenerator
{
    /**
     * @var array<string, ?CarbonInterface>
     */
    private array $entries = [];

    public function __construct(private readonly PageUrlResolver $pageUrls) {}

    /**
     * @param  null|callable(string): void  $progress
     */
    public function execute(string $url, string $path, ?callable $progress = null): void
    {
        URL::forceRootUrl($url);
        $this->entries = [];

        $this->report($progress, sprintf('Added %d published CMS pages.', $this->addCmsPages()));
        $this->report($progress, sprintf('Added %d core public routes.', $this->addCoreRoutes()));
        $this->report($progress, sprintf('Added %d active plugin type pages.', $this->addPluginTypes()));
        $this->report($progress, sprintf('Added %d active public plugins.', $this->addPlugins()));
        $this->report($progress, sprintf('Added %d public forums.', $this->addForums()));

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $this->xml());
    }

    private function addCmsPages(): int
    {
        $before = count($this->entries);

        CmsModels::class('page')::query()
            ->published()
            ->orderBy('id')
            ->chunkById(250, function ($pages): void {
                foreach ($pages as $page) {
                    $this->add(trim((string) $page->slug) === 'home' || trim((string) $page->custom_route) === '/' ? route('home') : $this->pageUrls->urlFor($page), $page->updated_at);
                }
            });

        return count($this->entries) - $before;
    }

    /**
     * Core routes with no CMS page of their own. Not /search: robots.txt disallows it, and a
     * sitemap must not list URLs crawlers are told to stay out of.
     */
    private function addCoreRoutes(): int
    {
        $before = count($this->entries);

        foreach ([route('home'), route('forums.index'), route('plugins.index')] as $url) {
            $this->add($url);
        }

        return count($this->entries) - $before;
    }

    private function addPluginTypes(): int
    {
        $before = count($this->entries);

        PluginModels::class('type')::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->chunkById(250, function ($types): void {
                foreach ($types as $type) {
                    $this->add(route('plugins.type', $type->slug));
                }
            });

        return count($this->entries) - $before;
    }

    private function addPlugins(): int
    {
        $before = count($this->entries);

        PluginModels::class('plugin')::query()
            ->where('status', PluginStatus::ACTIVE->value)
            ->whereHas('type', fn ($query) => $query->where('is_active', true))
            ->withMax(['releases as latest_active_release_at' => fn ($query) => $query->where('status', PluginStatus::ACTIVE->value)], 'created_at')
            ->orderBy('id')
            ->chunkById(250, function ($plugins): void {
                foreach ($plugins as $plugin) {
                    $this->add(route('plugins.show', $plugin), $this->pluginLastModified($plugin));
                }
            });

        return count($this->entries) - $before;
    }

    private function addForums(): int
    {
        $before = count($this->entries);

        ForumModels::class('forum')::query()
            ->where('is_active', true)
            ->where('visibility', ForumVisibility::PUBLIC->value)
            ->orderBy('id')
            ->chunkById(250, function ($forums): void {
                foreach ($forums as $forum) {
                    $this->add(route('forums.show', $forum), $forum->last_post_at);
                }
            });

        return count($this->entries) - $before;
    }

    /**
     * The later of the plugin's own last edit and its newest active release, so a new version or a
     * changed description both move the date.
     */
    private function pluginLastModified(object $plugin): ?CarbonInterface
    {
        $releaseAt = $plugin->latest_active_release_at !== null ? Date::parse($plugin->latest_active_release_at) : null;

        if ($plugin->updated_at === null) {
            return $releaseAt;
        }

        return $releaseAt !== null && $releaseAt->greaterThan($plugin->updated_at) ? $releaseAt : $plugin->updated_at;
    }

    private function add(string $url, ?CarbonInterface $lastModified = null): void
    {
        if (! array_key_exists($url, $this->entries)) {
            $this->entries[$url] = $lastModified;
        }
    }

    private function xml(): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($this->entries as $url => $lastModified) {
            $lines[] = '<url><loc>'.htmlspecialchars((string) $url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'
                .($lastModified !== null ? '<lastmod>'.$lastModified->toAtomString().'</lastmod>' : '')
                .'</url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  null|callable(string): void  $progress
     */
    private function report(?callable $progress, string $message): void
    {
        if ($progress !== null) {
            $progress($message);
        }
    }
}
