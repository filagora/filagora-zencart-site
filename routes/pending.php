<?php

use App\Http\Controllers\NotYetPorted;
use Illuminate\Support\Facades\Route;

/*
 * Routes of the site this one replaces that belong to a slice not yet ported (see
 * zencart-site-frontend-plan.md in the planning repository), kept under their real names and paths
 * so the pages that link to them render. Each answers 404 until its slice replaces it. A slice
 * deletes its own lines from this file; the file must be empty before cutover, and the route
 * parity test treats anything still here as not done.
 */

Route::get('/messages', NotYetPorted::class)->name('messages.index');
Route::get('/messages/users/search', NotYetPorted::class)->name('messages.users.search');
Route::get('/newsletter', NotYetPorted::class)->name('newsletter.show');
Route::post('/notifications/clear-all', NotYetPorted::class)->name('notifications.clear-all');
Route::get('/notifications', NotYetPorted::class)->name('notifications.index');
Route::get('/notifications/{notification}/go', NotYetPorted::class)->name('notifications.open');
Route::post('/notifications/read-all', NotYetPorted::class)->name('notifications.read-all');
Route::get('/plugins/{plugin}/releases/new', NotYetPorted::class)->name('plugins.release.new');
Route::get('/plugins/{plugin}', NotYetPorted::class)->name('plugins.show');
Route::get('/plugins/submit', NotYetPorted::class)->name('plugins.submit');
Route::post('/profile/privacy-policy/accept', NotYetPorted::class)->name('profile.privacy-policy.accept');
Route::get('/profile', NotYetPorted::class)->name('profile.show');
Route::post('/profile/staff-frontend-preview', NotYetPorted::class)->name('profile.staff-frontend-preview.update');
Route::get('/search', NotYetPorted::class)->name('search.index');
Route::post('/staff/impersonation/stop', NotYetPorted::class)->name('staff.impersonation.stop');
Route::get('/avatars/{user}/{filename}', NotYetPorted::class)->name('users.avatar.show');
Route::get('/account/billing', NotYetPorted::class)->name('billing.index');
Route::get('/attachment.php', NotYetPorted::class)->name('legacy.attachments.download');
Route::get('/forums/search/for-move', NotYetPorted::class)->name('forums.search-for-move');
Route::get('/forums/{forum}/threads/new', NotYetPorted::class)->name('threads.create.form');
Route::get('/members/{user}', NotYetPorted::class)->name('members.show');
Route::get('/misc/{thread}', NotYetPorted::class)->name('legacy.attachments.index');
Route::get('/polls/{poll}/edit', NotYetPorted::class)->name('polls.edit');
Route::get('/post-attachments/{attachment}', NotYetPorted::class)->name('post.attachments.show');
Route::get('/post-attachments/{attachment}/thumbnail', NotYetPorted::class)->name('post.attachments.thumbnail');
Route::get('/posts/{post}/edit', NotYetPorted::class)->name('posts.edit');
Route::get('/threads/search/for-move', NotYetPorted::class)->name('threads.search-for-move');
Route::get('/threads/{thread}/poll/new', NotYetPorted::class)->name('threads.poll.create');
Route::post('/bug-reports/{bugReport}', NotYetPorted::class)->name('bug-reports.update');
Route::post('/forums/{forum}/threads', NotYetPorted::class)->name('threads.create');
Route::post('/moderation/relocate/undo', NotYetPorted::class)->name('moderation.relocate.undo');
Route::post('/moderation/reports/{postReport}/resolve', NotYetPorted::class)->name('moderation.reports.resolve');
Route::post('/polls/{poll}/moderate', NotYetPorted::class)->name('polls.moderate');
Route::post('/polls/{poll}/vote', NotYetPorted::class)->name('polls.vote');
Route::post('/posts/{post}/moderate', NotYetPorted::class)->name('posts.moderate');
Route::post('/posts/{post}/reactions/toggle', NotYetPorted::class)->name('posts.reactions.toggle');
Route::post('/posts/{post}/report', NotYetPorted::class)->name('posts.report');
Route::post('/profile/hide-resolved-bug-threads', NotYetPorted::class)->name('profile.hide-resolved-bug-threads.update');
Route::post('/threads/{thread}/bug-reports', NotYetPorted::class)->name('threads.bug-reports.store');
Route::post('/threads/{thread}/moderate', NotYetPorted::class)->name('threads.moderate');
Route::post('/threads/{thread}/posts/moderate-bulk', NotYetPorted::class)->name('posts.moderate-bulk');
Route::post('/threads/{thread}/reply', NotYetPorted::class)->name('threads.reply');
Route::post('/threads/{thread}/subscribe', NotYetPorted::class)->name('threads.subscribe');
Route::post('/threads/{thread}/unsubscribe', NotYetPorted::class)->name('threads.unsubscribe');
