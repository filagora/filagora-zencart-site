<?php

namespace App\Profile;

use App\Api\ApiScopeCatalog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Entitlements\Models\EntitlementDefinition;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Services\EntitlementResolver;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\Memberships\Services\Memberships;
use Filagora\Notifications\Services\Preferences;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

/**
 * What the profile page shows for a member and a chosen tab. Each tab's data is only built when
 * that tab is open, except the subscription lists, which the page always shows.
 */
class ProfilePage
{
    private const TABS = ['details', 'password', 'settings', 'avatar', 'two-factor', 'api-keys', 'subscriptions', 'billing', 'entitlements'];

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly PolicyAcceptance $policy,
        private readonly Preferences $preferences,
        private readonly ApiScopeCatalog $apiScopes,
        private readonly EntitlementResolver $entitlementResolver,
        private readonly Memberships $memberships,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, string $requestedTab): array
    {
        $privacy = $this->policy->stateFor($user);
        $gdprEnforced = (bool) $privacy['enforcementEnabled'];
        $tab = in_array($requestedTab, [...self::TABS, ...($gdprEnforced ? ['privacy'] : [])], true) ? $requestedTab : 'details';

        $privacyRequests = $gdprEnforced ? $this->privacyRequests($user) : null;

        return [
            'activeTab' => $tab,
            'timezoneOptions' => $this->timezoneOptions($user->timezone),
            'notificationMatrix' => $this->preferences->matrix($user),
            'twoFactorSetup' => $this->twoFactorSetup($user),
            'newRecoveryCodes' => session('new_recovery_codes'),
            'recoveryCodesRemainingCount' => $user->recoveryCodesRemainingCount(),
            'threadSubscriptions' => $user->subscribedThreads()
                ->with(['forum:id,name,slug', 'lastPostAuthor:id,username,name,email'])
                ->orderByRaw('COALESCE(threads.last_post_at, threads.created_at) desc')
                ->orderBy('thread_subscriptions.id', 'desc')
                ->paginate(20, ['*'], 'thread_page')
                ->withQueryString(),
            'pluginSubscriptions' => $user->subscribedPlugins()
                ->with(['type:id,name,slug'])
                ->orderByPivot('created_at', 'desc')
                ->orderBy('plugin_subscriptions.id', 'desc')
                ->paginate(20, ['*'], 'plugin_page')
                ->withQueryString(),
            'effectiveEntitlements' => in_array($tab, ['billing', 'entitlements'], true) ? $this->entitlementResolver->entitlementsFor($user) : [],
            ...($tab === 'billing' ? $this->billingTab($user) : []),
            ...($tab === 'entitlements' ? $this->entitlementsTab($user) : []),
            ...($tab === 'api-keys' ? $this->apiKeysTab($user) : ['apiTokens' => collect(), 'availableApiScopes' => [], 'apiTokenAbilityLabels' => []]),
            'newApiToken' => session('new_api_token'),
            'privacyRequests' => $privacyRequests,
            'privacyExportDownloadUrls' => $privacyRequests !== null ? $this->exportUrls($privacyRequests->getCollection()) : [],
            'privacyRequestTypes' => PrivacyRequestType::selectableOptions(),
            'privacyRequestStatuses' => PrivacyRequestStatus::options(),
            'privacyTransparencySummary' => config('privacy-transparency.summary', []),
            'activePrivacyPolicyVersion' => $privacy['activePolicy'],
            'latestPrivacyPolicyAcceptance' => $privacy['latestAcceptance'],
            'hasAcknowledgedActivePrivacyPolicy' => $privacy['hasAcknowledgedActivePolicy'],
            'gdprEnforcementEnabled' => $gdprEnforced,
            'richComposerDefault' => ($user->prefers_rich_editor ?? true) ? 'rich' : 'source',
        ];
    }

    /**
     * @return array{plans: Collection<int, MembershipPlan>, memberships: Collection<int, UserMembership>}
     */
    private function billingTab(User $user): array
    {
        return [
            'plans' => $this->memberships->availablePlans(),
            'memberships' => $user->userMemberships()->with('plan')->latest('id')->get(),
        ];
    }

    /**
     * @return array{requestableEntitlementDefinitions: Collection<int, EntitlementDefinition>, entitlementExplanations: array<string, mixed>, entitlementRequests: mixed}
     */
    private function entitlementsTab(User $user): array
    {
        $held = $this->entitlementResolver->entitlementsFor($user);

        return [
            'entitlementExplanations' => $this->entitlementResolver->explanationsFor($user),
            'requestableEntitlementDefinitions' => EntitlementDefinition::query()
                ->where('is_active', true)
                ->where('is_requestable', true)
                ->when($held !== [], fn ($query) => $query->whereNotIn('key', $held))
                ->orderBy('sort_order')
                ->orderBy('key')
                ->get(),
            'entitlementRequests' => EntitlementRequest::query()
                ->where('user_id', $user->id)
                ->orderByDesc('requested_at')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'entitlement_page')
                ->withQueryString(),
        ];
    }

    /**
     * @return array{apiTokens: Collection<int, PersonalAccessToken>, availableApiScopes: list<array<string, string>>, apiTokenAbilityLabels: array<int, list<string>>}
     */
    private function apiKeysTab(User $user): array
    {
        $tokens = $user->tokens()->latest('id')->get();

        return [
            'apiTokens' => $tokens,
            'availableApiScopes' => $this->apiScopes->selfServiceScopesFor($user),
            'apiTokenAbilityLabels' => $tokens
                ->mapWithKeys(fn (PersonalAccessToken $token): array => [$token->id => $this->apiScopes->labelsForAbilities((array) ($token->abilities ?? []))])
                ->all(),
        ];
    }

    /**
     * @return array{secret: string, otpauthUri: string, svg: string}|null
     */
    private function twoFactorSetup(User $user): ?array
    {
        $secret = $user->getTwoFactorPendingSecret();

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $uri = $this->google2fa->getQRCodeUrl((string) config('app.name', 'Forum'), $user->email ?: (string) $user->username, $secret);

        return [
            'secret' => $secret,
            'otpauthUri' => $uri,
            'svg' => (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($uri),
        ];
    }

    private function privacyRequests(User $user): mixed
    {
        return PrivacyRequest::query()
            ->where('user_id', $user->id)
            ->with('replies')
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'privacy_page')
            ->withQueryString();
    }

    /**
     * Short-lived signed links for fulfilled exports whose file still exists; a fresh one is made on
     * every page view.
     *
     * @param  Collection<int, PrivacyRequest>  $requests
     * @return array<int, string>
     */
    private function exportUrls(Collection $requests): array
    {
        $disk = Storage::disk((string) config('filagora-compliance.exports.disk', 'local'));
        $urls = [];

        foreach ($requests as $request) {
            $path = trim((string) ($request->export_path ?? ''));

            if ($request->type === PrivacyRequestType::EXPORT && $request->status === PrivacyRequestStatus::FULFILLED && $path !== '' && $disk->exists($path)) {
                $urls[$request->id] = URL::temporarySignedRoute('profile.privacy-requests.export.download', now()->addHour(), ['privacyRequest' => $request->id]);
            }
        }

        return $urls;
    }

    /**
     * @return array<string, string>
     */
    private function timezoneOptions(?string $current): array
    {
        $options = array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers());
        $current = trim((string) $current);

        return $current !== '' && ! isset($options[$current]) ? [$current => $current.' (Imported)'] + $options : $options;
    }
}
