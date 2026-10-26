<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Plugins\PluginLibrary;
use App\Plugins\PluginReviewQueue;
use App\Plugins\PluginSearcher;
use App\Support\CacheMetrics;
use App\Support\MetaDescription;
use App\Support\PluginsIndexCache;
use App\Support\PluginStructuredDataFactory;
use App\Support\StaffFrontendPreview;
use Filagora\Forums\Support\Abilities;
use Filagora\Plugins\Contracts\DownloadAccess;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Models\PluginRelease;
use Filagora\Plugins\Support\Gates;
use Filagora\Plugins\Support\Models;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The public side of the plugin library: the front page and categories, search, a plugin's page,
 * and downloads. What each audience sees is the plugins package's `Visibility`; who may download is
 * its `DownloadAccess`; this builds the pages and answers a refused download the way the site
 * always has (a guest is sent to sign in, someone without the entitlement back to the plugin).
 */
class PluginLibraryController extends Controller
{
    private const CATEGORY_PER_PAGE = 15;

    public function __construct(
        private readonly PluginLibrary $library,
        private readonly PluginSearcher $searcher,
        private readonly DownloadAccess $downloads,
        private readonly PluginReviewQueue $reviewQueue,
        private readonly StaffFrontendPreview $staffPreview,
    ) {}

    public function index(Request $request): View
    {
        $typeSlug = trim((string) $request->query('type', ''));
        $activeType = $typeSlug !== ''
            ? Models::class('type')::query()->where('is_active', true)->where('slug', $typeSlug)->first()
            : null;

        return $this->library($activeType, $request);
    }

    public function byType(string $slug, Request $request): View
    {
        return $this->library(
            Models::class('type')::query()->where('is_active', true)->where('slug', $slug)->firstOrFail(),
            $request,
        );
    }

    public function search(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $zcVersion = trim((string) $request->query('zc_version', ''));
        $typeSlug = trim((string) $request->query('type', ''));
        $encapsulated = $this->library->normalizeEncapsulatedFilter((string) $request->query('encapsulated', 'all'));
        $sort = $this->searcher->normalizeSort((string) $request->query('sort', $query !== '' ? 'relevance' : 'downloads_desc'));

        $activeType = $typeSlug !== ''
            ? Models::class('type')::query()->where('is_active', true)->where('slug', $typeSlug)->first()
            : null;

        $hasCriteria = $query !== '' || $zcVersion !== '' || $activeType !== null || $encapsulated !== 'all';
        $viewer = $request->user() instanceof User ? $request->user() : null;

        $results = $hasCriteria
            ? $this->searcher->paginate($query, $zcVersion, $activeType, $encapsulated, $sort, max(1, (int) $request->query('page', 1)), $viewer)
            : null;

        return view('plugins.search', [
            'query' => $query,
            'results' => $results,
            'zcVersion' => $zcVersion,
            'activeType' => $activeType,
            'encapsulated' => $encapsulated,
            'sort' => $sort,
            'sortOptions' => $this->searcher->sortOptions(),
            'zcVersionOptions' => $this->zcVersionOptions(),
            'typeOptions' => $this->typeOptions(),
            'usedDatabaseFallback' => false,
        ]);
    }

