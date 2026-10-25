<?php

use App\Http\Controllers\FrontendLogoutController;
use Illuminate\Support\Facades\Route;

// The home page is a placeholder until the CMS slice (S1) renders it from the CMS.
Route::view('/', 'home')->name('home');

Route::post('/logout', FrontendLogoutController::class)->name('frontend.logout');

require __DIR__.'/auth.php';
require __DIR__.'/pending.php';
