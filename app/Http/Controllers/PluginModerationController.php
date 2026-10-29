<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PluginMailNotification;
use App\Plugins\PluginDeletion;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Entitlements\Services\AuthorizationService;
use Filagora\Plugins\Enums\ModerationAction;
use Filagora\Plugins\Exceptions\ModerationRefused;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Models\PluginRelease;
use Filagora\Plugins\Services\Moderation;
use Filagora\Plugins\Support\Gates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The staff side of the plugin library: approving or rejecting a plugin or release from the
 * queue, deleting content (optionally with the author's other work, and a ban), and letting an
 * author publish without review. The decisions themselves are the package's `Moderation`.
 */
class PluginModerationController extends Controller
{
    public function __construct(
        private readonly Moderation $moderation,
        private readonly PluginDeletion $deletion,
        private readonly AuthorizationService $authorization,
    ) {}

    public function moderate(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $this->staff($request);
        abort_unless(Gate::forUser($user)->allows('moderate', $plugin), 403);

        $validated = $this->validatedDecision($request);
        $action = ModerationAction::from($validated['action']);

        $this->decide(fn () => $this->moderation->moderatePlugin($user, $plugin, $action, $validated['note'] ?? null));

        return $this->backToQueue($action === ModerationAction::APPROVE
            ? __('plugin.moderation.flash.plugin_approved', ['plugin' => $this->label($plugin)])
            : __('plugin.moderation.flash.plugin_rejected'));
    }

    public function moderateRelease(Request $request, Plugin $plugin, PluginRelease $release): RedirectResponse
    {
        $user = $this->staff($request);
        abort_unless($release->plugin_id === $plugin->id, 404);
        abort_unless(Gate::forUser($user)->allows('moderate', $release), 403);

        $validated = $this->validatedDecision($request);
        $action = ModerationAction::from($validated['action']);

        $this->decide(fn () => $this->moderation->moderateRelease($user, $plugin, $release, $action, $validated['note'] ?? null));

        return $this->backToQueue($action === ModerationAction::APPROVE
            ? __('plugin.moderation.flash.release_approved', ['plugin' => $this->label($plugin)])
            : __('plugin.moderation.flash.release_rejected', ['plugin' => $this->label($plugin)]));
    }

    public function destroyPlugin(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $this->staff($request);
        abort_unless(Gate::forUser($user)->allows('delete', $plugin), 403);

        $plugin->loadMissing('owner');
        $options = $this->deletionOptions($request);

        return $this->deleted($this->deletion->deletePlugin($plugin, $user, $options['reason'], $options['all'], $options['ban'], $options['ban_reason']), $plugin->owner);
    }

    public function destroyRelease(Request $request, Plugin $plugin, PluginRelease $release): RedirectResponse
    {
        $user = $this->staff($request);
        abort_unless($release->plugin_id === $plugin->id, 404);
        abort_unless(Gate::forUser($user)->allows('delete', $release), 403);

        $release->loadMissing(['uploadedBy', 'plugin.owner']);
        $author = $release->uploadedBy ?? $release->plugin?->owner;
        $options = $this->deletionOptions($request);

        return $this->deleted($this->deletion->deleteRelease($release, $user, $options['reason'], $options['all'], $options['ban'], $options['ban_reason']), $author);
    }

    public function grantAutoPublishToPluginAuthor(Request $request, Plugin $plugin): RedirectResponse
    {
        $staff = $this->staff($request);
        abort_unless(Gate::forUser($staff)->allows('moderate', $plugin), 403);

        $author = $plugin->owner;

        if (! $author instanceof User) {
            return $this->backToQueue(null, __('plugin.moderation.errors.plugin_author_missing'));
        }

        return $this->grantAutoPublish($staff, $author, 'plugin_author');
    }

    public function grantAutoPublishToReleaseAuthor(Request $request, Plugin $plugin, PluginRelease $release): RedirectResponse
    {
        $staff = $this->staff($request);
        abort_unless($release->plugin_id === $plugin->id, 404);
        abort_unless(Gate::forUser($staff)->allows('moderate', $release), 403);

        $author = $release->uploadedBy instanceof User ? $release->uploadedBy : $plugin->owner;

        if (! $author instanceof User) {
            return $this->backToQueue(null, __('plugin.moderation.errors.release_author_missing'));
        }

        return $this->grantAutoPublish($staff, $author, 'release_author');
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array{action: string, note?: string|null}
     */
    private function validatedDecision(Request $request): array
    {
        return $request->validate([
            'action' => ['required', Rule::in([ModerationAction::APPROVE->value, ModerationAction::REJECT_PERMANENTLY->value])],
            'note' => ['nullable', 'string', 'required_if:action,'.ModerationAction::REJECT_PERMANENTLY->value],
        ]);
    }

    private function decide(\Closure $decision): void
    {
        try {
            $decision();
        } catch (ModerationRefused $refused) {
            throw ValidationException::withMessages(['note' => $refused->getMessage()]);
        }
    }

    /**
     * @return array{reason: ?string, all: bool, ban: bool, ban_reason: ?string}
     */
    private function deletionOptions(Request $request): array
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'remove_all_by_author' => ['sometimes', 'boolean'],
            'ban' => ['sometimes', 'boolean'],
            'ban_reason' => ['nullable', 'string', 'max:4000'],
        ]);

        return [
            'reason' => $validated['reason'] ?? null,
            'all' => $request->boolean('remove_all_by_author'),
            'ban' => $request->boolean('ban'),
            'ban_reason' => $validated['ban_reason'] ?? null,
        ];
    }

    /**
     * @param  array{plugins:int, releases:int, banned:bool}  $result
     */
    private function deleted(array $result, ?User $author): RedirectResponse
    {
        $message = __('plugin.moderation.flash.deleted', ['plugins' => $result['plugins'], 'releases' => $result['releases']]);

        if ($result['banned'] && $author instanceof User) {
            $message .= ' '.__('forum.flash.author_banned', ['name' => $author->username ?: $author->name]);
        }

        return redirect()->route('plugins.index')->with('plugin_success', $message)->with('plugin_keep_queue_open', true);
    }

    private function grantAutoPublish(User $staff, User $author, string $which): RedirectResponse
    {
        $flash = __("plugin.moderation.flash.auto_publish_{$which}_enabled", [
            'author' => $author->username ?: $author->name ?: $author->email ?: __('plugin.moderation.defaults.author'),
        ]);

        $key = (string) (config('filagora-plugins.gates.'.Gates::AUTO_PUBLISH) ?: 'operator.plugin.auto_publish');

        if ($this->authorization->allows($author, $key)) {
            return $this->backToQueue($flash);
        }

        // A new row each time, so an earlier grant and its revocation stay on record.
        UserEntitlementGrant::query()->create([
            'user_id' => $author->id,
            'entitlement_key' => $key,
            'grant_type' => 'manual',
            'granted_by_id' => $staff->id,
            'starts_at' => now(),
            'reason' => 'Granted from plugin moderation queue.',
            'meta' => ['source' => 'plugin_moderation_queue'],
        ]);

        $author->notify(new PluginMailNotification('auto_publish_granted', 'auto_publish_granted', [], 'plugin-auto-publish-granted', ['actionUrl' => route('plugins.index')]));

        return $this->backToQueue($flash);
    }

    private function backToQueue(?string $success, ?string $error = null): RedirectResponse
    {
        $response = back()->with('plugin_keep_queue_open', true);

        return $error !== null ? $response->with('plugin_error', $error) : $response->with('plugin_success', $success);
    }

    private function label(Plugin $plugin): string
    {
        return trim((string) $plugin->name) !== '' ? trim((string) $plugin->name) : 'plugin';
    }
}
