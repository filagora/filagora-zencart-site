<?php

use App\Http\Middleware\ApplySearchIconToggleOverride;
use App\Http\Middleware\EnsureFrontendEmailIsVerified;
use App\Http\Middleware\FrontendMaintenanceMode;
use App\Http\Middleware\RecordUserLastSeen;
use Filagora\Cms\Http\Middleware\MarkPreviewResponsesUncacheable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            FrontendMaintenanceMode::class,
        ]);

        $middleware->web(append: [
            RecordUserLastSeen::class,
            ApplySearchIconToggleOverride::class,
            // The CMS package adds this itself, but that registration is lost when the kernel
            // syncs its own middleware groups, so the site lists it here.
            MarkPreviewResponsesUncacheable::class,
        ]);

        $middleware->alias([
            'frontend.verified' => EnsureFrontendEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
