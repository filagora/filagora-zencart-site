<?php

namespace App\Plugins;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Search\PluginRanking;
use Filagora\Plugins\Search\PluginSearch;
use Filagora\Plugins\Services\Visibility;
use Filagora\Plugins\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The plugin library's search page: words, a Zen Cart version, a type and the encapsulated filter,
 * in four orders. Words are matched by the plugins package's search (every word in any field, the
 * best match first); with no words the library is browsed straight from the database so a broad
 * filter is never cut short.
 */
class PluginSearcher
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly PluginSearch $search,
        private readonly PluginRanking $ranking,
        private readonly Visibility $visibility,
        private readonly PluginLibrary $library,
    ) {}

    /**
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return [
            'relevance' => 'Best match',
            'downloads_desc' => 'Most downloaded',
            'updated_desc' => 'Recently updated',
            'name_asc' => 'Name (A-Z)',
        ];
    }

    public function normalizeSort(string $sort): string
    {
        return array_key_exists($sort, $this->sortOptions()) ? $sort : 'relevance';
    }

    /**
     * @return LengthAwarePaginator<int, Plugin>
     */
    public function paginate(string $text, string $zcVersion, ?object $type, string $encapsulated, string $sort, int $page, ?User $viewer): LengthAwarePaginator
    {
        $results = $text !== ''
            ? $this->matched($text, $zcVersion, $type, $encapsulated, $sort, $page, $viewer)
            : $this->browsed($zcVersion, $type, $encapsulated, $sort, $page, $viewer);

        $this->decorate($results);

        return $results;
    }

    /**
     * @return LengthAwarePaginator<int, Plugin>
     */
    private function matched(string $text, string $zcVersion, ?object $type, string $encapsulated, string $sort, int $page, ?User $viewer): LengthAwarePaginator
    {
        $ranked = $this->search->ranked($text, $viewer, $type?->id, $zcVersion);

        if ($encapsulated !== 'all') {
            $ranked = $ranked->filter(fn ($plugin): bool => $this->latestEncapsulation($plugin) === $encapsulated)->values();
        }

        if ($sort !== 'relevance' || $this->ranking->tokens($text) === []) {
            $ranked = $this->ordered($ranked, $sort === 'relevance' ? 'downloads_desc' : $sort);
        }

        return new LengthAwarePaginator(
            $ranked->forPage($page, self::PER_PAGE)->values(),
            $ranked->count(),
            self::PER_PAGE,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->except('page')],
        );
    }

    /**
     * @param  Collection<int, Plugin>  $plugins
     * @return Collection<int, Plugin>
     */
    private function ordered(Collection $plugins, string $sort): Collection
    {
        $downloads = fn ($plugin): int => (int) $plugin->releases->where('status', PluginStatus::ACTIVE)->sum('download_count');
        $updated = fn ($plugin): int => (int) ($plugin->releases->max(fn ($release) => ($release->approved_at ?? $release->created_at)?->timestamp) ?? 0);

        return match ($sort) {
            'updated_desc' => $plugins->sortByDesc($updated)->values(),
            'name_asc' => $plugins->sortBy(fn ($plugin): string => mb_strtolower((string) $plugin->name))->values(),
            default => $plugins->sortByDesc($downloads)->values(),
        };
    }

    /**
     * @return LengthAwarePaginator<int, Plugin>
     */
    private function browsed(string $zcVersion, ?object $type, string $encapsulated, string $sort, int $page, ?User $viewer): LengthAwarePaginator
    {
        $builder = $this->visibility->publicPlugins($viewer, $type?->slug)
            ->whereHas('releases', fn (Builder $releases) => $releases->where('status', PluginStatus::ACTIVE->value));

        if ($zcVersion !== '') {
            $builder->whereHas('releases', fn (Builder $releases) => $releases
                ->where('status', PluginStatus::ACTIVE->value)
                ->whereHas('zcVersions', fn (Builder $versions) => $versions->where('version', $zcVersion)));
        }

        if ($encapsulated !== 'all') {
            $this->library->applyEncapsulatedFilter($builder, $encapsulated);
        }

        $builder
            ->withSum(['releases as plugin_search_downloads' => fn (Builder $releases) => $releases->where('status', PluginStatus::ACTIVE->value)], 'download_count')
            ->withMax(['releases as plugin_search_latest_release_at' => fn (Builder $releases) => $releases->where('status', PluginStatus::ACTIVE->value)], 'approved_at');

        match ($sort) {
            'updated_desc' => $builder->orderByDesc('plugin_search_latest_release_at')->orderByDesc('id'),
            'name_asc' => $builder->orderByRaw('LOWER(name)')->orderBy('id'),
            default => $builder->orderByDesc('plugin_search_downloads')->orderByDesc('id'),
        };

        return $builder->paginate(self::PER_PAGE, ['*'], 'page', $page)->withQueryString();
    }

    private function latestEncapsulation(object $plugin): string
    {
        $latest = $plugin->releases->where('status', PluginStatus::ACTIVE)->sortByDesc('created_at')->first();
        $value = $latest?->encapsulated;

        return $value instanceof \BackedEnum ? (string) $value->value : (is_string($value) ? $value : 'unknown');
    }

    /**
     * Loads each card's downloads, latest release date, packaging and Zen Cart versions onto the
     * current page, so the same view renders whichever way the rows were found.
     *
     * @param  LengthAwarePaginator<int, Plugin>  $results
     */
    private function decorate(LengthAwarePaginator $results): void
    {
        $plugins = $results->getCollection();

        if ($plugins->isEmpty()) {
            return;
        }

        $plugins->loadMissing('type:id,name,slug');
        $pluginIds = $plugins->pluck('id')->all();
        $pluginModel = Models::class('plugin');
        $releaseModel = Models::class('release');

        $aggregates = $pluginModel::query()
            ->whereIn('id', $pluginIds)
            ->tap(fn (Builder $query) => $this->library->selectLatestReleaseEncapsulation($query))
            ->withSum(['releases as card_downloads' => fn (Builder $releases) => $releases->where('status', PluginStatus::ACTIVE->value)], 'download_count')
            ->addSelect(['card_latest_release_at' => $releaseModel::query()
                ->selectRaw('MAX(COALESCE(approved_at, created_at))')
                ->whereColumn('plugin_id', 'plugins.id')
                ->where('status', PluginStatus::ACTIVE->value)])
            ->get(['id'])
            ->keyBy('id');

        $zcVersionsByPlugin = DB::table('plugin_release_zc_versions')
            ->join('plugin_releases', 'plugin_releases.id', '=', 'plugin_release_zc_versions.plugin_release_id')
            ->join('plugin_zc_versions', 'plugin_zc_versions.id', '=', 'plugin_release_zc_versions.plugin_zc_version_id')
            ->whereIn('plugin_releases.plugin_id', $pluginIds)
            ->where('plugin_releases.status', PluginStatus::ACTIVE->value)
            ->orderByDesc('plugin_zc_versions.position')
            ->orderByDesc('plugin_zc_versions.version')
            ->get(['plugin_releases.plugin_id as plugin_id', 'plugin_zc_versions.version as version'])
            ->groupBy('plugin_id')
            ->map(fn ($rows) => $rows->pluck('version')->unique()->values()->all());

        $plugins->each(function ($plugin) use ($aggregates, $zcVersionsByPlugin): void {
            $row = $aggregates->get($plugin->id);
            $encapsulated = is_string($row?->latest_release_encapsulated ?? null) ? (string) $row->latest_release_encapsulated : 'unknown';

            $plugin->setAttribute('card_downloads', (int) ($row?->card_downloads ?? 0));
            $plugin->setAttribute('card_latest_release_at', $row?->card_latest_release_at);
            $plugin->setAttribute('card_encapsulated', $encapsulated);
            $plugin->setAttribute('card_encapsulated_label', $this->library->encapsulationLabel($encapsulated));
            $plugin->setAttribute('card_zc_versions', $zcVersionsByPlugin->get($plugin->id, []));
        });
    }
}
