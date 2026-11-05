<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceModeManager;
use App\Services\SubsystemAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubsystemIsAvailable
{
    public function __construct(
        private readonly SubsystemAvailability $availability,
        private readonly MaintenanceModeManager $maintenanceMode,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $subsystem): Response
    {
        if (! $this->availability->isUnderMaintenance($subsystem)) {
            return $next($request);
        }

        if (app()->runningUnitTests() && ! config('app.subsystem_maintenance.allow_during_tests', false)) {
            return $next($request);
        }

        if ($this->maintenanceMode->requestShouldBypass(
            $request,
            (array) config('app.frontend_maintenance.allowed_ips', []),
            (array) config('app.subsystem_maintenance.except', []),
        )) {
            return $next($request);
        }

        return $this->maintenanceMode->response(
            $request,
            $this->availability->message($subsystem),
            $this->availability->retryAfter($subsystem),
            $subsystem,
        );
    }
}
