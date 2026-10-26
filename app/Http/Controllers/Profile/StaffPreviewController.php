<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StaffFrontendPreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff switching between seeing the site as staff and as an ordinary member.
 */
class StaffPreviewController extends Controller
{
    public function __invoke(Request $request, StaffFrontendPreview $preview): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $preview->availableFor($user), 403);

        $preview->setEnabled($request, $request->boolean('enabled'));

        $to = (string) $request->input('redirect_to', '');

        if ($to !== '' && str_starts_with($to, '/') && ! str_starts_with($to, '//') && (string) parse_url($to, PHP_URL_PATH) !== '') {
            return redirect()->to($to);
        }

        return redirect()->back(fallback: route('home'));
    }
}
