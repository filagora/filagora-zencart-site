<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponseMessage;
use Filagora\Compliance\Services\CookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A signed-in member's stored cookie choice, which the site's script reads and writes so the choice
 * follows them between browsers.
 */
class CookieConsentController extends Controller
{
    public function __construct(private readonly CookieConsent $consent) {}

    public function show(Request $request): JsonResponse
    {
        if (! $this->consent->isWorkflowEnabled()) {
            return response()->json(['message' => ApiResponseMessage::cookieConsentWorkflowDisabled()], 404);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => ApiResponseMessage::unauthenticated()], 401);
        }

        $record = $this->consent->forUser($user);
        $requiresReconsent = $this->consent->requiresReconsent($record);

        return response()->json([
            'consent' => $requiresReconsent ? null : $record?->consent,
            'policyVersion' => $record?->policyVersion?->version,
            'activePolicyVersion' => $this->consent->activePolicyVersion()?->version,
            'consentedAt' => $record?->consented_at?->toIso8601String(),
            'requiresReconsent' => $requiresReconsent,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->consent->isWorkflowEnabled()) {
            return response()->json(['message' => ApiResponseMessage::cookieConsentWorkflowDisabled()], 404);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => ApiResponseMessage::unauthenticated()], 401);
        }

        $validated = $request->validate([
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['boolean'],
            'cookies' => ['sometimes', 'array'],
            'cookies.*' => ['boolean'],
            'updatedAt' => ['sometimes', 'nullable', 'date'],
        ]);

        $record = $this->consent->save($user, $validated, $request->ip(), $request->userAgent());

        return response()->json([
            'consent' => $record->consent,
            'policyVersion' => $record->policyVersion?->version,
            'activePolicyVersion' => $record->policyVersion?->version,
            'consentedAt' => $record->consented_at?->toIso8601String(),
            'requiresReconsent' => false,
        ]);
    }
}
