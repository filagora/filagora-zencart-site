<?php

use App\Http\Middleware\ApplySearchIconToggleOverride;
use App\Http\Middleware\EnsureFrontendEmailIsVerified;
use App\Http\Middleware\FrontendMaintenanceMode;
use App\Http\Middleware\RecordUserLastSeen;
use App\Http\Middleware\RepairLegacyPathEncoding;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\ValidatePathEncoding;
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
        ]);

        // The base ValidatePathEncoding answers 400 to any path that is not valid UTF-8. Old forum
        // links percent-encode in Windows code pages, which a plain 400 would throw away; replacing
        // the middleware with a subclass repairs what it can and still 400s the rest.
        $middleware->replace(ValidatePathEncoding::class, RepairLegacyPathEncoding::class);

        $middleware->alias([
            'frontend.verified' => EnsureFrontendEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
