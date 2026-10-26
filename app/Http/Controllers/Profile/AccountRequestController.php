<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Exceptions\PrivacyRequestRefused;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Compliance\Services\PrivacyRequests;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Models\EntitlementDefinition;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Services\EntitlementResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a member asks of the site from their profile: an entitlement, a privacy request (and the
 * export it may produce), and their acceptance of the privacy policy.
 */
class AccountRequestController extends Controller
{
    public function __construct(
        private readonly PrivacyRequests $privacyRequests,
        private readonly PolicyAcceptance $policy,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function storeEntitlementRequest(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('entitlementRequest', [
            'entitlement_key' => ['required', 'string', 'max:255', Rule::exists(EntitlementDefinition::class, 'key')->where('is_active', true)->where('is_requestable', true)],
            'request_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $key = trim((string) $validated['entitlement_key']);

        if (EntitlementRequest::query()->where('user_id', $user->id)->where('entitlement_key', $key)->where('status', EntitlementRequestStatus::PENDING->value)->exists()) {
            return $this->entitlementRefused('You already have a pending request for this entitlement.');
        }

        if ($this->entitlements->hasEntitlement($user, $key)) {
            return $this->entitlementRefused('You already have this entitlement.');
        }

        EntitlementRequest::query()->create([
            'user_id' => $user->id,
            'entitlement_key' => trim((string) $validated['entitlement_key']),
            'status' => EntitlementRequestStatus::PENDING->value,
            'request_reason' => isset($validated['request_reason']) ? trim((string) $validated['request_reason']) : null,
            'requested_at' => now(),
        ]);

        return redirect()->route('profile.show', ['tab' => 'entitlements'])->with('profile_status', __('profile.flash.entitlement_request_submitted'));
    }

    public function storePrivacyRequest(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        if (! $this->policy->isEnforced()) {
            return $this->gdprOff();
        }

        $validated = $request->validateWithBag('privacyRequest', [
            'type' => ['required', Rule::in(array_keys(PrivacyRequestType::selectableOptions()))],
            'request_details' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->privacyRequests->submit($user, $validated['type'], $validated['request_details'] ?? null);
        } catch (PrivacyRequestRefused $refused) {
            return redirect()->route('profile.show', ['tab' => 'privacy'])->withErrors(['type' => $refused->getMessage()], 'privacyRequest')->withInput();
        }

        return redirect()->route('profile.show', ['tab' => 'privacy'])->with('profile_status', __('profile.flash.privacy_request_submitted'));
    }

    public function acceptPrivacyPolicy(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        if (! $this->policy->isEnforced()) {
            return $this->gdprOff();
        }

        $acceptance = $this->policy->accept($user, $request->ip(), $request->userAgent());

        if ($acceptance === null) {
            return redirect()->route('profile.show', ['tab' => 'privacy'])->with('profile_status', __('profile.flash.privacy_policy_unavailable'));
        }

        $status = __('profile.flash.privacy_policy_acknowledged', ['version' => (string) ($acceptance->policyVersion?->version ?? '')]);
        $intended = (string) $request->session()->pull('privacy_policy.intended', '');

        return $this->isSafeInternalPath($intended)
            ? redirect()->to($intended)->with('profile_status', $status)
            : redirect()->route('profile.show', ['tab' => 'privacy'])->with('profile_status', $status);
    }

    public function downloadPrivacyExport(Request $request, PrivacyRequest $privacyRequest): StreamedResponse|RedirectResponse
    {
        $user = $this->member($request);

        if (! $this->policy->isEnforced()) {
            return $this->gdprOff();
        }

        abort_unless((int) $privacyRequest->user_id === (int) $user->id, 403);
        abort_unless($privacyRequest->type === PrivacyRequestType::EXPORT && $privacyRequest->status === PrivacyRequestStatus::FULFILLED, 404);

        $path = trim((string) ($privacyRequest->export_path ?? ''));
        $disk = Storage::disk((string) config('filagora-compliance.exports.disk', 'local'));

        abort_if($path === '' || ! $disk->exists($path), 404);

        return $disk->download($path, sprintf('privacy-export-request-%d.json', $privacyRequest->id), ['Content-Type' => 'application/json']);
    }

    private function entitlementRefused(string $message): RedirectResponse
    {
        return redirect()->route('profile.show', ['tab' => 'entitlements'])->withErrors(['entitlement_key' => $message], 'entitlementRequest')->withInput();
    }

    private function gdprOff(): RedirectResponse
    {
        return redirect()->route('profile.show', ['tab' => 'details'])->with('profile_status', __('profile.flash.gdpr_disabled'));
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * An address inside this site that a redirect may safely return to. The cookie-consent and
     * policy pages are not: sending someone back to them would loop.
     */
    private function isSafeInternalPath(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }

        $normalized = (string) parse_url($path, PHP_URL_PATH);

        if ($normalized === '') {
            return false;
        }

        foreach (['/profile/cookie-consent', '/profile/privacy-policy/accept'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
