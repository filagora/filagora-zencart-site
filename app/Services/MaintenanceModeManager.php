<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterval;
use Filagora\Core\Contracts\AccessChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class MaintenanceModeManager
{
    /** @param list<string> $allowedIps @param list<string> $exceptPaths */
    public function requestShouldBypass(Request $request, array $allowedIps, array $exceptPaths): bool
    {
        if ((bool) $request->attributes->get('bypass_frontend_maintenance', false)) {
            return true;
        }

        if ($this->requestIpIsAllowed($request, $allowedIps)) {
            return true;
        }

        $user = $request->user();
        if ($user instanceof User && app(AccessChecker::class)->allows($user, 'operator.dashboard.access')) {
            return true;
        }

        if ($this->pathMatchesException($request->path(), $exceptPaths)) {
            return true;
        }

        return $this->requestOriginatesFromExceptedPath($request, $exceptPaths);
    }

    public function response(Request $request, string $message, int $retryAfter, ?string $subsystem = null, ?string $startedAt = null): SymfonyResponse
    {
        $headers = [
            'Retry-After' => (string) $retryAfter,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ];

        $startedAtUtc = $this->parseStartedAt($startedAt);

        if ($request->expectsJson()) {
            return new JsonResponse(array_filter([
                'message' => $message,
                'maintenance' => true,
                'subsystem' => $subsystem,
                'started_at' => $startedAtUtc?->toIso8601String(),
            ], static fn (mixed $value): bool => $value !== null), Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        }

        return response()
            ->view('errors.maintenance', [
                'message' => $message,
                'retryAfter' => $retryAfter,
                'retryAfterHuman' => $this->humanizeRetryAfter($retryAfter),
                'subsystem' => $subsystem,
                'startedAtUtc' => $startedAtUtc?->format('d M Y, H:i \U\T\C'),
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->withHeaders($headers);
    }

    /**
     * FRONTEND_MAINTENANCE_STARTED_AT is set by hand alongside the enabled
     * flag, so it can be missing or malformed — treat either as "unknown"
     * rather than failing the whole maintenance response over it.
     */
    private function parseStartedAt(?string $startedAt): ?Carbon
    {
        $trimmed = trim((string) $startedAt);
        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param list<string> $allowedIps */
    private function requestIpIsAllowed(Request $request, array $allowedIps): bool
    {
        $ip = $request->ip();
        if (! is_string($ip) || $ip === '') {
            return false;
        }

        $normalizedIps = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $allowedIps),
            static fn (string $value): bool => $value !== '',
        ));

        return $normalizedIps !== [] && IpUtils::checkIp($ip, $normalizedIps);
    }

    /** @param list<string> $exceptPaths */
    private function requestOriginatesFromExceptedPath(Request $request, array $exceptPaths): bool
    {
        if (! Str::startsWith($request->path(), 'livewire')) {
            return false;
        }

        $refererPath = parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH);
        if (! is_string($refererPath) || $refererPath === '') {
            return false;
        }

        return $this->pathMatchesException(ltrim($refererPath, '/'), $exceptPaths);
    }

    /** @param list<string> $exceptPaths */
    private function pathMatchesException(string $path, array $exceptPaths): bool
    {
        foreach ($exceptPaths as $exceptPath) {
            $normalized = trim((string) $exceptPath);
            if ($normalized !== '' && Str::is($normalized, $path)) {
                return true;
            }
        }

        return false;
    }

    private function humanizeRetryAfter(int $retryAfter): string
    {
        return CarbonInterval::seconds(max(1, $retryAfter))
            ->cascade()
            ->locale(app()->getLocale())
            ->forHumans([
                'parts' => 2,
                'join' => true,
                'short' => false,
            ]);
    }
}
