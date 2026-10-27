<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BugReportController;
use App\Http\Controllers\Cms\CmsPageController;
use App\Http\Controllers\Cms\CmsPreviewController;
use App\Http\Controllers\Cms\CmsPreviewSessionController;
use App\Http\Controllers\Cms\CmsPreviewShareController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ForumModerationController;
use App\Http\Controllers\ForumSearchController;
use App\Http\Controllers\FrontendLogoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarkdownPreviewController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PluginCommunityController;
use App\Http\Controllers\PluginLibraryController;
use App\Http\Controllers\PluginModerationController;
use App\Http\Controllers\PluginSubmissionController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\PostAttachmentController;
use App\Http\Controllers\PostInteractionController;
use App\Http\Controllers\Profile\AccountRequestController;
use App\Http\Controllers\Profile\AnnouncementDismissalController;
use App\Http\Controllers\Profile\ApiKeyController;
use App\Http\Controllers\Profile\CookieConsentController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\ResolvedBugThreadVisibilityController;
use App\Http\Controllers\Profile\StaffPreviewController;
use App\Http\Controllers\Profile\SubscriptionController;
use App\Http\Controllers\Profile\TwoFactorController;
use App\Http\Controllers\RelocationTargetsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaffUserImpersonationController;
use App\Http\Controllers\ThreadPostingController;
use App\Http\Controllers\UserAvatarController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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

Route::get('/misc/{thread}', [PostAttachmentController::class, 'index'])->name('legacy.attachments.index');
Route::get('/attachment.php', [PostAttachmentController::class, 'download'])->name('legacy.attachments.download');
// The same link inside an old /forum/ path (see the legacy redirects for the rest of that prefix).
Route::get('/forum/attachment.php', [PostAttachmentController::class, 'download'])->name('legacy.forum.attachments.download');
Route::get('/post-attachments/{attachment}', [PostAttachmentController::class, 'show'])->name('post.attachments.show');
Route::get('/post-attachments/{attachment}/thumbnail', [PostAttachmentController::class, 'thumbnail'])->name('post.attachments.thumbnail');

// Avatars are public images requested as subresources, so the session middleware is stripped:
// StartSession and PreventRequestForgery would answer every one with Set-Cookie, and a Set-Cookie
// on a cacheable response makes Cloudflare refuse to cache it (measured on the old site: 39,439 of
// 39,461 sampled avatar responses were cf-cache-status BYPASS for exactly this reason). The
// controller reads no session.
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function (): void {
    Route::get('/avatars/{user}/{filename}', [UserAvatarController::class, 'show'])
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('users.avatar.show');
});

