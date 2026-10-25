<?php

namespace App\Support;

class LegacyRedirectResult
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    private function __construct(
        public readonly string $outcome,
        public readonly ?string $targetUrl = null,
        public readonly int $statusCode = 404,
        public readonly array $context = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function redirect(string $targetUrl, int $statusCode = 301, array $context = []): self
    {
        return new self('redirect', $targetUrl, $statusCode, $context);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function gone(array $context = []): self
    {
        return new self('gone', null, 410, $context);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function notFound(array $context = []): self
    {
        return new self('not_found', null, 404, $context);
    }
}
