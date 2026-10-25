<?php

namespace App\Services;

use App\Support\SpamCheckResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SpamDetectionService
{
    /**
     * @param  array{username?:string, email?:string, ipaddress?:string|null}  $payload
     */
    public function assessRegistration(array $payload): SpamCheckResult
    {
        $result = new SpamCheckResult;

        if (! (bool) config('spam.enabled', false)) {
            return $result;
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $ipAddress = trim((string) ($payload['ipaddress'] ?? ''));

        $this->checkBlockedEmailPatterns($email, $result);
        $this->checkProjectHoneypot($ipAddress, $result);
        $this->checkStopForumSpam($username, $email, $ipAddress, $result);
        $this->checkTemporaryEmailProvider($email, $result);

        if ($result->isSpam || $result->isSuspicious) {
            Log::info('registration_spam_check', [
                'email' => $email,
                'username' => $username,
                'ipaddress' => $ipAddress,
                'is_spam' => $result->isSpam,
                'is_suspicious' => $result->isSuspicious,
                'sources' => array_values(array_unique($result->sources)),
                'reasons' => array_values(array_unique($result->reasons)),
                'details' => $result->details,
            ]);
        }

        return $result;
    }

    private function checkBlockedEmailPatterns(string $email, SpamCheckResult $result): void
    {
        if ($email === '') {
            return;
        }

        foreach ((array) config('spam.blocked_email_patterns', []) as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            if (@preg_match('/'.$pattern.'/', $email) === 1) {
                $result->flagSpam('blocked_email_patterns', 'Email matched blocked pattern.', [
                    'pattern' => $pattern,
                ]);

                return;
            }
        }
    }

    private function checkProjectHoneypot(string $ipAddress, SpamCheckResult $result): void
    {
        if (
            ! (bool) config('spam.project_honeypot.enabled', false)
            || $ipAddress === ''
        ) {
            return;
        }

        $apiKey = trim((string) config('spam.project_honeypot.api_key', ''));
        $lookupDomain = trim((string) config('spam.project_honeypot.lookup_domain', 'dnsbl.httpbl.org'));

        if ($apiKey === '' || $lookupDomain === '') {
            return;
        }

        $octets = explode('.', $ipAddress);
        if (count($octets) !== 4) {
            return;
        }

        $query = $apiKey.'.'.implode('.', array_reverse($octets)).'.'.$lookupDomain;
        $response = gethostbyname($query);

        if ($response === $query) {
            return;
        }

        $parts = explode('.', $response);
        if (count($parts) !== 4 || $parts[0] !== '127') {
            return;
        }

        $lastSeenDays = (int) $parts[1];
        $visitorType = (int) $parts[3];

        if ($lastSeenDays >= (int) config('spam.project_honeypot.max_last_seen_days', 21)) {
            return;
        }

        if ($visitorType >= (int) config('spam.project_honeypot.spammer_visitor_min', 4)) {
            $result->flagSpam('project_honeypot', 'Project Honey Pot identified a known spam IP.', [
                'response' => $response,
                'last_seen_days' => $lastSeenDays,
                'visitor_type' => $visitorType,
            ]);

            return;
        }

        if ($visitorType >= (int) config('spam.project_honeypot.suspicious_visitor_min', 1)) {
            $result->flagSuspicious('project_honeypot', 'Project Honey Pot identified a suspicious IP.', [
                'response' => $response,
                'last_seen_days' => $lastSeenDays,
                'visitor_type' => $visitorType,
            ]);
        }
    }

    private function checkStopForumSpam(string $username, string $email, string $ipAddress, SpamCheckResult $result): void
    {
        if (! (bool) config('spam.stop_forum_spam.enabled', true)) {
            return;
        }

        $url = trim((string) config('spam.stop_forum_spam.url', ''));
        if ($url === '') {
            return;
        }

        $query = array_filter([
            'username' => $username !== '' ? $username : null,
            'email' => $email !== '' ? $email : null,
            'ip' => $ipAddress !== '' ? $ipAddress : null,
            'json' => '',
        ], static fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::timeout(8)->acceptJson()->get($url, $query);
            if (! $response->ok()) {
                return;
            }

            $data = $response->json();
            if (! is_array($data) || ! (bool) ($data['success'] ?? false)) {
                return;
            }

            $confidenceThreshold = (int) config('spam.stop_forum_spam.confidence_threshold', 40);
            $frequencyThreshold = (int) config('spam.stop_forum_spam.frequency_threshold', 1);

            if ($this->stopForumSpamFieldIsSpam($data['username'] ?? null, $confidenceThreshold, $frequencyThreshold)
                || $this->stopForumSpamFieldIsSpam($data['ip'] ?? null, $confidenceThreshold, $frequencyThreshold)
                || $this->stopForumSpamFieldIsSpam($data['email'] ?? null, $confidenceThreshold, $frequencyThreshold)
            ) {
                $result->flagSpam('stop_forum_spam', 'Stop Forum Spam matched the registration details.', [
                    'username' => $data['username'] ?? null,
                    'ip' => $data['ip'] ?? null,
                    'email' => $data['email'] ?? null,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('stop_forum_spam_lookup_failed', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function stopForumSpamFieldIsSpam(mixed $field, int $confidenceThreshold, int $frequencyThreshold): bool
    {
        if (! is_array($field)) {
            return false;
        }

        if ((int) ($field['frequency'] ?? 0) >= $frequencyThreshold) {
            return true;
        }

        if ((float) ($field['confidence'] ?? 0) > $confidenceThreshold) {
            return true;
        }

        return (bool) ($field['blacklisted'] ?? false);
    }

    private function checkTemporaryEmailProvider(string $email, SpamCheckResult $result): void
    {
        if (! (bool) config('spam.temporary_email.enabled', false) || $email === '') {
            return;
        }

        $url = trim((string) config('spam.temporary_email.url', ''));
        $apiKey = trim((string) config('spam.temporary_email.api_key', ''));

        if ($url === '' || $apiKey === '') {
            return;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withToken($apiKey)
                ->get($url, ['email' => $email]);

            if (! $response->ok()) {
                return;
            }

            $data = $response->json();
            if (! is_array($data)) {
                return;
            }

            if ((bool) ($data['is_temporary'] ?? false)) {
                $result->flagSuspicious('temporary_email', 'Disposable email provider detected.', [
                    'provider' => $data['provider'] ?? null,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('temporary_email_lookup_failed', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
