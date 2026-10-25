<?php

use App\Http\Controllers\Cms\CmsPageController;
use App\Http\Controllers\Cms\CmsPreviewController;
use App\Http\Controllers\Cms\CmsPreviewSessionController;
use App\Http\Controllers\Cms\CmsPreviewShareController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ForumSearchController;
use App\Http\Controllers\FrontendLogoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/forums', [ForumController::class, 'index'])->name('forums.index');
Route::get('/forums/today', [ForumController::class, 'today'])->name('forums.today');
Route::get('/forums/unread', [ForumController::class, 'unread'])->middleware('frontend.verified')->name('forums.unread');
Route::get('/forums/mark-all-read', static function (): never {
    abort(405);
});
Route::post('/forums/mark-all-read', [ForumController::class, 'markAllRead'])->middleware('frontend.verified')->name('forums.mark-all-read');
// Before /forums/{forum}, which would otherwise take "search" as a forum.
Route::get('/forums/search', [ForumSearchController::class, 'index'])->name('forums.search');
Route::get('/forums/search/forums', [ForumSearchController::class, 'forumOptions'])->name('forums.search.forum-options');
Route::get('/forums/{forum}', [ForumController::class, 'show'])->name('forums.show');
Route::get('/threads/{thread}', [ForumController::class, 'showThread'])->name('threads.show');
Route::get('/threads/{thread}/search', [ForumSearchController::class, 'thread'])->name('threads.search');

Route::get('/sitemap.xml', [SitemapController::class, 'show'])->name('sitemap.xml');

Route::get('/pages/{slug}', [CmsPageController::class, 'show'])->name('cms.pages.show');
Route::get('/pages/tag/{tagSlug}', [CmsPageController::class, 'listByTag'])->name('cms.pages.tag');

foreach ([
    'privacy' => 'legal.privacy',
    'download' => 'download',
    'about' => 'about',
    'compare' => 'compare',
    'donate' => 'donate',
    'services' => 'services',
    'services/payment-processing' => 'services.payment-processing',
    'services/business-services' => 'services.business-services',
    'services/custom-programming' => 'services.custom-programming',
] as $customRoute => $name) {
    Route::get('/'.$customRoute, [CmsPageController::class, 'showByCustomRoute'])
        ->defaults('customRoute', $customRoute)
        ->name($name);
}

Route::permanentRedirect('/comparison', '/compare')->name('comparison');

Route::get('/preview/{token}', [CmsPreviewShareController::class, 'open'])
    ->middleware('throttle:30,1')
    ->name('cms.preview.share.open');
Route::post('/preview-exit', [CmsPreviewShareController::class, 'exit'])->name('cms.preview.share.exit');

Route::middleware('auth')->group(function (): void {
    Route::get('/staff/cms/pages/{page}/preview', [CmsPreviewController::class, 'page'])->name('cms.pages.preview');
    Route::get('/staff/cms/pages/{page}/preview/start', [CmsPreviewSessionController::class, 'startPage'])->name('cms.pages.preview.start');
    Route::get('/staff/cms/navigations/{navigation}/preview', [CmsPreviewSessionController::class, 'startNavigation'])->name('cms.navigations.preview');
    Route::post('/staff/cms/preview/remove', [CmsPreviewSessionController::class, 'remove'])->name('cms.preview.remove');
    Route::post('/staff/cms/preview/variant', [CmsPreviewSessionController::class, 'switchVariant'])->name('cms.preview.variant');
    Route::post('/staff/cms/preview/stop', [CmsPreviewSessionController::class, 'stop'])->name('cms.preview.stop');
    Route::get('/staff/cms/blocks/{block}/preview', [CmsPreviewController::class, 'block'])
        ->withoutScopedBindings()
        ->name('cms.blocks.preview');
});

Route::post('/logout', FrontendLogoutController::class)->name('frontend.logout');

require __DIR__.'/auth.php';
require __DIR__.'/pending.php';

Route::get('/{page}', [PageController::class, 'show'])
    ->where('page', '[a-zA-Z0-9\-\_\/]+')
    ->name('page.show');
