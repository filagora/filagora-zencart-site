<?php

namespace App\View\Composers;

use Filagora\Cms\Services\CmsContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The forum sub-menu every forum page shows, from the CMS's `forum_header` menu. Worked out once
 * per request and viewer.
 */
class ForumPagesComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly CmsContent $cms,
    ) {}

    public function compose(View $view): void
    {
        $signature = (string) ($this->request->user()?->getAuthIdentifier() ?? 'guest');
        $shared = $this->request->attributes->get('frontend.forum_shared_view_data');

        if (! is_array($shared) || $this->request->attributes->get('frontend.forum_shared_view_data.signature') !== $signature) {
            $shared = ['forumHeaderMenuItems' => $this->cms->navigationItems('forum_header')];

            $this->request->attributes->set('frontend.forum_shared_view_data', $shared);
            $this->request->attributes->set('frontend.forum_shared_view_data.signature', $signature);
        }

        $view->with($shared);
    }
}
