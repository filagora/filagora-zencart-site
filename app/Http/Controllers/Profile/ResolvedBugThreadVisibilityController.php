<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ResolvedBugThreadVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResolvedBugThreadVisibilityController extends Controller
{
    public function __invoke(Request $request, ResolvedBugThreadVisibility $visibility): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $visibility->availableFor($user), 403);

        $visibility->setHidden($request, $request->boolean('hidden'));

        $to = (string) $request->input('redirect_to', '');

        if ($to !== '' && str_starts_with($to, '/') && ! str_starts_with($to, '//') && (string) parse_url($to, PHP_URL_PATH) !== '') {
            return redirect()->to($to);
        }

        return redirect()->back(fallback: route('forums.index'));
    }
}
