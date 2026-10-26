<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponseMessage;
use Filagora\Forums\Services\AnnouncementDismissals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A member closing a site-wide notice, and the first time they saw it (so the notice can stop
 * showing after its allowance).
 */
class AnnouncementDismissalController extends Controller
{
    public function __construct(private readonly AnnouncementDismissals $dismissals) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => ApiResponseMessage::unauthenticated()], 401);
        }

        return response()->json(['dismissed' => $this->dismissals->dismiss($user, $this->notificationId($request))]);
    }

    public function storeView(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => ApiResponseMessage::unauthenticated()], 401);
        }

        return response()->json(['recorded' => $this->dismissals->recordFirstView($user, $this->notificationId($request))]);
    }

    private function notificationId(Request $request): int
    {
        return (int) $request->validate(['notification_id' => ['required', 'integer', 'min:1']])['notification_id'];
    }
}
