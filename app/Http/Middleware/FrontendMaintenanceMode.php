<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceModeManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

class FrontendMaintenanceMode
{
    public function __construct(private readonly MaintenanceModeManager $maintenanceMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.frontend_maintenance.enabled')) {
            return $next($request);
        }

        if (app()->runningUnitTests() && ! config('app.frontend_maintenance.allow_during_tests', false)) {
            return $next($request);
        }

        if ($this->maintenanceMode->requestShouldBypass(
            $request,
            (array) config('app.frontend_maintenance.allowed_ips', []),
            (array) config('app.frontend_maintenance.except', []),
        )) {
            return $next($request);
        }

        return $this->maintenanceMode->response(
            $request,
            $this->maintenanceMessage(),
            max(0, (int) config('app.frontend_maintenance.retry_after', 3600)),
            startedAt: config('app.frontend_maintenance.started_at'),
        );
    }

    private function maintenanceMessage(): string
    {
        $override = trim((string) config('app.frontend_maintenance.message', ''));
        if ($override !== '') {
            return $override;
        }

        $messageKey = trim((string) config('app.frontend_maintenance.message_key', 'maintenance.message'));
        if ($messageKey !== '' && Lang::has($messageKey)) {
            return __($messageKey);
        }

        return 'The site is temporarily unavailable while we perform maintenance. Please check back soon.';
    }
}
