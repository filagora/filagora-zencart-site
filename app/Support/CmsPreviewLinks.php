<?php

namespace App\Support;

use Filagora\Cms\Preview\PreviewSession;
use Illuminate\Http\Request;

/**
 * Addresses for comparing a CMS preview with the live page: the same URL with the preview
 * switched off for one request, or with the preview parameter removed.
 */
class CmsPreviewLinks
{
    public static function parameter(): string
    {
        return (string) config('filagora-cms.preview.query_parameter', 'preview');
    }

    /**
     * This page as visitors see it, whatever is being previewed.
     */
    public static function live(Request $request): string
    {
        return $request->fullUrlWithQuery([self::parameter() => PreviewSession::LIVE]);
    }

    /**
     * This page without the preview parameter: back to the preview a session holds, or to the
     * live page when the preview came from a token in the address.
     */
    public static function withoutPreviewParameter(Request $request): string
    {
        return $request->fullUrlWithoutQuery(self::parameter());
    }
}