Route::middleware('frontend.verified')->group(function (): void {
    Route::get('/forums/{forum}/threads/new', [ThreadPostingController::class, 'newThreadForm'])->name('threads.create.form');
    Route::post('/forums/{forum}/threads', [ThreadPostingController::class, 'storeThread'])->name('threads.create');
    Route::post('/threads/{thread}/reply', [ThreadPostingController::class, 'reply'])->name('threads.reply');
    Route::get('/posts/{post}/edit', [ThreadPostingController::class, 'editPostForm'])->name('posts.edit');
    Route::put('/posts/{post}', [ThreadPostingController::class, 'updatePost'])->name('posts.update');
    Route::post('/threads/{thread}/subscribe', [ThreadPostingController::class, 'subscribe'])->name('threads.subscribe');
    Route::post('/threads/{thread}/unsubscribe', [ThreadPostingController::class, 'unsubscribe'])->name('threads.unsubscribe');

    Route::get('/members', [MembersController::class, 'index'])->name('members.index');
    Route::post('/members/{user}/contacts', [MembersController::class, 'storeContact'])->name('members.contacts.store');
    Route::delete('/members/{user}/contacts', [MembersController::class, 'destroyContact'])->name('members.contacts.destroy');
    Route::get('/members/{user}/posts', [MembersController::class, 'latestPosts'])->name('members.posts');
    Route::get('/members/{user}/threads', [MembersController::class, 'latestThreads'])->name('members.threads');
    Route::post('/members/{user}/ignore', [MembersController::class, 'storeIgnore'])->name('members.ignore.store');
    Route::delete('/members/{user}/ignore', [MembersController::class, 'destroyIgnore'])->name('members.ignore.destroy');
    Route::get('/members/{user}', [MembersController::class, 'show'])->name('members.show');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/compose', [MessageController::class, 'compose'])->name('messages.compose');
    Route::get('/messages/users/search', [MessageController::class, 'searchUsers'])->middleware('throttle:message-user-search')->name('messages.users.search');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/bulk', [MessageController::class, 'bulk'])->name('messages.bulk');
    Route::post('/messages/trash/empty', [MessageController::class, 'emptyTrash'])->name('messages.trash.empty');
    Route::post('/messages/folders', [MessageController::class, 'createFolder'])->name('messages.folders.store');
    Route::post('/messages/folders/{folder}/delete', [MessageController::class, 'deleteFolder'])->name('messages.folders.delete');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/reply', [MessageController::class, 'reply'])->name('messages.reply');
    Route::post('/messages/{conversation}/move', [MessageController::class, 'move'])->name('messages.move');

    Route::middleware('throttle:notifications')->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}/go', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/clear-read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
        Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    });

    Route::get('/account/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/account/billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/account/billing/cancel/{membership}', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('/account/billing/checkout/success', [BillingController::class, 'checkoutSuccess'])->name('billing.checkout.success');
    Route::get('/account/billing/checkout/cancel', [BillingController::class, 'checkoutCancel'])->name('billing.checkout.cancel');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/details', [ProfileController::class, 'updateDetails'])->name('profile.details.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/settings', [ProfileController::class, 'updateSettings'])->name('profile.settings.update');
    Route::post('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::post('/profile/avatar/remove', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/2fa/setup', [TwoFactorController::class, 'start'])->name('profile.2fa.setup');
    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');
    Route::post('/profile/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('profile.2fa.recovery-codes.regenerate');
    Route::post('/profile/api-keys', [ApiKeyController::class, 'store'])->name('profile.api-tokens.store');
    Route::post('/profile/api-keys/{token}/revoke', [ApiKeyController::class, 'revoke'])->name('profile.api-tokens.revoke');
    Route::post('/profile/subscriptions/{thread}/remove', [SubscriptionController::class, 'removeThread'])->name('profile.subscriptions.remove');
    Route::post('/profile/subscriptions/bulk-remove', [SubscriptionController::class, 'removeThreads'])->name('profile.subscriptions.bulk-remove');
    Route::post('/profile/plugin-subscriptions/{plugin:slug}/remove', [SubscriptionController::class, 'removePlugin'])->name('profile.plugin-subscriptions.remove');
    Route::post('/profile/entitlement-requests', [AccountRequestController::class, 'storeEntitlementRequest'])->name('profile.entitlement-requests.store');
    Route::post('/profile/privacy-requests', [AccountRequestController::class, 'storePrivacyRequest'])->middleware('throttle:privacy-request-submit')->name('profile.privacy-requests.store');
    Route::get('/profile/privacy-requests/{privacyRequest}/export', [AccountRequestController::class, 'downloadPrivacyExport'])->middleware('signed')->name('profile.privacy-requests.export.download');
    Route::post('/profile/hide-resolved-bug-threads', ResolvedBugThreadVisibilityController::class)->name('profile.hide-resolved-bug-threads.update');
    Route::post('/threads/{thread}/bug-reports', [BugReportController::class, 'store'])->name('threads.bug-reports.store');
    Route::post('/bug-reports/{bugReport}', [BugReportController::class, 'update'])->name('bug-reports.update');
    Route::post('/staff/impersonation/stop', [StaffUserImpersonationController::class, 'stop'])->name('staff.impersonation.stop');
    Route::get('/staff/impersonation/{user}', static function (): never {
        abort(405);
    });
    Route::post('/staff/impersonation/{user}', [StaffUserImpersonationController::class, 'start'])->name('staff.impersonation.start');
    Route::post('/profile/staff-frontend-preview', StaffPreviewController::class)->name('profile.staff-frontend-preview.update');
    Route::get('/profile/cookie-consent', [CookieConsentController::class, 'show'])->name('profile.cookie-consent.show');
    Route::post('/profile/cookie-consent', [CookieConsentController::class, 'store'])->name('profile.cookie-consent.store');
    Route::post('/profile/site-notification-dismissals', [AnnouncementDismissalController::class, 'store'])->name('profile.site-notification-dismissals.store');
    Route::post('/profile/site-notification-views', [AnnouncementDismissalController::class, 'storeView'])->name('profile.site-notification-views.store');
    Route::post('/profile/privacy-policy/accept', [AccountRequestController::class, 'acceptPrivacyPolicy'])->name('profile.privacy-policy.accept');

    Route::post('/markdown/preview', MarkdownPreviewController::class)->middleware('throttle:markdown-preview')->name('markdown.preview');
    Route::post('/post-attachments/inline-upload', [PostAttachmentController::class, 'storeInline'])->name('post.attachments.inline-upload');

    Route::post('/posts/{post}/report', [PostInteractionController::class, 'reportPost'])->name('posts.report');
    Route::post('/posts/{post}/reactions/toggle', [PostInteractionController::class, 'toggleReaction'])->name('posts.reactions.toggle');

    Route::get('/threads/{thread}/poll/new', [PollController::class, 'createForm'])->name('threads.poll.create');
    Route::post('/threads/{thread}/poll', [PollController::class, 'store'])->name('threads.poll.store');
    Route::get('/polls/{poll}/edit', [PollController::class, 'editForm'])->name('polls.edit');
    Route::put('/polls/{poll}', [PollController::class, 'update'])->name('polls.update');
    Route::post('/polls/{poll}/moderate', [PollController::class, 'moderate'])->name('polls.moderate');
    Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->name('polls.vote');

    Route::get('/threads/search/for-move', [RelocationTargetsController::class, 'threads'])->middleware('throttle:message-user-search')->name('threads.search-for-move');
    Route::get('/forums/search/for-move', [RelocationTargetsController::class, 'forums'])->middleware('throttle:message-user-search')->name('forums.search-for-move');
    Route::post('/threads/{thread}/moderate', [ForumModerationController::class, 'moderateThread'])->middleware('throttle:moderation-action')->name('threads.moderate');
    Route::post('/threads/{thread}/posts/moderate-bulk', [ForumModerationController::class, 'moderatePostsBulk'])->middleware('throttle:moderation-action')->name('posts.moderate-bulk');
    Route::post('/posts/{post}/moderate', [ForumModerationController::class, 'moderatePost'])->middleware('throttle:moderation-action')->name('posts.moderate');
    Route::post('/moderation/relocate/undo', [ForumModerationController::class, 'undoRelocation'])->middleware('throttle:moderation-action')->name('moderation.relocate.undo');
    Route::post('/moderation/reports/{postReport}/resolve', [ForumModerationController::class, 'resolvePostReport'])->middleware('throttle:moderation-action')->name('moderation.reports.resolve');
});

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
require __DIR__.'/legacy.php';

