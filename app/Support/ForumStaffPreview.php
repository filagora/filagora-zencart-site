<?php

namespace App\Support;

use App\Models\User;
use Filagora\Forums\Contracts\StaffPreview;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * The forum package's "view as a member" switch, answered from the staff member's session toggle.
 */
class ForumStaffPreview implements StaffPreview
{
    public function __construct(
        private readonly Request $request,
        private readonly StaffFrontendPreview $preview,
    ) {}

    public function enabledFor(?Authenticatable $user): bool
    {
        return $this->preview->enabledFor($this->request, $user instanceof User ? $user : null);
    }
}
