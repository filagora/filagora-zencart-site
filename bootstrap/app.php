<?php

use App\Http\Middleware\AddMonitoringContext;
use App\Http\Middleware\ApplySearchIconToggleOverride;
use App\Http\Middleware\EnsureFrontendEmailIsVerified;
use App\Http\Middleware\EnsurePluginSubmissionsAreOpen;
use App\Http\Middleware\EnsureSubsystemIsAvailable;
use App\Http\Middleware\FrontendMaintenanceMode;
use App\Http\Middleware\NoindexPrivateAndThinPages;
use App\Http\Middleware\RecordUserLastSeen;
use App\Http\Middleware\RepairLegacyPathEncoding;
use App\Http\Middleware\RequestTimingMiddleware;
use App\Http\SubsystemRoutes;
use App\Support\TrustedHosts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static fn () => SubsystemRoutes::assign(app('router')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AddMonitoringContext::class);

        // Opt-in: only enforced when APP_TRUSTED_HOSTS lists hostnames. See TrustedHosts.
        $middleware->trustHosts(at: fn (): array => TrustedHosts::patterns(), subdomains: false);

        // Global rather than in the web group: Filament panels don't run `web`, and the dashboard is
        // the main thing this needs to cover.
        $middleware->append(NoindexPrivateAndThinPages::class);

        $middleware->web(prepend: [
            FrontendMaintenanceMode::class,
        ]);

        $middleware->web(append: [
            RequestTimingMiddleware::class,
            RecordUserLastSeen::class,
            ApplySearchIconToggleOverride::class,
        ]);

        // The base ValidatePathEncoding answers 400 to any path that is not valid UTF-8. Old forum
        // links percent-encode in Windows code pages, which a plain 400 would throw away; replacing
        // the middleware with a subclass repairs what it can and still 400s the rest.
        $middleware->replace(ValidatePathEncoding::class, RepairLegacyPathEncoding::class);

        // Mail providers post their delivery events here with no session or token; each request is
        // checked by the controller.
        $middleware->preventRequestForgery([
            'webhooks/mailgun',
            'webhooks/mailtrap',
            // The service worker re-posts a rotated push subscription from a background context
            // with no CSRF token. The controller checks the old endpoint is already on file for
            // the signed-in member, so a forged cross-site request cannot plant its own.
            'push/subscriptions/resync',
        ]);

        $middleware->alias([
            'frontend.verified' => EnsureFrontendEmailIsVerified::class,
            'plugins.submissions.open' => EnsurePluginSubmissionsAreOpen::class,
        ]);

        // Before route model binding, so a forum that is down answers 503 even for an unknown thread.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureSubsystemIsAvailable::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Fuzzers replay Livewire update payloads against the Filament login component with mangled
        // snapshots. Livewire rejects them, but each rejection surfaces as one of these two
        // exceptions; neither is actionable and a burst of them drowns real reports.
        $exceptions->dontReport([CannotUpdateLockedPropertyException::class]);
        $exceptions->dontReportWhen(
            static fn (Throwable $e): bool => $e instanceof TypeError
                && str_contains($e->getMessage(), 'Filament\\Notifications\\Collection::fromLivewire()'),
        );
        $exceptions->reportable(static function (Throwable $e) {
            if (app()->bound('honeybadger')) {
                app('honeybadger')->notify($e, app('request'));
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