    public function show(Request $request, Plugin $plugin): View
    {
        $user = $request->user() instanceof User ? $request->user() : null;
        $canModerate = $this->canModerate($request, $user);

        abort_unless($this->isPublic($plugin) || $canModerate, 404);

        // Archived releases are hidden from the public list by default; a member opts back in from
        // their profile settings, moderators included. The other non-active statuses stay
        // moderator-only whatever the setting.
        $showArchived = (bool) ($user?->show_archived_plugin_releases ?? false);

        $plugin->load([
            'type',
            'forumThread',
            'owner',
            'latestActiveRelease',
            'releases' => function ($query) use ($canModerate, $showArchived): void {
                if (! $canModerate) {
                    $statuses = [PluginStatus::ACTIVE->value];

                    if ($showArchived) {
                        $statuses[] = PluginStatus::ARCHIVED->value;
                    }

                    $query->whereIn('status', $statuses);
                } elseif (! $showArchived) {
                    $query->where('status', '!=', PluginStatus::ARCHIVED->value);
                }

                $query->with([
                    'zcVersions',
                    'uploadedBy:id,username,name,email',
                    'quarantineScan:id,scanner_version,highest_severity,status',
                ])
                    ->when(
                        $canModerate,
                        fn ($releases) => $releases->with(['latestSecurityScan', 'latestSecurityScan.triggeredByUser:id,email']),
                        fn ($releases) => $releases->with('latestSecurityScan:id,highest_severity,status,finished_at'),
                    )
                    ->orderByDesc('created_at');
            },
        ]);

        // Not $plugin->releases->first(): that list can include archived releases, which can sort newest.
        $latestActiveRelease = $plugin->latestActiveRelease;
        $editableZcVersionsReleaseId = $latestActiveRelease?->id;
        $canEditReleaseZcVersions = $user instanceof User
            && $latestActiveRelease !== null
            && Gate::forUser($user)->allows('updateZcVersions', $latestActiveRelease);

        $description = MetaDescription::fromMarkup($plugin->description);

        return view('plugins.show', [
            'metaDescription' => $description !== ''
                ? $description
                : trim((string) $plugin->name).' - a '.($plugin->type?->name ?: 'plugin').' add-on for Zen Cart.',
            'canonicalUrl' => route('plugins.show', $plugin),
            'structuredData' => $this->isPublic($plugin) ? app(PluginStructuredDataFactory::class)->forPlugin($plugin) : null,
            'plugin' => $plugin,
            'latestActiveRelease' => $latestActiveRelease,
            'activeReleases' => $plugin->releases,
            'canModerate' => $canModerate,
            'canEditReleaseZcVersions' => $canEditReleaseZcVersions,
            'editableZcVersionsReleaseId' => $editableZcVersionsReleaseId,
            'zcVersionOptions' => $canEditReleaseZcVersions
                ? ZcVersion::query()->where('is_active', true)->orderBy('position')->orderByDesc('version')->get()
                : collect(),
            'canSubmitRelease' => $user instanceof User && Gate::forUser($user)->allows('submitRelease', $plugin),
            'downloadEntitlementScope' => $this->downloads->requiredScope($plugin),
            'canDownloadPlugin' => $canModerate || $this->downloads->canDownloadPlugin($user, $plugin),
            'isSubscribed' => $user instanceof User && $plugin->subscribedUsers()->where('user_id', $user->id)->exists(),
            'viewerIsStaff' => $this->viewerIsStaff($request, $user),
        ]);
    }

    public function download(Request $request, Plugin $plugin): BinaryFileResponse|RedirectResponse
    {
        $canModerate = $this->canModerate($request, $request->user() instanceof User ? $request->user() : null);

        abort_unless($this->isPublic($plugin) || $canModerate, 404);

        if (! $canModerate && ($redirect = $this->refuseDownload($request, $plugin))) {
            return $redirect;
        }

        $release = Models::class('release')::query()
            ->where('plugin_id', $plugin->id)
            ->unless($canModerate, fn ($query) => $query->where('status', PluginStatus::ACTIVE->value))
            ->latest('created_at')
            ->first();

        abort_if($release === null, 404);

        return $this->serve($plugin, $release);
    }

    public function downloadRelease(Request $request, Plugin $plugin, PluginRelease $release): BinaryFileResponse|RedirectResponse
    {
        $canModerate = $this->canModerate($request, $request->user() instanceof User ? $request->user() : null);

        abort_unless($this->isPublic($plugin) || $canModerate, 404);
        abort_unless($release->plugin_id === $plugin->id, 404);
        abort_unless(
            $release->status === PluginStatus::ACTIVE
                || ($release->status === PluginStatus::ARCHIVED && (bool) config('filagora-plugins.downloads.archived_releases', true))
                || $canModerate,
            404,
        );

        if (! $canModerate && ($redirect = $this->refuseDownload($request, $plugin))) {
            return $redirect;
        }

        return $this->serve($plugin, $release);
    }

