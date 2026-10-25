<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Middleware\ThrottleAuthEndpoints;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::get('/password/forgot', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verification.notice');

Route::middleware(ThrottleAuthEndpoints::class)->group(function (): void {
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/login/two-factor', [LoginController::class, 'storeTwoFactor'])->name('login.two-factor.store');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('password.update');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send');
});

Route::post('/login/two-factor/cancel', [LoginController::class, 'cancelTwoFactor'])->name('login.two-factor.cancel');
