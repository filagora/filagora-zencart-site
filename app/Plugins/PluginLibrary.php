<?php

namespace App\Plugins;

use App\Models\LegacyImportUserMap;
use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Support\Models;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The public plugin library's front page and category listings: the types with their counts, the
 * latest plugins, a category's paged list with its sorts and the encapsulated filter, and the top
 * contributors. One payload per type, page, sort and filter, which the controller caches.
 */
class PluginLibrary
{
    /**
     * @return array{
     *   typeRows: list<array{
     *      type:array{id:int,slug:string,name:string},
     *      label:string,
     *      description:string,
     *      count:int
     *   }>,
     *   categoryListing: ?array{
     *      items:list<array{
     *          id:int,
     *          name:string,
     *          slug:string,
     *          description:string,
     *          encapsulated:string,
     *          encapsulated_label:string,
     *          updated_at:string|null,
     *          updated_at_display:string,
     *          releases_count:int
     *      }>,
     *      total:int,
     *      page:int,
     *      perPage:int,
     *      pages:int
     *   },
     *   latestPlugins:list<array{
     *      id:int,
     *      name:string,
     *      slug:string,
     *      encapsulated:string,
     *      encapsulated_label:string,
     *      updated_at:string|null,
     *      sidebar_date:string|null,
     *      sidebar_date_display:string
     *   }>,
     *   topContributors:list<array{label:string,count:int,user_id:int|null}>
     * }
     */
    public function payload(
        ?object $activeType,
        int $page,
        int $perPage,
        string $categorySort,
        string $encapsulatedFilter
    ): array {
        $activeTypeId = $activeType?->id;

        $typeCounts = Models::class('plugin')::query()
            ->where('status', PluginStatus::ACTIVE->value)
            ->selectRaw('plugin_type_id, COUNT(*) as aggregate')
            ->groupBy('plugin_type_id')
            ->pluck('aggregate', 'plugin_type_id')
            ->mapWithKeys(fn ($count, $typeId): array => [(int) $typeId => (int) $count])
            ->all();

        $typeRows = Models::class('type')::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(function ($type) use ($typeCounts): array {
                return [
                    'type' => [
                        'id' => (int) $type->id,
                        'slug' => (string) $type->slug,
                        'name' => (string) $type->name,
                    ],
                    'label' => (string) $type->name,
                    'description' => (string) ($type->description ?? ''),
                    'count' => (int) ($typeCounts[(int) $type->id] ?? 0),
                ];
            })
            ->all();

        $latestPlugins = Models::class('plugin')::query()
            ->where('status', PluginStatus::ACTIVE->value)
            ->whereHas('type', fn ($query) => $query->where('is_active', true))
            ->tap(fn ($query) => $this->selectLatestReleaseEncapsulation($query))
            ->withMax([
                'releases as latest_release_at' => fn ($query) => $query->where('status', PluginStatus::ACTIVE->value),
            ], 'approved_at')
            ->when(
                is_int($activeTypeId),
                fn ($query) => $query->where('plugin_type_id', $activeTypeId)
            )
            ->when(
                $encapsulatedFilter !== 'all',
                fn ($query) => $this->applyEncapsulatedFilter($query, $encapsulatedFilter)
            )
            ->orderByDesc('latest_release_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'updated_at'])
            ->map(function ($plugin): array {
                $sidebarDate = $plugin->latest_release_at !== null
                    ? Carbon::parse((string) $plugin->latest_release_at)
                    : $plugin->updated_at;
                $encapsulation = is_string($plugin->latest_release_encapsulated ?? null)
                    ? (string) $plugin->latest_release_encapsulated
                    : 'unknown';

                return [
                    'id' => (int) $plugin->id,
                    'name' => (string) $plugin->name,
                    'slug' => (string) $plugin->slug,
                    'encapsulated' => $encapsulation,
                    'encapsulated_label' => $this->encapsulationLabel($encapsulation),
                    'updated_at' => $plugin->updated_at?->toISOString(),
                    'sidebar_date' => $sidebarDate?->toISOString(),
                    'sidebar_date_display' => $sidebarDate?->format('d M Y') ?? '',
                ];
            })
            ->all();

        $categoryListing = null;
        if (is_int($activeTypeId)) {
            $query = Models::class('plugin')::query()
                ->where('status', PluginStatus::ACTIVE->value)
                ->where('plugin_type_id', $activeTypeId)
                ->tap(fn ($query) => $this->selectLatestReleaseEncapsulation($query))
                ->withMax([
                    'releases as latest_release_at' => fn ($releaseQuery) => $releaseQuery->where('status', PluginStatus::ACTIVE->value),
                ], 'approved_at')
                ->withCount('releases');

            if ($encapsulatedFilter !== 'all') {
                $this->applyEncapsulatedFilter($query, $encapsulatedFilter);
            }

            $this->applyCategorySort($query, $categorySort);

            $total = (clone $query)->count();
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $items = $query
                ->forPage($page, $perPage)
                ->get(['id', 'name', 'slug', 'description', 'updated_at'])
                ->map(function ($plugin): array {
                    $displayDate = $plugin->latest_release_at !== null
                        ? Carbon::parse((string) $plugin->latest_release_at)
                        : $plugin->updated_at;
                    $encapsulation = is_string($plugin->latest_release_encapsulated ?? null)
                        ? (string) $plugin->latest_release_encapsulated
                        : 'unknown';

                    return [
                        'id' => (int) $plugin->id,
                        'name' => (string) $plugin->name,
                        'slug' => (string) $plugin->slug,
                        'description' => Str::limit((string) ($plugin->description ?? ''), 300),
                        'encapsulated' => $encapsulation,
                        'encapsulated_label' => $this->encapsulationLabel($encapsulation),
                        'updated_at' => $plugin->updated_at?->toISOString(),
                        'updated_at_display' => $displayDate?->format('d M Y') ?? '',
                        'releases_count' => (int) ($plugin->releases_count ?? 0),
                    ];
                })
                ->all();

            $categoryListing = [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'pages' => $pages,
            ];
        }

        return [
            'typeRows' => $typeRows,
            'categoryListing' => $categoryListing,
            'latestPlugins' => $latestPlugins,
            'topContributors' => $this->topContributors(),
        ];
    }

    public function normalizeCategorySort(string $sort): string
    {
        return in_array($sort, ['date_desc', 'date_asc', 'name_asc', 'name_desc'], true)
            ? $sort
            : 'date_desc';
    }

    public function normalizeEncapsulatedFilter(string $encapsulated): string
    {
        return in_array($encapsulated, ['all', 'yes', 'no', 'unknown'], true)
            ? $encapsulated
            : 'all';
    }

    private function applyCategorySort($query, string $sort): void
    {
        match ($sort) {
            'date_asc' => $query
                ->orderBy('latest_release_at')
                ->orderBy('updated_at')
                ->orderBy('created_at')
                ->orderBy('id'),
            'name_asc' => $query
                ->orderByRaw('LOWER(name)')
                ->orderBy('id'),
            'name_desc' => $query
                ->orderByRaw('LOWER(name) DESC')
                ->orderByDesc('id'),
            default => $query
                ->orderByDesc('latest_release_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        };
    }

    public function applyEncapsulatedFilter($query, string $encapsulated): void
    {
        $query->whereRaw(
            '(select encapsulated from plugin_releases where plugin_releases.plugin_id = plugins.id and plugin_releases.status = ? order by plugin_releases.created_at desc, plugin_releases.id desc limit 1) = ?',
            [PluginStatus::ACTIVE->value, $encapsulated]
        );
    }

    public function encapsulationLabel(string $encapsulated): string
    {
        return match ($encapsulated) {
            'yes' => 'Encapsulated',
            'no' => 'Not encapsulated',
            default => 'Encapsulation unknown',
        };
    }

    public function selectLatestReleaseEncapsulation($query): void
    {
        $query->addSelect([
            'latest_release_encapsulated' => Models::class('release')::query()
                ->select('encapsulated')
                ->whereColumn('plugin_id', 'plugins.id')
                ->where('status', PluginStatus::ACTIVE->value)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(1),
        ]);
    }

    /**
     * @return list<array{label:string,count:int,user_id:int|null}>
     */
    private function topContributors(): array
    {
        $ownerCounts = Models::class('plugin')::query()
            ->where('status', PluginStatus::ACTIVE->value)
            ->whereNotNull('owner_id')
            ->select('owner_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('owner_id')
            ->pluck('aggregate', 'owner_id')
            ->mapWithKeys(static fn ($count, $userId): array => [(int) $userId => (int) $count])
            ->all();

        $uploaderCounts = Models::class('release')::query()
            ->where('status', PluginStatus::ACTIVE->value)
            ->whereNotNull('uploaded_by_id')
            ->select('uploaded_by_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('uploaded_by_id')
            ->pluck('aggregate', 'uploaded_by_id')
            ->mapWithKeys(static fn ($count, $userId): array => [(int) $userId => (int) $count])
            ->all();

        $combinedCounts = [];
        foreach ([$ownerCounts, $uploaderCounts] as $dataset) {
            foreach ($dataset as $userId => $count) {
                $userId = (int) $userId;
                if ($userId <= 0) {
                    continue;
                }

                $combinedCounts[$userId] = (int) ($combinedCounts[$userId] ?? 0) + (int) $count;
            }
        }

        if ($combinedCounts === []) {
            return [];
        }

        arsort($combinedCounts);
        $topCounts = array_slice($combinedCounts, 0, 10, true);
        $topUserIds = array_keys($topCounts);

        $legacyUsernameByUserId = LegacyImportUserMap::query()
            ->whereIn('user_id', $topUserIds)
            ->whereNotNull('legacy_username')
            ->pluck('legacy_username', 'user_id')
            ->mapWithKeys(static fn ($username, $userId): array => [(int) $userId => trim((string) $username)])
            ->all();

        $userLabelById = User::query()
            ->whereIn('id', $topUserIds)
            ->get(['id', 'username', 'name', 'email'])
            ->mapWithKeys(static function (User $user): array {
                $username = trim((string) ($user->username ?? ''));
                $name = trim((string) ($user->name ?? ''));
                $email = trim((string) ($user->email ?? ''));

                return [
                    (int) $user->id => $username !== ''
                        ? $username
                        : ($name !== '' ? $name : ($email !== '' ? $email : 'User #'.(int) $user->id)),
                ];
            })
            ->all();

        return collect($topCounts)
            ->map(static function (int $count, int $userId) use ($legacyUsernameByUserId, $userLabelById): array {
                $legacyUsername = trim((string) ($legacyUsernameByUserId[$userId] ?? ''));
                $label = $legacyUsername !== ''
                    ? $legacyUsername
                    : (string) ($userLabelById[$userId] ?? ('User #'.$userId));

                return [
                    'label' => $label,
                    'count' => $count,
                    'user_id' => $userId,
                ];
            })
            ->values()
            ->all();
    }
}
