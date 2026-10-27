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

Route::get('/newsletter', NotYetPorted::class)->name('newsletter.show');
Route::post('/staff/impersonation/stop', NotYetPorted::class)->name('staff.impersonation.stop');
Route::post('/bug-reports/{bugReport}', NotYetPorted::class)->name('bug-reports.update');
Route::post('/profile/hide-resolved-bug-threads', NotYetPorted::class)->name('profile.hide-resolved-bug-threads.update');
Route::post('/threads/{thread}/bug-reports', NotYetPorted::class)->name('threads.bug-reports.store');
