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
Route::get('/account/billing', NotYetPorted::class)->name('billing.index');
Route::get('/members/{user}', NotYetPorted::class)->name('members.show');
Route::post('/bug-reports/{bugReport}', NotYetPorted::class)->name('bug-reports.update');
Route::post('/profile/hide-resolved-bug-threads', NotYetPorted::class)->name('profile.hide-resolved-bug-threads.update');
Route::post('/threads/{thread}/bug-reports', NotYetPorted::class)->name('threads.bug-reports.store');
Route::get('/messages/{conversation}', NotYetPorted::class)->name('messages.show');
Route::get('/plugins', NotYetPorted::class)->name('plugins.index');
Route::get('/plugins/type/{slug}', NotYetPorted::class)->name('plugins.type');
Route::get('/members', NotYetPorted::class)->name('members.index');