    private function library(?object $activeType, Request $request): View
    {
        $user = $request->user() instanceof User ? $request->user() : null;
        $canModerate = $this->canModerate($request, $user);

        $page = max(1, (int) $request->query('page', 1));
        $categorySort = $this->library->normalizeCategorySort((string) $request->query('sort', 'date_desc'));
        $encapsulatedFilter = $this->library->normalizeEncapsulatedFilter((string) $request->query('encapsulated', 'all'));

        $payload = CacheMetrics::remember(
            'plugins_index',
            PluginsIndexCache::keyFor($activeType?->id, $page, $categorySort, $encapsulatedFilter),
            now()->addSeconds(PluginsIndexCache::ttlSeconds()),
            fn (): array => $this->library->payload($activeType, $page, self::CATEGORY_PER_PAGE, $categorySort, $encapsulatedFilter),
        );

        [$pendingPlugins, $pendingReleases] = $canModerate ? $this->reviewQueue->forModerator() : [collect(), collect()];

        return view('plugins.index', [
            'activeType' => $activeType,
            'typeRows' => is_array($payload['typeRows'] ?? null) ? $payload['typeRows'] : [],
            'categoryListing' => is_array($payload['categoryListing'] ?? null) ? $payload['categoryListing'] : null,
            'latestPlugins' => is_array($payload['latestPlugins'] ?? null) ? $payload['latestPlugins'] : [],
            'topContributors' => is_array($payload['topContributors'] ?? null) ? $payload['topContributors'] : [],
            'canModerate' => $canModerate,
            'pendingPlugins' => $pendingPlugins,
            'pendingReleases' => $pendingReleases,
            'categorySort' => $categorySort,
            'encapsulatedFilter' => $encapsulatedFilter,
            'searchSortOptions' => $this->searcher->sortOptions(),
            'searchZcVersionOptions' => $this->zcVersionOptions(),
            'searchTypeOptions' => $this->typeOptions(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function zcVersionOptions(): array
    {
        return ZcVersion::query()->where('is_active', true)->orderByDesc('position')->orderByDesc('version')->pluck('version')->all();
    }

    private function typeOptions(): Collection
    {
        return Models::class('type')::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(['slug', 'name']);
    }

    private function isPublic(Plugin $plugin): bool
    {
        return $plugin->status === PluginStatus::ACTIVE && (bool) $plugin->type?->is_active;
    }

    private function viewerIsStaff(Request $request, ?User $user): bool
    {
        return $user instanceof User
            && Gate::forUser($user)->any(Abilities::staff())
            && $this->staffPreview->enabledFor($request, $user);
    }

    private function canModerate(Request $request, ?User $user): bool
    {
        return $user instanceof User
            && $this->staffPreview->enabledFor($request, $user)
            && Gate::forUser($user)->allows(Gates::MODERATE);
    }

    /**
     * Answers a download the viewer may not have: nothing for a type that needs no entitlement
     * (anyone may), a sign-in for a guest, and back to the plugin page, with the reason, for a
     * member without the entitlement.
     */
    private function refuseDownload(Request $request, Plugin $plugin): ?RedirectResponse
    {
        $required = $this->downloads->requiredScope($plugin);

        if ($required === '') {
            return null;
        }

        $user = $request->user() instanceof User ? $request->user() : null;

        if ($user === null) {
            return redirect()->route('home', ['auth' => 'login', 'intended' => $request->getRequestUri()]);
        }

        if ($this->downloads->canDownloadPlugin($user, $plugin)) {
            return null;
        }

        return redirect()
            ->route('plugins.show', $plugin)
            ->with('plugin_error', __('plugin.errors.download_entitlement_required', ['entitlement' => $required]));
    }

    private function serve(Plugin $plugin, PluginRelease $release): BinaryFileResponse|RedirectResponse
    {
        $disk = Storage::disk((string) config('filagora-plugins.storage.disk', 'public'));
        $path = ltrim((string) $release->zip_path, '/');

        if ($path === '' || ! $disk->exists($path)) {
            return redirect()->route('plugins.show', $plugin)->with('plugin_error', __('plugin.errors.download_unavailable'));
        }

        // Only count packages the public could have fetched anyway: anything else reached here
        // through the moderation bypass, and a review download should not seed a pending release's tally.
        if ($this->isPublic($plugin) && $release->status === PluginStatus::ACTIVE) {
            $release->increment('download_count');
        }

        return response()->download($disk->path($path), $release->zip_filename ?: basename($path));
    }
}
