<?php

use App\Http\Controllers\LegacyRedirectController;
use App\Http\Middleware\ThrottleVersionCheckOnly;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The vBulletin scripts, registered below at both the site root and under
// /forum/. That /forum prefix was primarily a friendly alias which .htaccess
// rewrote to /forum.php -- but the prefixed script URLs are still in active use:
// 419 hits in a 16h sample, 83 of them referred by pages on this site,
// because imported post bodies carry absolute /forum/showthread.php?t=N links.
// Registering both spellings resolves them without costing an extra redirect hop.
$vbulletinRoutes = function (): void {
    Route::get('/showthread.php/{legacyPath?}', [LegacyRedirectController::class, 'showThread'])
        ->where('legacyPath', '.*')
        ->name('showthread');

    Route::get('/showpost.php/{legacyPath?}', [LegacyRedirectController::class, 'showPost'])
        ->where('legacyPath', '.*')
        ->name('showpost');

    Route::get('/forumdisplay.php/{legacyPath?}', [LegacyRedirectController::class, 'forumDisplay'])
        ->where('legacyPath', '.*')
        ->name('forumdisplay');

    Route::get('/misc.php', [LegacyRedirectController::class, 'misc'])
        ->name('misc');

    Route::get('/sendmessage.php/{legacyPath?}', [LegacyRedirectController::class, 'sendMessage'])
        ->where('legacyPath', '.*')
        ->name('sendmessage');

    Route::get('/search.php', [LegacyRedirectController::class, 'search'])
        ->name('search');

    // Only the version check is limited; the other modes just redirect.
    Route::get('/downloads.php', [LegacyRedirectController::class, 'downloads'])
        ->middleware(ThrottleVersionCheckOnly::class)
        ->name('downloads');

    Route::get('/content.php/{legacyPath?}', [LegacyRedirectController::class, 'content'])
        ->where('legacyPath', '.*')
        ->name('content');

    Route::get('/faq.php', [LegacyRedirectController::class, 'faq'])
        ->name('faq');

    Route::get('/member.php/{legacyPath?}', [LegacyRedirectController::class, 'member'])
        ->where('legacyPath', '.*')
        ->name('member');

    Route::get('/printthread.php/{legacyPath?}', [LegacyRedirectController::class, 'printThread'])
        ->where('legacyPath', '.*')
        ->name('printthread');

    Route::get('/newreply.php/{legacyPath?}', [LegacyRedirectController::class, 'newReply'])
        ->where('legacyPath', '.*')
        ->name('newreply');

    Route::get('/external.php/{legacyPath?}', [LegacyRedirectController::class, 'external'])
        ->where('legacyPath', '.*')
        ->name('external');

    Route::get('/showcase.php', [LegacyRedirectController::class, 'showcase'])
        ->name('showcase');

    Route::get('/blog.php/{legacyPath?}', [LegacyRedirectController::class, 'blog'])
        ->where('legacyPath', '.*')
        ->name('blog');

    Route::get('/entry.php/{legacyPath?}', [LegacyRedirectController::class, 'entry'])
        ->where('legacyPath', '.*')
        ->name('entry');

    Route::get('/private.php/{legacyPath?}', [LegacyRedirectController::class, 'private'])
        ->where('legacyPath', '.*')
        ->name('private');
};

// These routes only map an old URL to a new one; nothing in the resolve path
// reads session or auth state. Crawlers hit them constantly and ignore cookies,
// so leaving StartSession attached started and persisted a fresh session on
// every request, whatever the configured session driver.
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () use ($vbulletinRoutes): void {
    Route::name('legacy.')->group($vbulletinRoutes);
    Route::prefix('forum')->name('legacy.forum.')->group($vbulletinRoutes);

    // The old MediaWiki install; its content now lives on the docs site.
    Route::get('/wiki/{legacyPath?}', [LegacyRedirectController::class, 'wiki'])
        ->where('legacyPath', '.*')
        ->name('legacy.wiki');

    // Catch-all route for very old zc-ping-server hits.
    Route::get('/zc-ping-server/{path}', function ($path) {
        return redirect('https://ping.zen-cart.com/'.$path, 301);
    })->where('path', '.*');
});
