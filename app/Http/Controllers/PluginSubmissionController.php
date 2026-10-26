<?php

namespace App\Http\Controllers;

use App\Forum\ThreadListings;
use App\Models\User;
use App\Support\LegacyTextDecoder;
use App\Support\ThreadUrlParser;
use Closure;
use Filagora\Forums\Exceptions\PostingNotPermitted;
use Filagora\Forums\Services\Posting;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Exceptions\ReleaseRefused;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Models\PluginRelease;
use Filagora\Plugins\Release\ReleaseInput;
use Filagora\Plugins\Services\Releases;
use Filagora\Plugins\Support\Models;
use Filagora\ZenCart\Enums\Encapsulated;
use Filagora\ZenCart\Models\ZcVersion;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Submitting a plugin with its first release, a new release of a plugin, and the Zen Cart versions
 * of a release. The package's `Releases` checks the version and the archive and stores the file;
 * this asks the questions, resolves the optional forum support thread, and answers a refusal as a
 * message on the field it concerns.
 */
class PluginSubmissionController extends Controller
{
    public function __construct(
        private readonly Releases $releases,
        private readonly Posting $posting,
        private readonly ThreadListings $threadListings,
    ) {}

    public function submit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $selectedThreadId = (int) old('forum_thread_id', 0);