// The plugin library's public pages. After the pending routes so `/plugins/submit` and
// `/plugins/search` are matched before `/plugins/{plugin}`.
Route::middleware('frontend.verified')->group(function (): void {
    Route::get('/plugins/submit', [PluginSubmissionController::class, 'submit'])->middleware('plugins.submissions.open')->name('plugins.submit');
    Route::post('/plugins/submit', [PluginSubmissionController::class, 'store'])->middleware('plugins.submissions.open')->name('plugins.store');
    Route::get('/plugins/support-threads/search', [PluginSubmissionController::class, 'searchSupportThreads'])->name('plugins.support-threads.search');
    Route::get('/plugins/{plugin:slug}/releases/new', [PluginSubmissionController::class, 'newRelease'])->middleware('plugins.submissions.open')->name('plugins.release.new');
    Route::post('/plugins/{plugin:slug}/releases/new', [PluginSubmissionController::class, 'storeRelease'])->middleware('plugins.submissions.open')->name('plugins.release.store');
    Route::post('/plugins/{plugin:slug}/subscribe', [PluginCommunityController::class, 'subscribe'])->name('plugins.subscribe');
    Route::post('/plugins/{plugin:slug}/unsubscribe', [PluginCommunityController::class, 'unsubscribe'])->name('plugins.unsubscribe');
    Route::post('/plugins/{plugin:slug}/report', [PluginCommunityController::class, 'report'])->name('plugins.report');
    Route::post('/plugins/{plugin:slug}/moderate', [PluginModerationController::class, 'moderate'])->name('plugins.moderate');
    Route::delete('/plugins/{plugin:slug}', [PluginModerationController::class, 'destroyPlugin'])->name('plugins.destroy');
    Route::post('/plugins/{plugin:slug}/grant-auto-publish', [PluginModerationController::class, 'grantAutoPublishToPluginAuthor'])->name('plugins.grant-auto-publish');
    Route::post('/plugins/{plugin:slug}/releases/{release}/moderate', [PluginModerationController::class, 'moderateRelease'])->name('plugins.release.moderate');
    Route::delete('/plugins/{plugin:slug}/releases/{release}', [PluginModerationController::class, 'destroyRelease'])->name('plugins.release.destroy');
    Route::post('/plugins/{plugin:slug}/releases/{release}/grant-auto-publish', [PluginModerationController::class, 'grantAutoPublishToReleaseAuthor'])->name('plugins.release.grant-auto-publish');
    Route::post('/plugins/{plugin:slug}/releases/{release}/zc-versions', [PluginSubmissionController::class, 'updateReleaseZcVersions'])->name('plugins.release.zc-versions.update');
});

Route::get('/docs/api/plugin-marketplace', [ApiDocsController::class, 'pluginMarketplace'])->name('docs.api.plugin-marketplace');
Route::get('/docs/api/plugin-marketplace/openapi.yaml', [ApiDocsController::class, 'pluginMarketplaceSpec'])->name('docs.api.plugin-marketplace.spec');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/plugins', [PluginLibraryController::class, 'index'])->name('plugins.index');
Route::get('/plugins/search', [PluginLibraryController::class, 'search'])->name('plugins.search');
Route::get('/plugins/type/{slug}', [PluginLibraryController::class, 'byType'])->name('plugins.type');
Route::get('/plugins/{plugin:slug}', [PluginLibraryController::class, 'show'])->name('plugins.show');
Route::get('/plugins/{plugin:slug}/download', [PluginLibraryController::class, 'download'])->name('plugins.download');
Route::get('/plugins/{plugin:slug}/releases/{release}/download', [PluginLibraryController::class, 'downloadRelease'])->name('plugins.release.download');

Route::get('/forum.php', function (Request $request) {
    if ($request->query->has('styleid')) {
        return response('', 410);
    }

    return redirect('/forums', 301);
});

Route::get('/{page}', [PageController::class, 'show'])
    ->where('page', '[a-zA-Z0-9\-\_\/]+')
    ->name('page.show');
