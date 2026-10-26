<?php

use App\Http\Controllers\Api\CmsApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

// The plugins package registers its own `/api/v1/plugins*` routes (see config/filagora-plugins.php).
Route::prefix('cms')->group(function (): void {
    Route::get('/pages', [CmsApiController::class, 'pages']);
    Route::get('/pages/{slug}', [CmsApiController::class, 'page']);
    Route::get('/blocks/{slug}', [CmsApiController::class, 'block']);
    Route::get('/navigation/{name}', [CmsApiController::class, 'navigation']);
});

Route::middleware('auth:sanctum')->get('/user', [UserApiController::class, 'show']);