        return view('plugins.submit', [
            'submissionsBypassModeration' => $this->releases->statusFor($user) === PluginStatus::ACTIVE,
            'types' => Models::class('type')::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(),
            'zcVersions' => $this->zcVersions(),
            'selectedSupportThread' => $selectedThreadId > 0
                ? $this->supportThreads($user)->where('threads.id', $selectedThreadId)->first(['threads.id', 'threads.title'])
                : null,
            'supportForums' => $this->supportForums()->get(['forums.id', 'forums.name']),
        ]);
    }

    /**
     * Typeahead for the "link an existing support thread" picker: threads the user may view in a
     * forum flagged as a plugin support forum.
     */
    public function searchSupportThreads(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['data' => []], 401);
        }

        $text = trim((string) $request->query('q', ''));

        $threads = $this->supportThreads($user)
            ->when($text !== '', fn (Builder $builder) => $builder->where('threads.title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $text).'%'))
            ->reorder()
            ->orderByDesc('threads.updated_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $threads->map(fn ($thread): array => [
                'id' => (int) $thread->id,
                'title' => LegacyTextDecoder::decode((string) $thread->title),
                'forum' => (string) $thread->forum?->name,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $mode = $this->supportThreadMode($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'plugin_type_id' => ['required', 'integer', Rule::exists(Models::class('type'), 'id')],
            'description' => ['nullable', 'string'],
            'github_repository_url' => ['nullable', 'url', 'max:500'],
            'create_support_thread' => ['nullable', 'boolean'],
            'support_thread_mode' => ['nullable', 'in:existing,url,create'],
            'forum_thread_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($user, $mode): void {
                    if ($mode === 'existing' && filled($value) && ! $this->supportThreads($user)->where('threads.id', (int) $value)->exists()) {
                        $fail('The selected forum support thread is invalid.');
                    }
                },
            ],
            'support_thread_url' => [
                Rule::requiredIf(fn (): bool => $mode === 'url'),
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail) use ($user, $mode): void {
                    if ($mode === 'url' && filled($value) && $this->threadIdFromUrl((string) $value, $user) === null) {
                        $fail('That link must point to a thread on this forum that you can view.');
                    }
                },
            ],
            'support_thread_forum_id' => [
                Rule::requiredIf(fn (): bool => $mode === 'create'),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($mode): void {
                    if ($mode === 'create' && filled($value) && ! $this->supportForums()->where('forums.id', (int) $value)->exists()) {
                        $fail('The selected support forum is invalid.');
                    }
                },
            ],
            'support_thread_title' => ['nullable', 'string', 'max:180'],
            'release_version' => ['required', 'string', 'max:40'],
            'release_zip_file' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'release_changelog' => ['nullable', 'string'],
            'release_encapsulated' => ['required', Rule::enum(Encapsulated::class)],
            'release_zc_versions' => ['nullable', 'array'],
            'release_zc_versions.*' => ['integer', Rule::exists(ZcVersion::class, 'id')->where('is_active', true)],
        ]);

        $input = new ReleaseInput(
            version: (string) $validated['release_version'],
            file: $request->file('release_zip_file'),
            changelog: $validated['release_changelog'] ?? null,
            extra: [
                'encapsulated' => $validated['release_encapsulated'],
                'zc_versions' => $validated['release_zc_versions'] ?? [],
            ],
        );

        $threadId = match ($mode) {
            'url' => $this->threadIdFromUrl((string) $validated['support_thread_url'], $user),
            'create' => $this->createSupportThread($user, $validated),
            default => filled($validated['forum_thread_id'] ?? null) ? (int) $validated['forum_thread_id'] : null,
        };

        try {
            $release = $this->releases->submitPlugin($user, [
                'name' => $validated['name'],
                'plugin_type_id' => (int) $validated['plugin_type_id'],
                'description' => $validated['description'] ?? null,
                'forum_thread_id' => $threadId,
                'github_repository_url' => $validated['github_repository_url'] ?? null,
            ], $input);
        } catch (ReleaseRefused $refused) {
            $this->refuse($refused, ['version' => 'release_version', 'zip_file' => 'release_zip_file', 'release' => 'release_zip_file']);
        }

        return redirect()
            ->route('plugins.index')
            ->with('plugin_success', $release->status === PluginStatus::ACTIVE
                ? __('plugin.flash.initial_release_live')
                : __('plugin.flash.initial_release_pending_review'));
    }

    public function newRelease(Request $request, Plugin $plugin): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $this->authorizeRelease($user, $plugin);

        $previous = Models::class('release')::query()
            ->where('plugin_id', $plugin->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return view('plugins.add_release', [
            'plugin' => $plugin,
            'submissionsBypassModeration' => $this->releases->statusFor($user) === PluginStatus::ACTIVE,
            'zcVersions' => $this->zcVersions(),
            'previousReleaseZcVersionIds' => $previous?->zcVersions()->pluck('plugin_zc_versions.id')->all() ?? [],
            'pendingReleases' => $this->releases->pendingFor($plugin),
        ]);
    }

    public function storeRelease(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $this->authorizeRelease($user, $plugin);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40'],
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'changelog' => ['nullable', 'string'],
            'encapsulated' => ['required', Rule::enum(Encapsulated::class)],
            'zc_versions' => ['nullable', 'array'],
            'zc_versions.*' => ['integer', Rule::exists(ZcVersion::class, 'id')->where('is_active', true)],
            'supersede_pending_releases' => ['sometimes', 'boolean'],
        ]);

        try {
            $release = $this->releases->submitRelease($user, $plugin, new ReleaseInput(
                version: (string) $validated['version'],
                file: $request->file('zip_file'),
                changelog: $validated['changelog'] ?? null,
                supersedePending: $request->boolean('supersede_pending_releases'),
                extra: [
                    'encapsulated' => $validated['encapsulated'],
                    'zc_versions' => $validated['zc_versions'] ?? [],
                ],
            ));
        } catch (ReleaseRefused $refused) {
            $this->refuse($refused, ['release' => 'zip_file']);
        }

        return redirect()
            ->route('plugins.show', $plugin)
            ->with('plugin_success', $release->status === PluginStatus::ACTIVE
                ? __('plugin.flash.release_live')
                : __('plugin.flash.release_pending_review'));
    }

    /**
     * Replaces the Zen Cart versions a release works with, without uploading a new release.
     */
    public function updateReleaseZcVersions(Request $request, Plugin $plugin, PluginRelease $release): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        abort_unless($plugin->status === PluginStatus::ACTIVE && $plugin->type?->is_active, 404);
        abort_unless($release->plugin_id === $plugin->id, 404);
        abort_unless(Gate::forUser($user)->allows('updateZcVersions', $release), 403);

        $validated = $request->validate([
            'zc_versions' => ['nullable', 'array'],
            'zc_versions.*' => ['integer', Rule::exists(ZcVersion::class, 'id')->where('is_active', true)],
        ]);

        $release->zcVersions()->sync(array_values(array_unique(array_map('intval', $validated['zc_versions'] ?? []))));

        return redirect()
            ->route('plugins.show', $plugin)
            ->with('plugin_success', __('plugin.flash.release_zc_versions_updated'));
    }

    private function authorizeRelease(User $user, Plugin $plugin): void
    {
        abort_unless($plugin->status === PluginStatus::ACTIVE && $plugin->type?->is_active, 404);
        abort_unless(Gate::forUser($user)->allows('submitRelease', $plugin), 403);
    }

    /**
     * @param  array<string, string>  $fields  the form's own names for the fields a refusal names
     *
     * @throws ValidationException
     */
    private function refuse(ReleaseRefused $refused, array $fields = []): never
    {
        $field = $refused->field();

        throw ValidationException::withMessages([$fields[$field] ?? $field => $refused->getMessage()]);
    }

    private function toLogin(Request $request): RedirectResponse
    {
        return redirect()->route('home', ['auth' => 'login', 'intended' => $request->getRequestUri()]);
    }

    /**
     * @return Collection<int, ZcVersion>
     */
    private function zcVersions()
    {
        return ZcVersion::query()->where('is_active', true)->orderBy('position')->orderByDesc('version')->get();
    }

    private function supportThreadMode(Request $request): string
    {
        $mode = (string) $request->input('support_thread_mode', '');

        if (in_array($mode, ['existing', 'url', 'create'], true)) {
            return $mode;
        }

        return $request->boolean('create_support_thread') ? 'create' : 'existing';
    }

    /**
     * @return Builder<Model>
     */
    private function supportForums(): Builder
    {
        return Models::class('forum')::query()
            ->where('is_plugin_support_forum', true)
            ->where('is_active', true)
            ->orderBy('name');
    }

    /**
     * Threads the user may view that live in a plugin support forum: the pool the typeahead and a
     * pasted link resolve against.
     *
     * @return Builder<Model>
     */
    private function supportThreads(User $user): Builder
    {
        return $this->threadListings->visibleTo($user)->reorder()->where('forums.is_plugin_support_forum', true);
    }

    private function threadIdFromUrl(string $url, User $user): ?int
    {
        $id = ThreadUrlParser::extractThreadId($url);

        return $id !== null && $this->supportThreads($user)->where('threads.id', $id)->exists() ? $id : null;
    }

    /**
     * Starts the plugin's support thread, or reuses the one a double submit already started.
     *
     * @param  array<string, mixed>  $validated
     */
    private function createSupportThread(Authenticatable $user, array $validated): int
    {
        $forum = $this->supportForums()->whereKey((int) $validated['support_thread_forum_id'])->firstOrFail();
        $title = trim((string) ($validated['support_thread_title'] ?? '')) !== '' ? (string) $validated['support_thread_title'] : (string) $validated['name'];

        try {
            return (int) $this->posting->startThread(
                $user,
                $forum,
                $title,
                __('plugin.support_thread.default_body', ['plugin' => $validated['name']]),
            )->thread->getKey();
        } catch (PostingNotPermitted $denied) {
            throw ValidationException::withMessages(['support_thread_forum_id' => $denied->getMessage()]);
        }
    }
}
