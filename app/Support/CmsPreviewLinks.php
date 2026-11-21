<?php

namespace App\Support;

use Filagora\Cms\Preview\PreviewUrl;
use Illuminate\Http\Request;

/**
 * The address of the live page for a previewed one: the same URL without the preview token.
 */
class CmsPreviewLinks
{
    public static function live(Request $request): string
    {
        $parameter = PreviewUrl::parameter();

        return $request->fullUrlWithoutQuery([$parameter]);
    }
}
